<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Model\LedgerEntry;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Domain\Repository\WarehouseRepositoryInterface;

class InventoryService
{
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;
    private ItemRepositoryInterface $itemRepo;
    private WarehouseRepositoryInterface $warehouseRepo;
    private \PDO $pdo;
    private JournalService $journal;

    private array $inventoryAccountMap = [
        'material' => '152', 'tool' => '153',
        'product' => '155', 'merchandise' => '156',
        'other' => '152',
    ];

    public function __construct(
        AccountRepositoryInterface $accountRepo,
        TransactionRepositoryInterface $txnRepo,
        ItemRepositoryInterface $itemRepo,
        WarehouseRepositoryInterface $warehouseRepo,
        JournalService $journal,
        \PDO $pdo
    ) {
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
        $this->itemRepo = $itemRepo;
        $this->warehouseRepo = $warehouseRepo;
        $this->journal = $journal;
        $this->pdo = $pdo;
    }

    private function wrapInTransaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn();
            $this->pdo->commit();
            return $result;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function receiveGoods(string $itemId, float $qty, float $unitPrice,
        array $addonCosts, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $unitPrice, $addonCosts, $reference, $createdBy) {
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Item not found: {$itemId}");

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';
            $itemCost = $qty * $unitPrice;
            $totalAddon = array_sum(array_column($addonCosts, 'amount'));
            $totalCost = $itemCost + $totalAddon;

            $lines = [
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => '331', 'amount' => $totalCost, 'is_debit' => false],
            ];

            $txn = $this->journal->postEntry("Goods receipt: {$item->getName()}", $reference, $lines, $createdBy);

            $item->setStockQty($item->getStockQty() + $qty);
            $this->itemRepo->save($item);

            $this->saveCostLayer($itemId, $qty, $unitPrice, $totalAddon / max($qty, 1), null);

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost];
        });
    }

    public function issueGoods(string $itemId, float $qty, string $issueType,
        string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $issueType, $reference, $createdBy) {
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Item not found: {$itemId}");
            if ($item->getStockQty() < $qty) {
                throw new \InvalidArgumentException(
                    "Insufficient stock: have {$item->getStockQty()}, need {$qty}"
                );
            }

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';
            $costResult = $this->consumeCostLayers($itemId, $qty, null);
            $totalCost = $costResult['total_cost'];
            $expenseCode = match($issueType) {
                'production' => '154',
                'construction' => '241',
                'sale' => '632',
                default => throw new \InvalidArgumentException("Invalid issue type: {$issueType}"),
            };

            $lines = [
                ['account_code' => $expenseCode, 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => false],
            ];

            $txn = $this->journal->postEntry("Goods issue: {$item->getName()}", $reference, $lines, $createdBy, $issueType === 'construction');

            $item->setStockQty($item->getStockQty() - $qty);
            $this->itemRepo->save($item);

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost];
        });
    }

    public function transferGoods(string $itemId, float $qty, ?string $fromWarehouseId, string $toWarehouseId, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $fromWarehouseId, $toWarehouseId, $reference, $createdBy) {
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Item not found: {$itemId}");

            if ($fromWarehouseId !== null) {
                $from = $this->warehouseRepo->findById($fromWarehouseId);
                if (!$from) throw new \InvalidArgumentException("Source warehouse not found: {$fromWarehouseId}");
            }
            $to = $this->warehouseRepo->findById($toWarehouseId);
            if (!$to) throw new \InvalidArgumentException("Destination warehouse not found: {$toWarehouseId}");

            $pdo = $this->getPdo();
            if ($fromWarehouseId !== null) {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id = ? AND qty > 0");
                $stmt->execute([$itemId, $fromWarehouseId]);
            } else {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id IS NULL AND qty > 0");
                $stmt->execute([$itemId]);
            }
            $sourceStock = (float)$stmt->fetchColumn();
            if ($sourceStock < $qty) {
                throw new \InvalidArgumentException("Insufficient stock in source: have {$sourceStock}, need {$qty}");
            }

            if ($fromWarehouseId !== null) {
                $stmt = $pdo->prepare("SELECT id, qty, unit_cost, addon_per_unit FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id = ? AND qty > 0 ORDER BY created_at ASC");
                $stmt->execute([$itemId, $fromWarehouseId]);
            } else {
                $stmt = $pdo->prepare("SELECT id, qty, unit_cost, addon_per_unit FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id IS NULL AND qty > 0 ORDER BY created_at ASC");
                $stmt->execute([$itemId]);
            }

            $remaining = $qty;
            $totalCost = 0;
            $transferLayers = [];
            while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) && $remaining > 0) {
                $consume = min($row['qty'], $remaining);
                $layerCost = $consume * ((float)$row['unit_cost'] + (float)$row['addon_per_unit']);
                $totalCost += $layerCost;
                $transferLayers[] = [
                    'id' => $row['id'],
                    'consume' => $consume,
                    'unit_cost' => $row['unit_cost'],
                    'addon_per_unit' => $row['addon_per_unit'],
                ];
                $remaining -= $consume;
            }

            foreach ($transferLayers as $tl) {
                $update = $pdo->prepare("UPDATE inventory_cost_layers SET qty = qty - ? WHERE id = ?");
                $update->execute([$tl['consume'], $tl['id']]);
            }

            foreach ($transferLayers as $tl) {
                $insert = $pdo->prepare("INSERT INTO inventory_cost_layers (id, item_id, warehouse_id, qty, unit_cost, addon_per_unit, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $insert->execute([uniqid('cst_'), $itemId, $toWarehouseId, $tl['consume'], $tl['unit_cost'], $tl['addon_per_unit']]);
            }

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';

            $fromName = $fromWarehouseId ? $from->getName() : 'General';
            $txn = $this->journal->postEntry(
                "Transfer: {$item->getName()} ({$fromName} → {$to->getName()})",
                $reference,
                [
                    ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => true],
                    ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => false],
                ],
                $createdBy
            );

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    public function recordInTransit(string $itemId, float $qty, float $unitPrice,
        array $addonCosts, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $unitPrice, $addonCosts, $reference, $createdBy) {
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Item not found: {$itemId}");

            $itemCost = $qty * $unitPrice;
            $totalAddon = array_sum(array_column($addonCosts, 'amount'));
            $totalCost = $itemCost + $totalAddon;

            $lines = [
                ['account_code' => '151', 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => '331', 'amount' => $totalCost, 'is_debit' => false],
            ];

            $txn = $this->journal->postEntry("In transit: {$item->getName()}", $reference, $lines, $createdBy);

            $pdo = $this->getPdo();
            $transitId = uniqid('trn_');
            $addonPerUnit = $totalAddon / max($qty, 1);
            $stmt = $pdo->prepare(
                "INSERT INTO inventory_in_transit (id, item_id, qty, unit_cost, addon_per_unit, reference) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$transitId, $itemId, $qty, $unitPrice, $addonPerUnit, $reference]);

            return ['transit_id' => $transitId, 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    public function receiveFromTransit(string $transitId, float $qty, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($transitId, $qty, $reference, $createdBy) {
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT * FROM inventory_in_transit WHERE id = ?");
            $stmt->execute([$transitId]);
            $transit = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$transit) throw new \InvalidArgumentException("Transit record not found: {$transitId}");
            if ($transit['qty'] < $qty) {
                throw new \InvalidArgumentException("Insufficient in transit: have {$transit['qty']}, need {$qty}");
            }

            $item = $this->itemRepo->findById($transit['item_id']);
            if (!$item) throw new \InvalidArgumentException("Item not found: {$transit['item_id']}");

            $unitCost = (float)$transit['unit_cost'];
            $addonPerUnit = (float)$transit['addon_per_unit'];
            $totalCost = $qty * ($unitCost + $addonPerUnit);
            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';

            $lines = [
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => '151', 'amount' => $totalCost, 'is_debit' => false],
            ];

            $txn = $this->journal->postEntry("Receive from transit: {$item->getName()}", $reference, $lines, $createdBy);

            $item->setStockQty($item->getStockQty() + $qty);
            $this->itemRepo->save($item);

            $this->saveCostLayer($transit['item_id'], $qty, $unitCost, $addonPerUnit, null);

            $newQty = $transit['qty'] - $qty;
            if ($newQty <= 0) {
                $pdo->prepare("DELETE FROM inventory_in_transit WHERE id = ?")->execute([$transitId]);
            } else {
                $pdo->prepare("UPDATE inventory_in_transit SET qty = ? WHERE id = ?")->execute([$newQty, $transitId]);
            }

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    public function consignGoods(string $itemId, float $qty, string $consignee,
        string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $consignee, $reference, $createdBy) {
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Item not found: {$itemId}");
            if ($item->getStockQty() < $qty) {
                throw new \InvalidArgumentException("Insufficient stock: have {$item->getStockQty()}, need {$qty}");
            }

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';
            $costResult = $this->consumeCostLayers($itemId, $qty, null);
            $totalCost = $costResult['total_cost'];
            $avgUnitCost = $qty > 0 ? $totalCost / $qty : 0;

            $lines = [
                ['account_code' => '157', 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => false],
            ];

            $txn = $this->journal->postEntry("Consignment: {$item->getName()} → {$consignee}", $reference, $lines, $createdBy);

            $item->setStockQty($item->getStockQty() - $qty);
            $this->itemRepo->save($item);

            $pdo = $this->getPdo();
            $cId = uniqid('csn_');
            $pdo->prepare("INSERT INTO inventory_consignment (id, item_id, qty, unit_cost, addon_per_unit, consignee, reference) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$cId, $itemId, $qty, $avgUnitCost, 0, $consignee, $reference]);

            return ['consignment_id' => $cId, 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    public function sellConsigned(string $consignmentId, float $qty, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($consignmentId, $qty, $reference, $createdBy) {
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT * FROM inventory_consignment WHERE id = ?");
            $stmt->execute([$consignmentId]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$record) throw new \InvalidArgumentException("Consignment record not found: {$consignmentId}");
            if ($record['qty'] < $qty) {
                throw new \InvalidArgumentException("Insufficient consignment: have {$record['qty']}, need {$qty}");
            }

            $unitCost = (float)$record['unit_cost'] + (float)$record['addon_per_unit'];
            $totalCost = $qty * $unitCost;

            $txn = $this->journal->postEntry("Consignment sale", $reference, [
                ['account_code' => '632', 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => '157', 'amount' => $totalCost, 'is_debit' => false],
            ], $createdBy);

            $newQty = $record['qty'] - $qty;
            if ($newQty <= 0) {
                $pdo->prepare("DELETE FROM inventory_consignment WHERE id = ?")->execute([$consignmentId]);
            } else {
                $pdo->prepare("UPDATE inventory_consignment SET qty = ? WHERE id = ?")->execute([$newQty, $consignmentId]);
            }

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    public function returnConsigned(string $consignmentId, float $qty, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($consignmentId, $qty, $reference, $createdBy) {
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT * FROM inventory_consignment WHERE id = ?");
            $stmt->execute([$consignmentId]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$record) throw new \InvalidArgumentException("Consignment record not found: {$consignmentId}");
            if ($record['qty'] < $qty) {
                throw new \InvalidArgumentException("Insufficient consignment for return: have {$record['qty']}, need {$qty}");
            }

            $item = $this->itemRepo->findById($record['item_id']);
            if (!$item) throw new \InvalidArgumentException("Item not found: {$record['item_id']}");

            $unitCost = (float)$record['unit_cost'] + (float)$record['addon_per_unit'];
            $totalCost = $qty * $unitCost;
            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';

            $txn = $this->journal->postEntry("Consignment return", $reference, [
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => '157', 'amount' => $totalCost, 'is_debit' => false],
            ], $createdBy);

            $item->setStockQty($item->getStockQty() + $qty);
            $this->itemRepo->save($item);

            $this->saveCostLayer($record['item_id'], $qty, (float)$record['unit_cost'], (float)$record['addon_per_unit'], null);

            $newQty = $record['qty'] - $qty;
            if ($newQty <= 0) {
                $pdo->prepare("DELETE FROM inventory_consignment WHERE id = ?")->execute([$consignmentId]);
            } else {
                $pdo->prepare("UPDATE inventory_consignment SET qty = ? WHERE id = ?")->execute([$newQty, $consignmentId]);
            }

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    public function adjustPhysicalCount(string $itemId, float $actualQty, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $actualQty, $reference, $createdBy) {
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Item not found: {$itemId}");

            $systemQty = $item->getStockQty();
            $diff = $actualQty - $systemQty;
            if (abs($diff) < 0.001) {
                return ['message' => 'No adjustment needed', 'diff' => 0];
            }

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';

            if ($diff > 0) {
                $unitCost = $item->getPurchasePrice() ?: 0;
                $diffValue = abs($diff) * $unitCost;

                $txn = $this->journal->postEntry("Count surplus: {$item->getName()}", $reference, [
                    ['account_code' => $inventoryCode, 'amount' => $diffValue, 'is_debit' => true],
                    ['account_code' => '711', 'amount' => $diffValue, 'is_debit' => false],
                ], $createdBy);

                $this->saveCostLayer($itemId, $diff, $unitCost, 0, null);

                $item->setStockQty($actualQty);
                $this->itemRepo->save($item);

                return [
                    'transaction_id' => $txn->getId(), 'diff' => $diff,
                    'diff_value' => $diffValue, 'adjusted' => true,
                ];
            } else {
                $costResult = $this->consumeCostLayers($itemId, abs($diff), null);
                $diffValue = $costResult['total_cost'];

                $txn = $this->journal->postEntry("Count shortage: {$item->getName()}", $reference, [
                    ['account_code' => '632', 'amount' => $diffValue, 'is_debit' => true],
                    ['account_code' => $inventoryCode, 'amount' => $diffValue, 'is_debit' => false],
                ], $createdBy);

                $item->setStockQty($actualQty);
                $this->itemRepo->save($item);

                return [
                    'transaction_id' => $txn->getId(), 'diff' => $diff,
                    'diff_value' => $diffValue, 'adjusted' => true,
                ];
            }
        });
    }

    public function createCountSession(array $lines, string $reference, string $notes, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($lines, $reference, $notes, $createdBy) {
            $pdo = $this->getPdo();
            $sessionId = uniqid('cnt_');
            $totalDiff = 0;
            $count = 0;

            $pdo->prepare("INSERT INTO inventory_count_sessions (id, session_date, reference, notes, status, created_by) VALUES (?, CURDATE(), ?, ?, 'draft', ?)")
                ->execute([$sessionId, $reference, $notes, $createdBy]);

            foreach ($lines as $line) {
                $item = $this->itemRepo->findById($line['item_id']);
                if (!$item) continue;

                $systemQty = $item->getStockQty();
                $actualQty = (float)$line['actual_qty'];
                $diffQty = $actualQty - $systemQty;
                $unitCost = $item->getPurchasePrice();
                $diffValue = $diffQty * $unitCost;
                $totalDiff += $diffValue;
                $count++;

                $pdo->prepare("INSERT INTO inventory_count_lines (id, session_id, item_id, system_qty, actual_qty, diff_qty, unit_cost, diff_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([uniqid('ctl_'), $sessionId, $line['item_id'], $systemQty, $actualQty, $diffQty, $unitCost, $diffValue]);
            }

            $pdo->prepare("UPDATE inventory_count_sessions SET total_items = ?, total_diff = ? WHERE id = ?")
                ->execute([$count, $totalDiff, $sessionId]);

            return ['session_id' => $sessionId, 'total_items' => $count, 'total_diff' => $totalDiff];
        });
    }

    public function recordImpairment(string $itemId, float $amount, string $reference, string $notes, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $amount, $reference, $notes, $createdBy) {
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Item not found: {$itemId}");
            if ($amount <= 0) throw new \InvalidArgumentException("Provision amount must be positive");

            $txn = $this->journal->postEntry("Impairment: {$item->getName()}", $reference, [
                ['account_code' => '632', 'amount' => $amount, 'is_debit' => true],
                ['account_code' => '2294', 'amount' => $amount, 'is_debit' => false],
            ], $createdBy);

            $pdo = $this->getPdo();
            $impairId = uniqid('imp_');
            $pdo->prepare("INSERT INTO inventory_impairment (id, item_id, provision_amount, remaining_amount, reference, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$impairId, $itemId, $amount, $amount, $reference, $notes, $createdBy]);

            return ['impairment_id' => $impairId, 'transaction_id' => $txn->getId(), 'amount' => $amount];
        });
    }

    public function reverseImpairment(string $impairmentId, float $amount, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($impairmentId, $amount, $reference, $createdBy) {
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT * FROM inventory_impairment WHERE id = ?");
            $stmt->execute([$impairmentId]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$record) throw new \InvalidArgumentException("Impairment record not found: {$impairmentId}");
            if ($record['remaining_amount'] < $amount) {
                throw new \InvalidArgumentException("Insufficient remaining provision: have {$record['remaining_amount']}, need {$amount}");
            }

            $item = $this->itemRepo->findById($record['item_id']);
            $itemName = $item ? $item->getName() : $record['item_id'];

            $txn = $this->journal->postEntry("Impairment reversal: {$itemName}", $reference, [
                ['account_code' => '2294', 'amount' => $amount, 'is_debit' => true],
                ['account_code' => '632', 'amount' => $amount, 'is_debit' => false],
            ], $createdBy);

            $newRemaining = $record['remaining_amount'] - $amount;
            $pdo->prepare("UPDATE inventory_impairment SET remaining_amount = ? WHERE id = ?")
                ->execute([$newRemaining, $impairmentId]);

            return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'remaining' => $newRemaining];
        });
    }

    public function issuePromotional(string $itemId, float $qty, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $reference, $createdBy) {
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Item not found: {$itemId}");
            if ($item->getStockQty() < $qty) {
                throw new \InvalidArgumentException("Insufficient stock: have {$item->getStockQty()}, need {$qty}");
            }

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';
            $costResult = $this->consumeCostLayers($itemId, $qty, null);
            $totalCost = $costResult['total_cost'];

            $txn = $this->journal->postEntry("Promotional: {$item->getName()}", $reference, [
                ['account_code' => '641', 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => false],
            ], $createdBy);

            $item->setStockQty($item->getStockQty() - $qty);
            $this->itemRepo->save($item);

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    public function returnFromCustomer(string $itemId, float $qty, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $reference, $createdBy) {
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Item not found: {$itemId}");
            if ($qty <= 0) throw new \InvalidArgumentException("Qty must be positive");

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';

            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty),0) as qty, COALESCE(SUM(qty * unit_cost + qty * addon_per_unit),0) as val FROM inventory_cost_layers WHERE item_id = ?");
            $stmt->execute([$itemId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $avgUnitCost = ($row['qty'] > 0) ? $row['val'] / $row['qty'] : ($item->getPurchasePrice() ?: 0);
            $totalCost = $qty * $avgUnitCost;

            $txn = $this->journal->postEntry("Customer return: {$item->getName()}", $reference, [
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => '632', 'amount' => $totalCost, 'is_debit' => false],
            ], $createdBy);

            $item->setStockQty($item->getStockQty() + $qty);
            $this->itemRepo->save($item);

            $this->saveCostLayer($itemId, $qty, $avgUnitCost, 0, null);

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    public function closePeriodicInventory(string $itemId, float $closingQty, float $closingUnitCost, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $closingQty, $closingUnitCost, $reference, $createdBy) {
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Item not found: {$itemId}");

            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) as total_qty, COALESCE(SUM(qty * unit_cost + qty * addon_per_unit), 0) as total_value FROM inventory_cost_layers WHERE item_id = ?");
            $stmt->execute([$itemId]);
            $layers = $stmt->fetch(\PDO::FETCH_ASSOC);
            $totalAvailableValue = (float)$layers['total_value'];

            $closingValue = $closingQty * $closingUnitCost;
            $cogsValue = max(0, $totalAvailableValue - $closingValue);

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';

            if ($cogsValue > 0.01) {
                $txn = $this->journal->postEntry("Periodic close: {$item->getName()}", $reference, [
                    ['account_code' => '632', 'amount' => $cogsValue, 'is_debit' => true],
                    ['account_code' => $inventoryCode, 'amount' => $cogsValue, 'is_debit' => false],
                ], $createdBy);
                $txnId = $txn->getId();
            } else {
                $txnId = null;
            }

            $pdo->prepare("DELETE FROM inventory_cost_layers WHERE item_id = ?")->execute([$itemId]);
            $this->saveCostLayer($itemId, $closingQty, $closingUnitCost, 0, null);

            $item->setStockQty($closingQty);
            $item->setPurchasePrice($closingUnitCost);
            $this->itemRepo->save($item);

            $periodId = uniqid('prd_');
            $periodStart = date('Y-m-01');
            $periodEnd = date('Y-m-t');
            $pdo->prepare("INSERT INTO periodic_inventory (id, item_id, period_start, period_end, opening_qty, opening_value, purchases_qty, purchases_value, closing_qty, closing_value, cogs, reference, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$periodId, $itemId, $periodStart, $periodEnd, 0, 0, 0, 0, $closingQty, $closingValue, $cogsValue, $reference, $createdBy]);

            return [
                'periodic_id' => $periodId,
                'total_available' => $totalAvailableValue,
                'closing_value' => $closingValue, 'cogs' => $cogsValue,
                'transaction_id' => $txnId,
            ];
        });
    }

    public function calculateAndUpdateUnitCost(string $itemId): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT SUM(qty) as total_qty, SUM(qty * unit_cost + addon_per_unit * qty) as total_cost FROM inventory_cost_layers WHERE item_id = ?");
        $stmt->execute([$itemId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row && $row['total_qty'] > 0) {
            $avg = $row['total_cost'] / $row['total_qty'];
            $item = $this->itemRepo->findById($itemId);
            if ($item) {
                $item->setPurchasePrice($avg);
                $this->itemRepo->save($item);
            }
        }
    }

    private function saveCostLayer(string $itemId, float $qty, float $unitCost, float $addonPerUnit, ?string $warehouseId): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            "INSERT INTO inventory_cost_layers (id, item_id, warehouse_id, qty, unit_cost, addon_per_unit, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([uniqid('cst_'), $itemId, $warehouseId, $qty, $unitCost, $addonPerUnit]);
    }

    private function consumeCostLayers(string $itemId, float $qty, ?string $warehouseId): array
    {
        $pdo = $this->getPdo();
        if ($warehouseId !== null) {
            $stmt = $pdo->prepare("SELECT id, qty, unit_cost, addon_per_unit FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id = ? AND qty > 0 ORDER BY created_at ASC");
            $stmt->execute([$itemId, $warehouseId]);
        } else {
            $stmt = $pdo->prepare("SELECT id, qty, unit_cost, addon_per_unit FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id IS NULL AND qty > 0 ORDER BY created_at ASC");
            $stmt->execute([$itemId]);
        }
        $remaining = $qty;
        $totalCost = 0.0;
        while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) && $remaining > 0) {
            $consume = min($row['qty'], $remaining);
            $layerUnitCost = (float)$row['unit_cost'] + (float)$row['addon_per_unit'];
            $totalCost += $consume * $layerUnitCost;
            $update = $pdo->prepare("UPDATE inventory_cost_layers SET qty = qty - ? WHERE id = ?");
            $update->execute([$consume, $row['id']]);
            $remaining -= $consume;
        }
        return ['total_cost' => $totalCost, 'remaining' => $remaining];
    }

    private function getPdo(): \PDO
    {
        return $this->pdo;
    }
}

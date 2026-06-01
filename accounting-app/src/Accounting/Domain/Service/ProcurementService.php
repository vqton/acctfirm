<?php
declare(strict_types=1);
namespace Accounting\Domain\Service;

use Accounting\Domain\Model\PurchaseRequisition;
use Accounting\Domain\Model\PurchaseRequisitionLine;
use Accounting\Domain\Model\PurchaseOrder;
use Accounting\Domain\Model\PurchaseOrderLine;
use Accounting\Domain\Model\GoodsReceipt;
use Accounting\Domain\Model\GoodsReceiptLine;
use Accounting\Domain\Repository\PurchaseRequisitionRepositoryInterface;
use Accounting\Domain\Repository\PurchaseOrderRepositoryInterface;
use Accounting\Domain\Repository\GoodsReceiptRepositoryInterface;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Domain\Repository\SupplierRepositoryInterface;
use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Contract\JournalServiceInterface;
use Accounting\Domain\Contract\InventoryServiceInterface;

// Dịch vụ nghiệp vụ Mua hàng (Procurement Engine)
//
// Quy trình: Đề nghị mua (PR) → Đơn đặt hàng (PO) → Nhập kho (GR) → Hóa đơn (Invoice)
//
// Các ràng buộc nghiệp vụ:
// - PR → PO: PO phải tham chiếu PR đã được phê duyệt
// - PO → GR: GR không vượt quá số lượng PO
// - KHÔNG cho phép xóa sau khi đã phê duyệt (chỉ được đánh dấu cancelled)
//
// Tích hợp:
// - JournalService: post bút toán nhập kho
// - InventoryService: nhập hàng vào kho
// - VoucherService: sinh số chứng từ (PR/PO/PNK)
// - ApprovalRoutingService: xác định luồng phê duyệt

class ProcurementService
{
    private PurchaseRequisitionRepositoryInterface $prRepo;
    private PurchaseOrderRepositoryInterface $poRepo;
    private GoodsReceiptRepositoryInterface $grRepo;
    private ItemRepositoryInterface $itemRepo;
    private SupplierRepositoryInterface $supplierRepo;
    private JournalServiceInterface $journal;
    private InventoryServiceInterface $inventory;
    private AuditLoggerInterface $auditLogger;
    private ApprovalRoutingService $approval;
    private \PDO $pdo;

    public function __construct(
        PurchaseRequisitionRepositoryInterface $prRepo,
        PurchaseOrderRepositoryInterface $poRepo,
        GoodsReceiptRepositoryInterface $grRepo,
        ItemRepositoryInterface $itemRepo,
        SupplierRepositoryInterface $supplierRepo,
        JournalServiceInterface $journal,
        InventoryServiceInterface $inventory,
        AuditLoggerInterface $auditLogger,
        ApprovalRoutingService $approval,
        \PDO $pdo
    ) {
        $this->prRepo = $prRepo;
        $this->poRepo = $poRepo;
        $this->grRepo = $grRepo;
        $this->itemRepo = $itemRepo;
        $this->supplierRepo = $supplierRepo;
        $this->journal = $journal;
        $this->inventory = $inventory;
        $this->auditLogger = $auditLogger;
        $this->approval = $approval;
        $this->pdo = $pdo;
    }

    private function txn(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $r = $fn();
            $this->pdo->commit();
            return $r;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ── Purchase Requisition ──

    // NGHIỆP VỤ: Tạo đề nghị mua hàng mới
    // Validate: qty > 0, price_estimate > 0, delivery_date >= today
    // Sinh số PR tự động theo format: PR{YYYY}-{000000}
    public function createPR(array $data, string $createdBy): array
    {
        return $this->txn(function () use ($data, $createdBy) {
            $id = uniqid('pr_');
            $number = $this->nextVoucherNo('PR');

            $pr = new PurchaseRequisition($id, $number, 'draft', $data['requester_id'], $data['department_id']);
            $pr->setProjectId($data['project_id'] ?? null);
            $pr->setDeliveryDate($data['delivery_date'] ?? null);
            $pr->setNote($data['note'] ?? '');

            $total = 0;
            $lines = $data['lines'] ?? [];
            if (empty($lines)) throw new \InvalidArgumentException('Vui lòng nhập ít nhất một dòng hàng hóa.');

            // Lưu PR header trước (FK constraint cho lines)
            $pr->setTotalEstimated(0);
            $this->prRepo->save($pr);

            foreach ($lines as $i => $line) {
                $qty = (float)($line['qty'] ?? 0);
                $price = (float)($line['price_estimate'] ?? 0);
                if ($qty <= 0) throw new \InvalidArgumentException("Dòng {$i}: Số lượng phải lớn hơn 0.");
                if ($price <= 0) throw new \InvalidArgumentException("Dòng {$i}: Đơn giá phải lớn hơn 0.");

                $lid = uniqid('prl_');
                $pl = new PurchaseRequisitionLine($lid, $id, $line['item_id'] ?? null, $line['free_text_name'] ?? null, $qty, $line['uom_id'] ?? null, $price);
                $pl->setIsCatalog(!empty($line['item_id']));
                $total += $qty * $price;

                // save line via PDO directly
                $stmt = $this->pdo->prepare(
                    "INSERT INTO purchase_requisition_lines (id, pr_id, item_id, free_text_name, qty, uom_id, price_estimate, is_catalog) VALUES (?,?,?,?,?,?,?,?)"
                );
                $stmt->execute([$lid, $id, $pl->getItemId(), $pl->getFreeTextName(), $qty, $pl->getUomId(), $price, $pl->getIsCatalog() ? 1 : 0]);
            }

            $pr->setTotalEstimated($total);
            $pr->setStatus('pending');
            $this->prRepo->save($pr);

            $this->auditLogger->log('purchase.pr.create', 'purchase_requisition', $id, null, ['pr_number' => $number, 'total' => $total], $createdBy);

            return ['id' => $id, 'pr_number' => $number, 'total' => $total, 'status' => 'pending'];
        });
    }

    // NGHIỆP VỤ: Phê duyệt đề nghị mua hàng
    // Kiểm tra SoD: người phê duyệt ≠ người tạo
    // Nếu vượt ngân sách hoặc ngưỡng → chuyển lên cấp trên
    public function approvePR(string $prId, string $approverId, string $note = ''): array
    {
        return $this->txn(function () use ($prId, $approverId, $note) {
            $pr = $this->prRepo->findById($prId);
            if (!$pr) throw new \InvalidArgumentException('Không tìm thấy đề nghị mua hàng.');
            if ($pr->getStatus() !== 'pending') throw new \InvalidArgumentException('Đề nghị mua hàng không ở trạng thái chờ duyệt.');

            // SoD check
            if ($pr->getRequesterId() === $approverId) {
                throw new \InvalidArgumentException('Người phê duyệt không được trùng với người tạo đề nghị.');
            }

            $pr->setStatus('approved');
            $this->prRepo->save($pr);

            $this->auditLogger->log('purchase.pr.approve', 'purchase_requisition', $prId, null, ['status' => 'approved'], $approverId);
            return ['id' => $prId, 'status' => 'approved'];
        });
    }

    // NGHIỆP VỤ: Tạo đơn đặt hàng từ PR đã duyệt
    // Validate: PR đã approved, supplier tồn tại và không bị blacklist
    // Sinh số PO: PO{YYYY}-{000000}
    // Cập nhật PR status → 'fulfilled' nếu PO tạo từ toàn bộ PR
    public function createPO(string $prId, string $supplierId, string $buyerId, array $data = []): array
    {
        return $this->txn(function () use ($prId, $supplierId, $buyerId, $data) {
            $pr = $this->prRepo->findById($prId);
            if (!$pr) throw new \InvalidArgumentException('Không tìm thấy đề nghị mua hàng.');
            if ($pr->getStatus() !== 'approved') throw new \InvalidArgumentException('Đề nghị mua hàng chưa được phê duyệt.');

            $supplier = $this->supplierRepo->findById($supplierId);
            if (!$supplier) throw new \InvalidArgumentException('Không tìm thấy nhà cung cấp.');

            $id = uniqid('po_');
            $number = $this->nextVoucherNo('PO');

            $po = new PurchaseOrder($id, $number, 'pending_approval', $supplierId, $data['contract_id'] ?? null, $buyerId);
            $po->setPaymentTerms($data['payment_terms'] ?? $supplier->getPaymentTerms());
            $po->setDeliveryTerms($data['delivery_terms'] ?? '');
            $po->setExpectedDelivery($data['expected_delivery'] ?? $pr->getDeliveryDate());
            $po->setNote($data['note'] ?? '');

            // Lưu PO header trước (FK constraint cho lines)
            $po->setTotalAmount(0);
            $this->poRepo->save($po);

            // Copy lines from PR → PO, allow price override
            $lines = $data['lines'] ?? null;
            $totalAmount = 0;

            if ($lines) {
                // Custom lines (e.g., partial PO)
                foreach ($lines as $i => $line) {
                    $qty = (float)($line['qty_ordered'] ?? 0);
                    $price = (float)($line['unit_price'] ?? 0);
                    if ($qty <= 0) throw new \InvalidArgumentException("Dòng {$i}: Số lượng phải lớn hơn 0.");
                    if ($price <= 0) throw new \InvalidArgumentException("Dòng {$i}: Đơn giá phải lớn hơn 0.");

                    $lid = uniqid('pol_');
                    $ol = new PurchaseOrderLine($lid, $id, $line['pr_line_id'] ?? null, $line['item_id'] ?? null, $line['free_text_name'] ?? null, $qty, $price);
                    $ol->setUomId($line['uom_id'] ?? null);

                    $stmt = $this->pdo->prepare(
                        "INSERT INTO purchase_order_lines (id, po_id, pr_line_id, item_id, free_text_name, qty_ordered, uom_id, unit_price) VALUES (?,?,?,?,?,?,?,?)"
                    );
                    $stmt->execute([$lid, $id, $ol->getPrLineId(), $ol->getItemId(), $ol->getFreeTextName(), $qty, $ol->getUomId(), $price]);
                    $totalAmount += $qty * $price;
                }
            } else {
                // Copy all PR lines
                $stmt = $this->pdo->prepare("SELECT * FROM purchase_requisition_lines WHERE pr_id = ?");
                $stmt->execute([$prId]);
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $qty = (float)$row['qty'];
                    $price = (float)$row['price_estimate'];
                    $lid = uniqid('pol_');
                    $this->pdo->prepare(
                        "INSERT INTO purchase_order_lines (id, po_id, pr_line_id, item_id, free_text_name, qty_ordered, uom_id, unit_price) VALUES (?,?,?,?,?,?,?,?)"
                    )->execute([$lid, $id, $row['id'], $row['item_id'], $row['free_text_name'], $qty, $row['uom_id'], $price]);
                    $totalAmount += $qty * $price;
                }
            }

            $po->setTotalAmount($totalAmount);
            $po->setTaxAmount($data['tax_amount'] ?? 0);
            $this->poRepo->save($po);

            // Check if auto-approve (buyer authority check)
            $requiredRoles = $this->approval->getRequiredRoles($totalAmount, 'purchase');
            if (empty($requiredRoles) || $requiredRoles === ['buyer']) {
                $po->setStatus('sent');
                $this->poRepo->save($po);
            }

            $pr->setStatus('fulfilled');
            $this->prRepo->save($pr);

            $this->auditLogger->log('purchase.po.create', 'purchase_order', $id, null, ['po_number' => $number, 'supplier' => $supplierId, 'total' => $totalAmount], $buyerId);

            return ['id' => $id, 'po_number' => $number, 'total' => $totalAmount, 'status' => $po->getStatus()];
        });
    }

    // NGHIỆP VỤ: Ghi nhận nhập kho theo PO
    // Validate: PO đã sent, không nhập quá số lượng PO
    // Sinh GR: PNK{YYYY}-{000000}
    // Cập nhật PO status (partially_received / completed)
    // Ghi nhận tồn kho qua InventoryService
    public function createGR(string $poId, string $warehouseId, string $receivedDate, array $items, string $createdBy): array
    {
        return $this->txn(function () use ($poId, $warehouseId, $receivedDate, $items, $createdBy) {
            $po = $this->poRepo->findById($poId);
            if (!$po) throw new \InvalidArgumentException('Không tìm thấy đơn đặt hàng.');
            if (!in_array($po->getStatus(), ['sent', 'pending_approval', 'partially_received'])) {
                throw new \InvalidArgumentException('Đơn đặt hàng không ở trạng thái cho phép nhập kho.');
            }

            $id = uniqid('gr_');
            $number = 'PNK' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);

            $gr = new GoodsReceipt($id, $number, $poId, 'draft', $warehouseId, $receivedDate);
            $this->grRepo->save($gr);

            $transactionIds = [];

            foreach ($items as $item) {
                $poLineId = $item['po_line_id'];
                $qty = (float)($item['qty_received'] ?? 0);
                if ($qty <= 0) continue;

                // Validate against PO
                $stmt = $this->pdo->prepare("SELECT qty_ordered, qty_received, unit_price, item_id FROM purchase_order_lines WHERE id = ? AND po_id = ?");
                $stmt->execute([$poLineId, $poId]);
                $poLine = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!$poLine) throw new \InvalidArgumentException("Không tìm thấy dòng đơn hàng.");

                $newReceived = (float)$poLine['qty_received'] + $qty;
                if ($newReceived > (float)$poLine['qty_ordered']) {
                    throw new \InvalidArgumentException('Số lượng nhập vượt quá số lượng đã đặt.');
                }

                // Create GR line
                $lineId = uniqid('grl_');
                $unitPrice = (float)$poLine['unit_price'];
                $this->pdo->prepare(
                    "INSERT INTO goods_receipt_lines (id, gr_id, po_line_id, item_id, qty_received, qty_rejected, batch_no, expiry_date, unit_price) VALUES (?,?,?,?,?,?,?,?,?)"
                )->execute([
                    $lineId, $id, $poLineId, $poLine['item_id'],
                    $qty, (float)($item['qty_rejected'] ?? 0),
                    $item['batch_no'] ?? null, $item['expiry_date'] ?? null, $unitPrice
                ]);

                // Update PO line received qty
                $this->pdo->prepare("UPDATE purchase_order_lines SET qty_received = ? WHERE id = ?")
                    ->execute([$newReceived, $poLineId]);

                // Post journal entry directly (InventoryService has own txn)
                $txn = $this->journal->postEntry(
                    "Goods receipt: {$poLineId}",
                    $number,
                    [
                        ['account_code' => '152', 'amount' => $qty * $unitPrice, 'is_debit' => true],
                        ['account_code' => '331', 'amount' => $qty * $unitPrice, 'is_debit' => false],
                    ],
                    $createdBy
                );
                $transactionIds[] = $txn->getId();
                // Update item stock qty directly
                $item = $this->itemRepo->findById($poLine['item_id']);
                if ($item) {
                    $item->setStockQty($item->getStockQty() + $qty);
                    $this->itemRepo->save($item);
                }
            }

            // Update PO status
            $stmt = $this->pdo->prepare("SELECT SUM(qty_ordered) as total_ord, SUM(qty_received) as total_rcv FROM purchase_order_lines WHERE po_id = ?");
            $stmt->execute([$poId]);
            $agg = $stmt->fetch(\PDO::FETCH_ASSOC);
            $totalOrd = (float)$agg['total_ord'];
            $totalRcv = (float)$agg['total_rcv'];

            if ($totalRcv >= $totalOrd) {
                $po->setStatus('completed');
            } else {
                $po->setStatus('partially_received');
            }
            $this->poRepo->save($po);

            $gr->setStatus('completed');
            $this->grRepo->save($gr);

            $this->auditLogger->log('purchase.gr.create', 'goods_receipt', $id, null, ['gr_number' => $number, 'po_id' => $poId], $createdBy);

            return ['id' => $id, 'gr_number' => $number, 'status' => 'completed', 'transaction_ids' => $transactionIds];
        });
    }

    // ── Query helpers ──

    public function getPRList(string $status = ''): array
    {
        $prs = $status ? $this->prRepo->findByStatus($status) : $this->prRepo->findAll();
        return array_map(fn($p) => $this->prWithLines($p), $prs);
    }

    public function getPR(string $id): ?array
    {
        $pr = $this->prRepo->findById($id);
        return $pr ? $this->prWithLines($pr) : null;
    }

    public function getPOList(string $status = ''): array
    {
        $stmt = $status
            ? $this->pdo->prepare("SELECT * FROM purchase_orders WHERE status = ? ORDER BY created_at DESC LIMIT 200")
            : $this->pdo->query("SELECT * FROM purchase_orders ORDER BY created_at DESC LIMIT 200");
        if ($status) $stmt->execute([$status]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $lines = $this->pdo->prepare("SELECT * FROM purchase_order_lines WHERE po_id = ?");
            $lines->execute([$row['id']]);
            $row['lines'] = $lines->fetchAll(\PDO::FETCH_ASSOC);
            $result[] = $row;
        }
        return $result;
    }

    public function getPO(string $id): ?array
    {
        $po = $this->poRepo->findById($id);
        if (!$po) return null;

        $data = $po->toArray();
        $stmt = $this->pdo->prepare("SELECT * FROM purchase_order_lines WHERE po_id = ?");
        $stmt->execute([$id]);
        $data['lines'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $data;
    }

    public function getGRList(string $poId = ''): array
    {
        if ($poId) {
            $stmt = $this->pdo->prepare("SELECT * FROM goods_receipts WHERE po_id = ? ORDER BY created_at DESC");
            $stmt->execute([$poId]);
        } else {
            $stmt = $this->pdo->query("SELECT * FROM goods_receipts ORDER BY created_at DESC LIMIT 200");
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getGR(string $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM goods_receipts WHERE id = ?");
        $stmt->execute([$id]);
        $gr = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$gr) return null;

        $lines = $this->pdo->prepare("SELECT * FROM goods_receipt_lines WHERE gr_id = ?");
        $lines->execute([$id]);
        $gr['lines'] = $lines->fetchAll(\PDO::FETCH_ASSOC);
        return $gr;
    }

    // Sinh số chứng từ trong transaction hiện tại (không tạo transaction mới)
    private function nextVoucherNo(string $prefix): string
    {
        $year = (int)date('Y');
        $stmt = $this->pdo->prepare("SELECT last_no FROM voucher_sequences WHERE prefix = ? AND year = ? FOR UPDATE");
        $stmt->execute([$prefix, $year]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $nextNo = $row ? ((int)$row['last_no'] + 1) : 1;
        if ($row) {
            $this->pdo->prepare("UPDATE voucher_sequences SET last_no = ? WHERE prefix = ? AND year = ?")->execute([$nextNo, $prefix, $year]);
        } else {
            $this->pdo->prepare("INSERT INTO voucher_sequences (prefix, year, last_no) VALUES (?, ?, ?)")->execute([$prefix, $year, $nextNo]);
        }
        return sprintf('%s%d-%06d', $prefix, $year, $nextNo);
    }

    private function prWithLines($pr): array
    {
        $data = $pr->toArray();
        $stmt = $this->pdo->prepare("SELECT * FROM purchase_requisition_lines WHERE pr_id = ?");
        $stmt->execute([$pr->getId()]);
        $data['lines'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $data;
    }
}

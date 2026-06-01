<?php

declare(strict_types=1);

namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\PurchaseOrder;
use Accounting\Domain\Repository\PurchaseOrderRepositoryInterface;

class PDOPurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?PurchaseOrder
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_orders WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findOneByPoNumber(string $poNumber): ?PurchaseOrder
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_orders WHERE po_number = ?'
        );
        $stmt->execute([$poNumber]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findBySupplier(string $supplierId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_orders WHERE supplier_id = ?'
        );
        $stmt->execute([$supplierId]);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findByStatus(string $status): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_orders WHERE status = ?'
        );
        $stmt->execute([$status]);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM purchase_orders');

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function save(PurchaseOrder $order): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO purchase_orders (id, po_number, status, supplier_id, contract_id, buyer_id, payment_terms, delivery_terms, total_amount, tax_amount, expected_delivery, note, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                po_number = VALUES(po_number),
                status = VALUES(status),
                supplier_id = VALUES(supplier_id),
                contract_id = VALUES(contract_id),
                buyer_id = VALUES(buyer_id),
                payment_terms = VALUES(payment_terms),
                delivery_terms = VALUES(delivery_terms),
                total_amount = VALUES(total_amount),
                tax_amount = VALUES(tax_amount),
                expected_delivery = VALUES(expected_delivery),
                note = VALUES(note),
                updated_at = VALUES(updated_at)'
        );

        $stmt->execute([
            $order->getId(),
            $order->getPoNumber(),
            $order->getStatus(),
            $order->getSupplierId(),
            $order->getContractId(),
            $order->getBuyerId(),
            $order->getPaymentTerms(),
            $order->getDeliveryTerms(),
            $order->getTotalAmount(),
            $order->getTaxAmount(),
            $order->getExpectedDelivery(),
            $order->getNote(),
            $order->getCreatedAt(),
            $order->getUpdatedAt(),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM purchase_orders WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): PurchaseOrder
    {
        return new PurchaseOrder(
            $row['id'],
            $row['po_number'],
            $row['status'],
            $row['supplier_id'],
            $row['contract_id'],
            $row['buyer_id'],
            $row['payment_terms'],
            $row['delivery_terms'],
            $row['total_amount'] !== null ? (float) $row['total_amount'] : null,
            $row['tax_amount'] !== null ? (float) $row['tax_amount'] : null,
            $row['expected_delivery'],
            $row['note'],
            $row['created_at'],
            $row['updated_at']
        );
    }
}

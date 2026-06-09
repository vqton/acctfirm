<?php
declare(strict_types=1);

namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\GoodsReceipt;
use Accounting\Domain\Repository\GoodsReceiptRepositoryInterface;

class PDOGoodsReceiptRepository implements GoodsReceiptRepositoryInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?GoodsReceipt
    {
        $stmt = $this->pdo->prepare('SELECT * FROM goods_receipts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findOneByGrNumber(string $grNumber): ?GoodsReceipt
    {
        $stmt = $this->pdo->prepare('SELECT * FROM goods_receipts WHERE gr_number = ?');
        $stmt->execute([$grNumber]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByPoId(string $poId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM goods_receipts WHERE po_id = ?');
        $stmt->execute([$poId]);
        return array_map(fn($row) => $this->hydrate($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findAll(?string $status = null, int $limit = 50): array
    {
        $sql = 'SELECT * FROM goods_receipts';
        $params = [];
        if ($status) {
            $sql .= ' WHERE status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . (int)$limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(fn($row) => $this->hydrate($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function save(GoodsReceipt $receipt): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO goods_receipts (id, gr_number, po_id, supplier_name, supplier_address,
                receipt_type, status, warehouse_id, received_date, department, note,
                total_amount, amount_in_words, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                gr_number = VALUES(gr_number),
                po_id = VALUES(po_id),
                supplier_name = VALUES(supplier_name),
                supplier_address = VALUES(supplier_address),
                receipt_type = VALUES(receipt_type),
                status = VALUES(status),
                warehouse_id = VALUES(warehouse_id),
                received_date = VALUES(received_date),
                department = VALUES(department),
                note = VALUES(note),
                total_amount = VALUES(total_amount),
                amount_in_words = VALUES(amount_in_words),
                created_by = VALUES(created_by),
                updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            $receipt->getId(),
            $receipt->getGrNumber(),
            $receipt->getPoId(),
            $receipt->getSupplierName(),
            $receipt->getSupplierAddress(),
            $receipt->getReceiptType(),
            $receipt->getStatus(),
            $receipt->getWarehouseId(),
            $receipt->getReceivedDate(),
            $receipt->getDepartment(),
            $receipt->getNote(),
            $receipt->getTotalAmount(),
            $receipt->getAmountInWords(),
            $receipt->getCreatedBy(),
            $receipt->getCreatedAt(),
            $receipt->getUpdatedAt(),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM goods_receipts WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): GoodsReceipt
    {
        return new GoodsReceipt(
            $row['id'], $row['gr_number'], $row['po_id'],
            $row['supplier_name'] ?? null, $row['supplier_address'] ?? null,
            $row['receipt_type'] ?? 'purchase',
            $row['status'], $row['warehouse_id'], $row['received_date'],
            $row['department'] ?? null, $row['note'] ?? null,
            isset($row['total_amount']) ? (float)$row['total_amount'] : null,
            $row['amount_in_words'] ?? null,
            $row['created_by'] ?? null, $row['created_at'] ?? null,
            $row['updated_at'] ?? null
        );
    }
}

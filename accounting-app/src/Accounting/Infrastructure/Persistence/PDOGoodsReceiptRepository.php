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
        $stmt = $this->pdo->prepare(
            'SELECT * FROM goods_receipts WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findOneByGrNumber(string $grNumber): ?GoodsReceipt
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM goods_receipts WHERE gr_number = ?'
        );
        $stmt->execute([$grNumber]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByPoId(string $poId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM goods_receipts WHERE po_id = ?'
        );
        $stmt->execute([$poId]);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM goods_receipts');

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function save(GoodsReceipt $receipt): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO goods_receipts (id, gr_number, po_id, status, warehouse_id, received_date, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                gr_number = VALUES(gr_number),
                po_id = VALUES(po_id),
                status = VALUES(status),
                warehouse_id = VALUES(warehouse_id),
                received_date = VALUES(received_date),
                note = VALUES(note)'
        );

        $stmt->execute([
            $receipt->getId(),
            $receipt->getGrNumber(),
            $receipt->getPoId(),
            $receipt->getStatus(),
            $receipt->getWarehouseId(),
            $receipt->getReceivedDate(),
            $receipt->getNote(),
            $receipt->getCreatedAt(),
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
            $row['id'],
            $row['gr_number'],
            $row['po_id'],
            $row['status'],
            $row['warehouse_id'],
            $row['received_date'],
            $row['note'],
            $row['created_at']
        );
    }
}

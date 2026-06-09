<?php
declare(strict_types=1);

namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\GoodsReceiptLine;
use Accounting\Domain\Repository\GoodsReceiptLineRepositoryInterface;

class PDOGoodsReceiptLineRepository implements GoodsReceiptLineRepositoryInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByGrId(string $grId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM goods_receipt_lines WHERE gr_id = ? ORDER BY id');
        $stmt->execute([$grId]);
        return array_map(fn($row) => $this->hydrate($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function save(GoodsReceiptLine $line): void
    {
        // total = GENERATED ALWAYS AS (qty_received * unit_price) STORED — không insert
        $stmt = $this->pdo->prepare(
            'INSERT INTO goods_receipt_lines (id, gr_id, po_line_id, item_id, qty_received,
                qty_rejected, batch_no, expiry_date, unit_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                gr_id = VALUES(gr_id),
                po_line_id = VALUES(po_line_id),
                item_id = VALUES(item_id),
                qty_received = VALUES(qty_received),
                qty_rejected = VALUES(qty_rejected),
                batch_no = VALUES(batch_no),
                expiry_date = VALUES(expiry_date),
                unit_price = VALUES(unit_price)'
        );
        $stmt->execute([
            $line->getId(),
            $line->getGrId(),
            $line->getPoLineId(),
            $line->getItemId(),
            $line->getQtyReceived(),
            $line->getQtyRejected(),
            $line->getBatchNo(),
            $line->getExpiryDate(),
            $line->getUnitPrice(),
        ]);
    }

    public function deleteByGrId(string $grId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM goods_receipt_lines WHERE gr_id = ?');
        $stmt->execute([$grId]);
    }

    private function hydrate(array $row): GoodsReceiptLine
    {
        // Lấy thông tin item name/code/uom từ items table nếu có
        $itemName = null; $itemCode = null; $uom = null;
        if ($row['item_id'] ?? null) {
            $istmt = $this->pdo->prepare('SELECT name, code, unit AS uom FROM items WHERE id = ?');
            $istmt->execute([$row['item_id']]);
            $item = $istmt->fetch(\PDO::FETCH_ASSOC);
            if ($item) {
                $itemName = $item['name'];
                $itemCode = $item['code'];
                $uom = $item['uom'] ?? null;
            }
        }
        return new GoodsReceiptLine(
            $row['id'], $row['gr_id'], $row['po_line_id'] ?? null,
            $row['item_id'] ?? null, $itemName, $itemCode, $uom,
            (float)$row['qty_received'], isset($row['qty_rejected']) ? (float)$row['qty_rejected'] : null,
            $row['batch_no'] ?? null, $row['expiry_date'] ?? null,
            isset($row['unit_price']) ? (float)$row['unit_price'] : null,
            isset($row['total']) ? (float)$row['total'] : null,
            0
        );
    }
}

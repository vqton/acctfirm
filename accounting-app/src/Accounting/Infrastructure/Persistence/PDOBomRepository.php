<?php
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\Bom;
use Accounting\Domain\Repository\BomRepositoryInterface;
use PDO;

class PDOBomRepository implements BomRepositoryInterface
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?Bom
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bom WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return $this->hydrate($row);
    }

    public function findByProduct(string $productId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bom WHERE product_id = ? ORDER BY version DESC');
        $stmt->execute([$productId]);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $items[] = $this->hydrate($row);
        return $items;
    }

    public function findActiveByProduct(string $productId): ?Bom
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bom WHERE product_id = ? AND status = "active" ORDER BY version DESC LIMIT 1');
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $items = [];
        foreach ($this->pdo->query('SELECT * FROM bom ORDER BY created_at DESC') as $row) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(Bom $bom): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO bom (id,product_id,version,status,effective_date,notes,created_by,created_at)
             VALUES (?,?,?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE status=VALUES(status),notes=VALUES(notes)'
        );
        $stmt->execute([$bom->getId(), $bom->getProductId(), $bom->getVersion(),
            $bom->getStatus(), $bom->getEffectiveDate(), $bom->getNotes(), $bom->getCreatedBy()]);

        $this->pdo->prepare('DELETE FROM bom_lines WHERE bom_id = ?')->execute([$bom->getId()]);
        $ins = $this->pdo->prepare('INSERT INTO bom_lines (id,bom_id,material_id,qty_per_unit,wastage_pct,unit) VALUES (?,?,?,?,?,?)');
        foreach ($bom->getLines() as $line) {
            $ins->execute([$line['id'], $bom->getId(), $line['material_id'],
                $line['qty_per_unit'], $line['wastage_pct'] ?? 0, $line['unit']]);
        }
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM bom WHERE id = ?')->execute([$id]);
    }

    private function hydrate(array $row): Bom
    {
        $b = new Bom($row['id'], $row['product_id'], (int)$row['version'],
            $row['effective_date'], $row['notes'] ?? null, $row['created_by'] ?? null);
        $b->setStatus($row['status']);

        $stmt = $this->pdo->prepare('SELECT * FROM bom_lines WHERE bom_id = ?');
        $stmt->execute([$row['id']]);
        $lines = [];
        while ($l = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lines[] = ['id' => $l['id'], 'material_id' => $l['material_id'],
                'qty_per_unit' => (float)$l['qty_per_unit'],
                'wastage_pct' => (float)$l['wastage_pct'], 'unit' => $l['unit']];
        }
        $b->setLines($lines);
        return $b;
    }
}

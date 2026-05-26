<?php
// Quản lý dữ liệu: danh mục đơn vị tính
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\Uom;
use Accounting\Domain\Repository\UomRepositoryInterface;
use PDO;

class PDOUomRepository implements UomRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?Uom
    {
        $stmt = $this->pdo->prepare('SELECT * FROM uoms WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?Uom
    {
        $stmt = $this->pdo->prepare('SELECT * FROM uoms WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM uoms ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(Uom $uom): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO uoms (id, code, name, status, created_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name), status=VALUES(status)'
        );
        $stmt->execute([
            $uom->getId(), $uom->getCode(), $uom->getName(),
            $uom->isStatus() ? 1 : 0, $uom->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM uoms WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): Uom
    {
        $uom = new Uom($row['id'], $row['code'], $row['name']);
        $uom->setStatus((bool)$row['status']);
        return $uom;
    }
}

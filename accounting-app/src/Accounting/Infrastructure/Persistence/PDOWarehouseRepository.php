<?php
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\Warehouse;
use Accounting\Domain\Repository\WarehouseRepositoryInterface;
use PDO;

class PDOWarehouseRepository implements WarehouseRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?Warehouse
    {
        $stmt = $this->pdo->prepare('SELECT * FROM warehouses WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?Warehouse
    {
        $stmt = $this->pdo->prepare('SELECT * FROM warehouses WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM warehouses ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function save(Warehouse $w): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO warehouses (id, code, name, address, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name), address=VALUES(address), status=VALUES(status)'
        );
        $stmt->execute([
            $w->getId(), $w->getCode(), $w->getName(), $w->getAddress(),
            $w->isStatus() ? 1 : 0, $w->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM warehouses WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): Warehouse
    {
        $w = new Warehouse($row['id'], $row['code'], $row['name'], $row['address']);
        $w->setStatus((bool)$row['status']);
        return $w;
    }
}
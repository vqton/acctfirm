<?php
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\Department;
use Accounting\Domain\Repository\DepartmentRepositoryInterface;
use PDO;

class PDODepartmentRepository implements DepartmentRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?Department
    {
        $stmt = $this->pdo->prepare('SELECT * FROM departments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?Department
    {
        $stmt = $this->pdo->prepare('SELECT * FROM departments WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM departments ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function save(Department $d): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO departments (id, code, name, parent_id, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name), parent_id=VALUES(parent_id), status=VALUES(status)'
        );
        $stmt->execute([
            $d->getId(), $d->getCode(), $d->getName(), $d->getParentId(),
            $d->isStatus() ? 1 : 0, $d->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM departments WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): Department
    {
        $d = new Department($row['id'], $row['code'], $row['name'], $row['parent_id']);
        $d->setStatus((bool)$row['status']);
        return $d;
    }
}
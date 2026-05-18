<?php
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\Ccdc;
use Accounting\Domain\Repository\CcdcRepositoryInterface;
use PDO;

class PDOCcdcRepository implements CcdcRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?Ccdc
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ccdc WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?Ccdc
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ccdc WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM ccdc ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(Ccdc $ccdc): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ccdc (id, code, name, unit, quantity, allocation_type, total_cost, allocated, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name), unit=VALUES(unit),
             quantity=VALUES(quantity), allocation_type=VALUES(allocation_type), total_cost=VALUES(total_cost),
             allocated=VALUES(allocated), status=VALUES(status)'
        );
        $stmt->execute([
            $ccdc->getId(), $ccdc->getCode(), $ccdc->getName(), $ccdc->getUnit(),
            $ccdc->getQuantity(), $ccdc->getAllocationType(), $ccdc->getTotalCost(),
            $ccdc->getAllocated(), $ccdc->isStatus() ? 1 : 0,
            $ccdc->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM ccdc WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): Ccdc
    {
        $ccdc = new Ccdc(
            $row['id'], $row['code'], $row['name'], $row['unit'],
            (float)$row['quantity'], $row['allocation_type'], (float)$row['total_cost'],
            (float)$row['allocated']
        );
        $ccdc->setStatus((bool)$row['status']);
        return $ccdc;
    }
}

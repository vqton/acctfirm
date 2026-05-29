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

    public function findPendingAllocation(int $limit = 100): array
    {
        $limit = (int)$limit;
        $stmt = $this->pdo->prepare(
            "SELECT * FROM ccdc WHERE allocation_type = 'period' AND remaining_months > 0 AND status = 1 ORDER BY allocation_start_date ASC LIMIT {$limit}"
        );
        $stmt->execute();
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(Ccdc $ccdc): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ccdc (id, code, name, unit, quantity, allocation_type, allocation_months, expense_account, allocation_start_date, total_cost, allocated, remaining_months, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name), unit=VALUES(unit),
             quantity=VALUES(quantity), allocation_type=VALUES(allocation_type),
             allocation_months=VALUES(allocation_months), expense_account=VALUES(expense_account),
             allocation_start_date=VALUES(allocation_start_date), total_cost=VALUES(total_cost),
             allocated=VALUES(allocated), remaining_months=VALUES(remaining_months), status=VALUES(status)'
        );
        $stmt->execute([
            $ccdc->getId(), $ccdc->getCode(), $ccdc->getName(), $ccdc->getUnit(),
            $ccdc->getQuantity(), $ccdc->getAllocationType(), $ccdc->getAllocationMonths(),
            $ccdc->getExpenseAccount(), $ccdc->getAllocationStartDate(),
            $ccdc->getTotalCost(), $ccdc->getAllocated(), $ccdc->getRemainingMonths(),
            $ccdc->isStatus() ? 1 : 0,
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
            (float)$row['quantity'], $row['allocation_type'],
            (int)($row['allocation_months'] ?? 0),
            $row['expense_account'] ?? '642',
            $row['allocation_start_date'] ?? null,
            (float)$row['total_cost'], (float)$row['allocated'],
            (int)($row['remaining_months'] ?? 0)
        );
        $ccdc->setStatus((bool)$row['status']);
        return $ccdc;
    }
}

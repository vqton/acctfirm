<?php

declare(strict_types=1);

namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\PurchaseBudget;
use Accounting\Domain\Repository\PurchaseBudgetRepositoryInterface;

class PDOPurchaseBudgetRepository implements PurchaseBudgetRepositoryInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?PurchaseBudget
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_budgets WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByDepartment(string $departmentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_budgets WHERE department_id = ?'
        );
        $stmt->execute([$departmentId]);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findOneByDeptPeriod(string $departmentId, string $period): ?PurchaseBudget
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_budgets WHERE department_id = ? AND period = ?'
        );
        $stmt->execute([$departmentId, $period]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM purchase_budgets');

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function save(PurchaseBudget $budget): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO purchase_budgets (id, department_id, period, budget_amount, committed_amount, actual_amount)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                department_id = VALUES(department_id),
                period = VALUES(period),
                budget_amount = VALUES(budget_amount),
                committed_amount = VALUES(committed_amount),
                actual_amount = VALUES(actual_amount)'
        );

        $stmt->execute([
            $budget->getId(),
            $budget->getDepartmentId(),
            $budget->getPeriod(),
            $budget->getBudgetAmount(),
            $budget->getCommittedAmount(),
            $budget->getActualAmount(),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM purchase_budgets WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): PurchaseBudget
    {
        return new PurchaseBudget(
            $row['id'],
            $row['department_id'],
            $row['period'],
            $row['budget_amount'] !== null ? (float) $row['budget_amount'] : null,
            $row['committed_amount'] !== null ? (float) $row['committed_amount'] : null,
            $row['actual_amount'] !== null ? (float) $row['actual_amount'] : null
        );
    }
}

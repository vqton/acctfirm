<?php
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\Project;
use Accounting\Domain\Repository\ProjectRepositoryInterface;
use PDO;

class PDOProjectRepository implements ProjectRepositoryInterface
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?Project
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projects WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?Project
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projects WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM projects ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $items[] = $this->hydrate($row);
        return $items;
    }

    public function save(Project $project): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO projects (id,code,name,customer_id,manager_id,start_date,end_date,budget,actual_cost,billed_amount,revenue_recognized,estimated_completion_pct,status,notes,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE code=VALUES(code),name=VALUES(name),
             customer_id=VALUES(customer_id),manager_id=VALUES(manager_id),
             start_date=VALUES(start_date),end_date=VALUES(end_date),
             budget=VALUES(budget),actual_cost=VALUES(actual_cost),
             billed_amount=VALUES(billed_amount),revenue_recognized=VALUES(revenue_recognized),
             estimated_completion_pct=VALUES(estimated_completion_pct),
             status=VALUES(status),notes=VALUES(notes)'
        );
        $stmt->execute([
            $project->getId(), $project->getCode(), $project->getName(),
            $project->getCustomerId(), $project->getManagerId(),
            $project->getStartDate(), $project->getEndDate(), $project->getBudget(),
            $project->getActualCost(), $project->getBilledAmount(),
            $project->getRevenueRecognized(), $project->getEstimatedCompletionPct(),
            $project->getStatus(), $project->getNotes(),
            $project->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM projects WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function getCostSummary(string $projectId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.type, a.code, a.name,
                   SUM(CASE WHEN le.is_debit=1 THEN le.amount ELSE 0 END) as debit,
                   SUM(CASE WHEN le.is_debit=0 THEN le.amount ELSE 0 END) as credit
            FROM ledger_entries le
            JOIN accounts a ON le.account_id = a.id
            WHERE le.project_id = ?
            GROUP BY a.type, a.code, a.name
            ORDER BY a.code
        ");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProjectTransactions(string $projectId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $sql = "SELECT t.* FROM transactions t
                JOIN ledger_entries le ON le.transaction_id = t.id
                WHERE le.project_id = ?";
        $params = [$projectId];
        if ($fromDate) { $sql .= ' AND t.transaction_date >= ?'; $params[] = $fromDate; }
        if ($toDate) { $sql .= ' AND t.transaction_date <= ?'; $params[] = $toDate; }
        $sql .= ' GROUP BY t.id ORDER BY t.transaction_date DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProgressBillings(string $projectId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM project_progress_billing WHERE project_id = ? ORDER BY billing_date'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProjectBudgets(string $projectId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM project_budgets WHERE project_id = ? ORDER BY account_code'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function hydrate(array $row): Project
    {
        $p = new Project(
            $row['id'], $row['code'], $row['name'], $row['customer_id'],
            $row['start_date'], $row['end_date'] ?? null, (float)($row['budget'] ?? 0),
            $row['notes'] ?? null, $row['manager_id'] ?? null
        );
        $p->setActualCost((float)($row['actual_cost'] ?? 0));
        $p->setBilledAmount((float)($row['billed_amount'] ?? 0));
        $p->setRevenueRecognized((float)($row['revenue_recognized'] ?? 0));
        $p->setEstimatedCompletionPct((float)($row['estimated_completion_pct'] ?? 0));
        $p->setStatus($row['status'] ?? 'active');
        return $p;
    }
}

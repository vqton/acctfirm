<?php
declare(strict_types=1);
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\ProjectRepositoryInterface;
use PDO;

class ProjectAccountingService
{
    private ProjectRepositoryInterface $projectRepo;
    private PDO $pdo;
    private ReportExportService $export;

    public function __construct(ProjectRepositoryInterface $projectRepo, PDO $pdo, ReportExportService $export)
    {
        $this->projectRepo = $projectRepo;
        $this->pdo = $pdo;
        $this->export = $export;
    }

    public function getDashboardStats(): array
    {
        return $this->pdo->query("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
                   SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
                   COALESCE(SUM(budget),0) as total_budget,
                   COALESCE(SUM(actual_cost),0) as total_cost,
                   COALESCE(SUM(billed_amount),0) as total_billed
            FROM projects
        ")->fetch(PDO::FETCH_ASSOC);
    }

    public function getProjectReport(string $projectId): array
    {
        $project = $this->projectRepo->findById($projectId);
        if (!$project) throw new \InvalidArgumentException('Không tìm thấy dự án');

        [$actualDebit, $actualCredit] = $this->getActualTotals($projectId);
        return [
            'project' => $project->toArray(),
            'actual_debit' => $actualDebit,
            'actual_credit' => $actualCredit,
            'variance' => $project->getBudget() - $actualDebit,
            'completion_pct' => $project->getBudget() > 0
                ? round($actualDebit / $project->getBudget() * 100, 2) : 0,
            'cost_summary' => $this->projectRepo->getCostSummary($projectId),
            'transactions' => $this->projectRepo->getProjectTransactions($projectId),
            'billings' => $this->projectRepo->getProgressBillings($projectId),
        ];
    }

    public function allocateCost(string $projectId, string $transactionId, string $accountCode, float $amount, bool $isDebit): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE ledger_entries le
                 JOIN accounts a ON le.account_id = a.id
                 SET le.project_id = ?
                 WHERE le.transaction_id = ? AND a.code = ? AND le.is_debit = ? AND le.amount = ?"
            );
            $stmt->execute([$projectId, $transactionId, $accountCode, $isDebit ? 1 : 0, $amount]);

            if ($stmt->rowCount() === 0) throw new \InvalidArgumentException('Không tìm thấy bút toán phù hợp để phân bổ');

            if ($isDebit) {
                $this->pdo->prepare('UPDATE projects SET actual_cost = actual_cost + ? WHERE id = ?')
                    ->execute([$amount, $projectId]);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function createProgressBilling(string $projectId, string $billingDate, float $amount, float $pctComplete, string $description, string $createdBy): string
    {
        $id = uniqid('bill_');
        $stmt = $this->pdo->prepare(
            'INSERT INTO project_progress_billing (id,project_id,billing_date,amount,pct_complete,description,status,created_by,created_at)
             VALUES (?,?,?,?,?,?,"draft",?,NOW())'
        );
        $stmt->execute([$id, $projectId, $billingDate, $amount, $pctComplete, $description, $createdBy]);
        return $id;
    }

    public function recognizeRevenue(string $projectId, string $userId): float
    {
        $project = $this->projectRepo->findById($projectId);
        if (!$project) throw new \InvalidArgumentException('Không tìm thấy dự án');

        $totalCost = $this->getActualTotals($projectId)[0];
        $pct = $project->getBudget() > 0 ? $totalCost / $project->getBudget() : 0;
        $revenue = round($project->getBudget() * min($pct, 1.0), 2);

        $this->pdo->prepare('UPDATE projects SET revenue_recognized = ?, estimated_completion_pct = ? WHERE id = ?')
            ->execute([$revenue, round($pct * 100, 2), $projectId]);

        return $revenue;
    }

    public function finalizeProject(string $projectId): void
    {
        $project = $this->projectRepo->findById($projectId);
        if (!$project) throw new \InvalidArgumentException('Không tìm thấy dự án');
        if ($project->getStatus() !== 'active') throw new \InvalidArgumentException('Chỉ có thể kết thúc dự án đang hoạt động');

        $this->pdo->prepare("UPDATE projects SET status='completed', estimated_completion_pct=100 WHERE id=?")
            ->execute([$projectId]);
    }

    public function setBudgetLine(string $projectId, string $accountCode, float $amount, ?string $notes = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO project_budgets (project_id,account_code,budget_amount,notes,created_at)
             VALUES (?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE budget_amount=VALUES(budget_amount),notes=VALUES(notes)'
        );
        $stmt->execute([$projectId, $accountCode, $amount, $notes]);
    }

    public function updateBudgetSpent(string $projectId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE project_budgets pb
            JOIN (
                SELECT a.code, SUM(le.amount) as spent
                FROM ledger_entries le
                JOIN accounts a ON le.account_id = a.id
                WHERE le.project_id = ? AND le.is_debit = 1
                GROUP BY a.code
            ) s ON pb.account_code = s.code
            SET pb.spent_amount = s.spent
            WHERE pb.project_id = ?
        ");
        $stmt->execute([$projectId, $projectId]);
    }

    public function getActiveProjectsList(): array
    {
        $stmt = $this->pdo->query("
            SELECT p.*, c.name as customer_name,
                   COALESCE(SUM(le.amount),0) as actual_cost
            FROM projects p
            LEFT JOIN customers c ON p.customer_id = c.id
            LEFT JOIN ledger_entries le ON le.project_id = p.id AND le.is_debit = 1
            WHERE p.status = 'active'
            GROUP BY p.id
            ORDER BY p.code
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function exportProjectReport(string $format, string $projectId): array
    {
        $report = $this->getProjectReport($projectId);
        $p = $report['project'];
        $headers = ['Hạng mục', 'Giá trị'];
        $data = [
            ['Mã dự án', $p['code']], ['Tên', $p['name']],
            ['Ngân sách', $p['budget']], ['Chi phí thực tế', $p['actual_cost']],
            ['Đã xuất hóa đơn', $p['billed_amount']],
            ['Doanh thu đã ghi nhận', $p['revenue_recognized']],
            ['Tỷ lệ hoàn thành', $p['estimated_completion_pct'] . '%'],
        ];
        foreach ($report['cost_summary'] as $c) {
            $data[] = ['TK ' . $c['code'] . ' ' . $c['name'], $c['debit'] - $c['credit']];
        }
        return $this->export->exportCsv($headers, $data, 'du_an_' . $p['code'] . '_' . date('Ymd') . '.csv');
    }

    private function getActualTotals(string $projectId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT SUM(CASE WHEN is_debit=1 THEN amount ELSE 0 END) as debit,
                   SUM(CASE WHEN is_debit=0 THEN amount ELSE 0 END) as credit
            FROM ledger_entries WHERE project_id = ?
        ");
        $stmt->execute([$projectId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return [(float)$r['debit'], (float)$r['credit']];
    }
}

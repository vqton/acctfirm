<?php
declare(strict_types=1);
namespace Accounting\Domain\Service;

use PDO;

class BudgetService
{
    private PDO $pdo;
    private ReportExportService $export;

    public function __construct(PDO $pdo, ReportExportService $export)
    {
        $this->pdo = $pdo;
        $this->export = $export;
    }

    public function createScenario(string $name, int $year, string $type = 'operating', ?string $notes = null, ?string $createdBy = null): array
    {
        $id = uniqid('bsc_');
        $stmt = $this->pdo->prepare(
            'INSERT INTO budget_scenarios (id,name,year,type,status,notes,created_by,created_at) VALUES (?,?,?,?,"draft",?,?,NOW())'
        );
        $stmt->execute([$id, $name, $year, $type, $notes, $createdBy]);
        return ['id' => $id, 'name' => $name, 'year' => $year];
    }

    public function activateScenario(string $id): void
    {
        $this->pdo->prepare("UPDATE budget_scenarios SET status='active' WHERE id=?")->execute([$id]);
    }

    public function getScenarios(int $year): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM budget_scenarios WHERE year = ? ORDER BY created_at DESC');
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setBudget(string $scenarioId, string $periodCode, string $accountCode, float $amount, ?string $notes = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO budget_plans (scenario_id,period_code,account_code,budget_amount,notes,created_at)
             VALUES (?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE budget_amount=VALUES(budget_amount),notes=VALUES(notes)'
        );
        $stmt->execute([$scenarioId, $periodCode, $accountCode, $amount, $notes]);
    }

    public function getBudgetLines(string $scenarioId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM budget_plans WHERE scenario_id = ? ORDER BY period_code, account_code');
        $stmt->execute([$scenarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getVarianceReport(string $scenarioId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT bp.period_code, bp.account_code, a.name as account_name,
                   bp.budget_amount,
                   COALESCE(SUM(CASE WHEN le.is_debit=1 THEN le.amount ELSE 0 END), 0) as actual_debit,
                   COALESCE(SUM(CASE WHEN le.is_debit=0 THEN le.amount ELSE 0 END), 0) as actual_credit,
                   (bp.budget_amount - COALESCE(SUM(CASE WHEN le.is_debit=1 THEN le.amount ELSE 0 END), 0)) as variance
            FROM budget_plans bp
            JOIN accounts a ON bp.account_code = a.code
            LEFT JOIN ledger_entries le ON le.account_id = a.id
                AND DATE_FORMAT(le.created_at, '%Y-%m') = bp.period_code
            WHERE bp.scenario_id = ?
            GROUP BY bp.period_code, bp.account_code, a.name, bp.budget_amount
            ORDER BY bp.period_code, bp.account_code
        ");
        $stmt->execute([$scenarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSummary(string $scenarioId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total_lines,
                   COALESCE(SUM(budget_amount),0) as total_budget,
                   COALESCE(SUM(CASE WHEN a.type IN ('revenue','income') THEN budget_amount ELSE 0 END),0) as total_revenue_budget,
                   COALESCE(SUM(CASE WHEN a.type IN ('expense','cost') THEN budget_amount ELSE 0 END),0) as total_expense_budget
            FROM budget_plans bp
            JOIN accounts a ON bp.account_code = a.code
            WHERE bp.scenario_id = ?
        ");
        $stmt->execute([$scenarioId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getDashboard(int $year): array
    {
        $scenarios = $this->getScenarios($year);
        $stmt = $this->pdo->prepare("
            SELECT bp.period_code,
                   SUM(bp.budget_amount) as budget,
                   COALESCE(SUM(le.amount),0) as actual
            FROM budget_plans bp
            JOIN budget_scenarios bs ON bp.scenario_id = bs.id
            LEFT JOIN accounts a ON bp.account_code = a.code
            LEFT JOIN ledger_entries le ON le.account_id = a.id
                AND DATE_FORMAT(le.created_at, '%Y-%m') = bp.period_code
                AND le.is_debit = 1
            WHERE bs.year = ? AND bs.status = 'active'
            GROUP BY bp.period_code
            ORDER BY bp.period_code
        ");
        $stmt->execute([$year]);
        return [
            'scenarios' => $scenarios,
            'summary_by_month' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function exportVarianceReport(string $scenarioId): array
    {
        $report = $this->getVarianceReport($scenarioId);
        $headers = ['Kỳ', 'TK', 'Diễn giải', 'Dự toán', 'Thực tế Nợ', 'Thực tế Có', 'Chênh lệch'];
        $data = [];
        foreach ($report as $r) {
            $data[] = [$r['period_code'], $r['account_code'], $r['account_name'],
                $r['budget_amount'], $r['actual_debit'], $r['actual_credit'], $r['variance']];
        }
        return $this->export->exportCsv($headers, $data, 'ngan_sach_' . date('Ymd') . '.csv');
    }
}

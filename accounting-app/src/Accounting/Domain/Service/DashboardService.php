<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;
use PDO;

class DashboardService
{
    public function __construct(
        private AccountRepositoryInterface $accountRepo,
        private TransactionRepositoryInterface $txnRepo,
        private PDO $pdo,
    ) {}

    public function getKPIs(): array
    {
        $today = date('Y-m-d');

        // === TIỀN ===
        $cashBalance = $this->getAccountBalance('111');
        $bankBalance = $this->getAccountBalance('112');

        // === THU/CHI HÔM NAY ===
        $todayStmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN a.code LIKE '111%' OR a.code LIKE '112%' THEN le.amount ELSE 0 END), 0) AS total
            FROM ledger_entries le
            JOIN accounts a ON a.id = le.account_id
            JOIN transactions t ON t.id = le.transaction_id AND t.status = 'posted' AND t.deleted_at IS NULL
            WHERE DATE(t.transaction_date) = ? AND le.is_debit = ?
        ");
        $todayStmt->execute([$today, 1]);
        $todayReceipts = (float)$todayStmt->fetchColumn();
        $todayStmt->execute([$today, 0]);
        $todayPayments = (float)$todayStmt->fetchColumn();

        // === DOANH THU/CHI PHÍ YTD ===
        $ytdStart = date('Y-01-01');
        $pnlStmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN a.code LIKE '5%' THEN le.amount ELSE 0 END), 0) AS revenue,
                COALESCE(SUM(CASE WHEN a.code LIKE '6%' OR a.code = '635' OR a.code LIKE '8%' THEN le.amount ELSE 0 END), 0) AS expense
            FROM ledger_entries le
            JOIN accounts a ON a.id = le.account_id
            JOIN transactions t ON t.id = le.transaction_id AND t.status = 'posted' AND t.deleted_at IS NULL
            WHERE t.transaction_date >= ? AND le.is_debit = 1
        ");
        $pnlStmt->execute([$ytdStart]);
        $pnl = $pnlStmt->fetch(PDO::FETCH_ASSOC);
        $revenue = (float)$pnl['revenue'];
        $expense = (float)$pnl['expense'];

        // === GIAO DỊCH CHỜ DUYỆT ===
        $pending = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM transactions WHERE status = 'submitted' AND deleted_at IS NULL"
        )->fetchColumn();

        // === TRẠNG THÁI GIAO DỊCH ===
        $statusStmt = $this->pdo->query("
            SELECT status, COUNT(*) AS cnt FROM transactions
            WHERE deleted_at IS NULL GROUP BY status
        ");
        $statusCounts = [];
        $totalTxns = 0;
        while ($r = $statusStmt->fetch(PDO::FETCH_ASSOC)) {
            $statusCounts[$r['status']] = (int)$r['cnt'];
            $totalTxns += (int)$r['cnt'];
        }

        // === DÒNG TIỀN 7 NGÀY ===
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $stmt = $this->pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE 0 END), 0) AS receipts,
                    COALESCE(SUM(CASE WHEN le.is_debit = 0 THEN le.amount ELSE 0 END), 0) AS payments
                FROM ledger_entries le
                JOIN accounts a ON a.id = le.account_id AND (a.code LIKE '111%' OR a.code LIKE '112%')
                JOIN transactions t ON t.id = le.transaction_id AND t.status = 'posted' AND t.deleted_at IS NULL
                WHERE DATE(t.transaction_date) = ?
            ");
            $stmt->execute([$d]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $trend[] = [
                'date' => $d,
                'receipts' => (float)$row['receipts'],
                'payments' => (float)$row['payments'],
            ];
        }

        // === KỲ HIỆN TẠI ===
        $period = $this->pdo->query(
            "SELECT * FROM accounting_periods WHERE status = 'open' ORDER BY start_date DESC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);

        // === GIAO DỊCH GẦN ĐÂY ===
        $rStmt = $this->pdo->query("
            SELECT id, reference, transaction_date, description, status, created_by
            FROM transactions WHERE deleted_at IS NULL
            ORDER BY created_at DESC LIMIT 5
        ");
        $recent = $rStmt->fetchAll(PDO::FETCH_ASSOC);

        // === TRIAL BALANCE CHECK ===
        $tb = $this->pdo->query("
            SELECT
                COALESCE(SUM(CASE WHEN is_debit = 1 THEN amount ELSE 0 END), 0) AS total_dr,
                COALESCE(SUM(CASE WHEN is_debit = 0 THEN amount ELSE 0 END), 0) AS total_cr
            FROM ledger_entries le
            JOIN transactions t ON t.id = le.transaction_id AND t.status IN ('posted','reversed') AND t.deleted_at IS NULL
        ")->fetch(PDO::FETCH_ASSOC);
        $balanced = abs((float)$tb['total_dr'] - (float)$tb['total_cr']) < 0.01;

        return [
            'cash_balance' => $cashBalance,
            'bank_balance' => $bankBalance,
            'total_cash' => $cashBalance + $bankBalance,
            'today_receipts' => $todayReceipts,
            'today_payments' => $todayPayments,
            'revenue_ytd' => $revenue,
            'expense_ytd' => $expense,
            'profit_ytd' => $revenue - $expense,
            'pending_approvals' => $pending,
            'total_transactions' => $totalTxns,
            'status_breakdown' => $statusCounts,
            'trend' => $trend,
            'current_period' => $period ? [
                'name' => $period['name'],
                'code' => $period['period_code'],
                'start_date' => $period['start_date'],
                'end_date' => $period['end_date'],
                'status' => $period['status'],
                'deadline' => $period['deadline'] ?? null,
            ] : null,
            'recent_transactions' => $recent,
            'trial_balance' => [
                'total_dr' => (float)$tb['total_dr'],
                'total_cr' => (float)$tb['total_cr'],
                'balanced' => $balanced,
            ],
        ];
    }

    private function getAccountBalance(string $code): float
    {
        $accounts = $this->accountRepo->findAll();
        $balance = 0.0;
        foreach ($accounts as $a) {
            if (str_starts_with($a->getCode(), $code)) {
                $balance += $a->getBalance();
            }
        }
        return $balance;
    }
}

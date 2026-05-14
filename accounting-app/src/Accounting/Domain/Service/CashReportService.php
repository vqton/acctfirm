<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\AccountRepositoryInterface;

class CashReportService
{
    private \PDO $pdo;
    private AccountRepositoryInterface $accountRepo;

    public function __construct(\PDO $pdo, AccountRepositoryInterface $accountRepo)
    {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
    }

    public function getCashPosition(): array
    {
        $cash = $this->accountRepo->findByCode('111');
        $bank = $this->accountRepo->findByCode('112');
        $transit = $this->accountRepo->findByCode('113');

        $cashBal = $cash ? $cash->getBalance() : 0;
        $bankBal = $bank ? $bank->getBalance() : 0;
        $transitBal = $transit ? $transit->getBalance() : 0;

        $bankAccounts = $this->pdo->query(
            "SELECT a.code, a.name, a.balance FROM accounts a WHERE a.code LIKE '112%' AND a.code != '112' AND LENGTH(a.code) = 4 ORDER BY a.code"
        )->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'cash_balance' => $cashBal,
            'bank_balance' => $bankBal,
            'transit_balance' => $transitBal,
            'total' => $cashBal + $bankBal + $transitBal,
            'bank_accounts' => $bankAccounts,
            'as_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function getBankLedger(string $fromDate = null, string $toDate = null, string $bankAccount = '112'): array
    {
        $bank = $this->accountRepo->findByCode($bankAccount);
        if (!$bank) return [];

        $where = "le.account_id = " . (int)$bank->getId();
        if ($fromDate) $where .= " AND t.created_at >= " . $this->pdo->quote($fromDate);
        if ($toDate) $where .= " AND t.created_at <= " . $this->pdo->quote($toDate . ' 23:59:59');

        $rows = $this->pdo->query(
            "SELECT t.id, t.description, t.reference, t.created_at as date, le.amount, le.is_debit,
                    a.code as account_code, a.name as account_name
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE {$where}
             ORDER BY t.created_at ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $running = 0;
        foreach ($rows as &$r) {
            $amt = (float)$r['amount'];
            if ($r['is_debit']) { $running += $amt; $r['type'] = 'receipt'; }
            else { $running -= $amt; $r['type'] = 'payment'; }
            $r['running_balance'] = round($running, 2);
        }

        return $rows;
    }

    public function getDailyCashFlow(string $fromDate, string $toDate): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DATE(t.created_at) as date,
                    SUM(CASE WHEN le.is_debit = 1 AND a.code IN ('111','112') THEN le.amount ELSE 0 END) as receipts,
                    SUM(CASE WHEN le.is_debit = 0 AND a.code IN ('111','112') THEN le.amount ELSE 0 END) as payments,
                    COUNT(DISTINCT t.id) as transaction_count
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE a.code IN ('111','112')
               AND DATE(t.created_at) >= ? AND DATE(t.created_at) <= ?
             GROUP BY DATE(t.created_at)
             ORDER BY date ASC"
        );
        $stmt->execute([$fromDate, $toDate]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCashConcentration(): array
    {
        $rows = $this->pdo->query(
            "SELECT a.code, a.name, a.balance
             FROM accounts a
             WHERE (a.code = '111' OR a.code LIKE '112%')
               AND LENGTH(a.code) <= 4
               AND a.code NOT IN ('112')
             ORDER BY a.code"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $total = array_sum(array_column($rows, 'balance'));
        foreach ($rows as &$r) {
            $r['pct'] = $total > 0 ? round($r['balance'] / $total * 100, 1) : 0;
        }

        return ['accounts' => $rows, 'total' => $total];
    }

    public function getCashFlowTrend(int $days = 7): array
    {
        $to = date('Y-m-d');
        $from = date('Y-m-d', strtotime("-{$days} days"));

        return $this->getDailyCashFlow($from, $to);
    }
}

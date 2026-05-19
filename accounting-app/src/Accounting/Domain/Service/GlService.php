<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\AccountRepositoryInterface;

class GlService
{
    private \PDO $pdo;
    private AccountRepositoryInterface $accountRepo;

    public function __construct(\PDO $pdo, AccountRepositoryInterface $accountRepo)
    {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
    }

    public function getGeneralLedger(string $accountCode, ?string $fromDate = null, ?string $toDate = null): array
    {
        $account = $this->accountRepo->findByCode($accountCode);
        if (!$account) throw new \InvalidArgumentException("Account not found: {$accountCode}");

        $isDebitNormal = in_array($account->getType(), ['asset', 'expense']);

        $params = [$account->getId()];
        $dateWhere = '';

        if ($fromDate) {
            $dateWhere .= ' AND t.created_at >= ?';
            $params[] = $fromDate;
        }
        if ($toDate) {
            $dateWhere .= ' AND t.created_at <= ?';
            $params[] = $toDate . ' 23:59:59';
        }

        // Get opening balance before the fromDate (only if date range specified)
        $openingBalance = 0;
        if ($fromDate) {
            $openStmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE 0 END), 0) as total_dr,
                        COALESCE(SUM(CASE WHEN le.is_debit = 0 THEN le.amount ELSE 0 END), 0) as total_cr
                 FROM ledger_entries le
                 JOIN transactions t ON t.id = le.transaction_id
                 WHERE le.account_id = ? AND t.created_at < ?"
            );
            $openStmt->execute([$account->getId(), $fromDate]);
            $openBal = $openStmt->fetch(\PDO::FETCH_ASSOC);
            $openingDr = (float)$openBal['total_dr'];
            $openingCr = (float)$openBal['total_cr'];
            $openingBalance = $isDebitNormal ? ($openingDr - $openingCr) : ($openingCr - $openingDr);
        }

        // Get period transactions
        $periodWhere = 'le.account_id = ?';
        if ($fromDate || $toDate) {
            $periodWhere .= $dateWhere;
        }

        $rows = $this->pdo->prepare(
            "SELECT t.id, t.description, t.reference, t.created_at as date,
                    le.amount, le.is_debit,
                    a2.code as contra_account_code, a2.name as contra_account_name
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             LEFT JOIN ledger_entries le2 ON le2.transaction_id = le.transaction_id AND le2.id != le.id
             LEFT JOIN accounts a2 ON a2.id = le2.account_id
             WHERE {$periodWhere}
             ORDER BY t.created_at ASC, le.id ASC"
        );
        $rows->execute($params);
        $txRows = $rows->fetchAll(\PDO::FETCH_ASSOC);

        // Deduplicate: group by transaction_id to get clean Dr/Cr pairs
        $grouped = [];
        foreach ($txRows as $r) {
            $tid = $r['id'];
            if (!isset($grouped[$tid])) {
                $grouped[$tid] = [
                    'id' => $tid,
                    'date' => $r['date'],
                    'reference' => $r['reference'],
                    'description' => $r['description'],
                    'debit' => 0,
                    'credit' => 0,
                    'contra_account' => '',
                ];
            }
            if ($r['is_debit']) {
                $grouped[$tid]['debit'] += (float)$r['amount'];
            } else {
                $grouped[$tid]['credit'] += (float)$r['amount'];
            }
            // Collect unique contra account codes
            if ($r['contra_account_code'] && $r['contra_account_code'] !== $accountCode) {
                $ca = $r['contra_account_code'];
                if (strpos($grouped[$tid]['contra_account'], $ca) === false) {
                    $grouped[$tid]['contra_account'] .= ($grouped[$tid]['contra_account'] ? ', ' : '') . $ca;
                }
            }
        }

        // Build result with running balance
        $result = [];
        $running = $openingBalance;

        foreach ($grouped as $g) {
            $dr = $g['debit'];
            $cr = $g['credit'];
            if ($isDebitNormal) {
                $running += $dr - $cr;
            } else {
                $running += $cr - $dr;
            }
            $result[] = [
                'date' => substr($g['date'], 0, 10),
                'reference' => $g['reference'],
                'description' => $g['description'],
                'debit' => $dr,
                'credit' => $cr,
                'contra_account' => $g['contra_account'],
                'running_balance' => round($running, 2),
            ];
        }

        return [
            'account_code' => $accountCode,
            'account_name' => $account->getName(),
            'account_type' => $account->getType(),
            'opening_balance' => round($openingBalance, 2),
            'closing_balance' => round($running, 2),
            'entries' => $result,
        ];
    }

    public function getMonthlyLedger(string $accountCode, ?string $fromDate = null, ?string $toDate = null): array
    {
        $account = $this->accountRepo->findByCode($accountCode);
        if (!$account) throw new \InvalidArgumentException("Account not found: {$accountCode}");

        $isDebitNormal = in_array($account->getType(), ['asset', 'expense']);
        $accountId = $account->getId();

        if (!$fromDate) $fromDate = date('Y-01-01');
        if (!$toDate) $toDate = date('Y-12-31');
        $firstMonthStart = (new \DateTime($fromDate))->format('Y-m-01');

        // Opening balance at start of first month
        $openStmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE 0 END), 0) as total_dr,
                    COALESCE(SUM(CASE WHEN le.is_debit = 0 THEN le.amount ELSE 0 END), 0) as total_cr
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             WHERE le.account_id = ? AND t.created_at < ?"
        );
        $openStmt->execute([$accountId, $firstMonthStart]);
        $openBal = $openStmt->fetch(\PDO::FETCH_ASSOC);
        $openingBeforeFirstMonth = $isDebitNormal
            ? ((float)$openBal['total_dr'] - (float)$openBal['total_cr'])
            : ((float)$openBal['total_cr'] - (float)$openBal['total_dr']);

        // Monthly totals
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(t.created_at, '%Y-%m') as period,
                    SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE 0 END) as total_debit,
                    SUM(CASE WHEN le.is_debit = 0 THEN le.amount ELSE 0 END) as total_credit
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             WHERE le.account_id = ? AND t.created_at >= ? AND t.created_at <= ?
             GROUP BY DATE_FORMAT(t.created_at, '%Y-%m')
             ORDER BY period ASC"
        );
        $endDate = $toDate . ' 23:59:59';
        $stmt->execute([$accountId, $firstMonthStart, $endDate]);
        $monthlyTotals = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $totalsByMonth = [];
        foreach ($monthlyTotals as $m) {
            $totalsByMonth[$m['period']] = $m;
        }

        // Contra account detail per month (debit side: which accounts credited into this account)
        $contraStmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(t.created_at, '%Y-%m') as period,
                    a2.code as contra_code, a2.name as contra_name, SUM(le2.amount) as amount
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN ledger_entries le2 ON le2.transaction_id = le.transaction_id AND le2.id != le.id AND le2.is_debit = 0
             JOIN accounts a2 ON a2.id = le2.account_id
             WHERE le.account_id = ? AND le.is_debit = 1
               AND t.created_at >= ? AND t.created_at <= ?
             GROUP BY DATE_FORMAT(t.created_at, '%Y-%m'), a2.code
             ORDER BY period ASC, amount DESC"
        );
        $contraStmt->execute([$accountId, $firstMonthStart, $endDate]);
        $contraRows = $contraStmt->fetchAll(\PDO::FETCH_ASSOC);
        $contraByMonth = [];
        foreach ($contraRows as $r) {
            $contraByMonth[$r['period']][] = [
                'contra_account_code' => $r['contra_code'],
                'contra_account_name' => $r['contra_name'],
                'amount' => (float)$r['amount'],
            ];
        }

        // Build all months in range
        $start = new \DateTime($firstMonthStart);
        $end = new \DateTime($toDate);
        $months = [];
        while ($start <= $end) {
            $months[] = $start->format('Y-m');
            $start->modify('+1 month');
        }

        $result = [];
        $running = $openingBeforeFirstMonth;

        foreach ($months as $period) {
            $hasData = isset($totalsByMonth[$period]);
            $totalDr = $hasData ? (float)$totalsByMonth[$period]['total_debit'] : 0;
            $totalCr = $hasData ? (float)$totalsByMonth[$period]['total_credit'] : 0;

            $monthOpening = $running;

            if ($isDebitNormal) {
                $running += $totalDr - $totalCr;
            } else {
                $running += $totalCr - $totalDr;
            }

            $result[] = [
                'period' => $period,
                'opening_balance' => round($monthOpening, 2),
                'total_debit' => round($totalDr, 2),
                'total_credit' => round($totalCr, 2),
                'closing_balance' => round($running, 2),
                'contra_debit_items' => $contraByMonth[$period] ?? [],
            ];
        }

        return [
            'account_code' => $accountCode,
            'account_name' => $account->getName(),
            'account_type' => $account->getType(),
            'mode' => 'monthly',
            'opening_balance' => round($openingBeforeFirstMonth, 2),
            'closing_balance' => round($running, 2),
            'entries' => $result,
        ];
    }

    public function getAccounts(): array
    {
        $stmt = $this->pdo->query("SELECT code, name, type, balance FROM accounts WHERE is_control = 0 ORDER BY code");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

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

    public function getAccounts(): array
    {
        $stmt = $this->pdo->query("SELECT code, name, type, balance FROM accounts WHERE is_control = 0 ORDER BY code");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

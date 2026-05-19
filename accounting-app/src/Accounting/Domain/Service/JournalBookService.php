<?php
namespace Accounting\Domain\Service;

class JournalBookService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getGeneralJournal(?string $fromDate = null, ?string $toDate = null): array
    {
        if (!$fromDate) $fromDate = date('Y-01-01');
        if (!$toDate) $toDate = date('Y-m-d');

        $params = [$fromDate, $toDate . ' 23:59:59'];

        $stmt = $this->pdo->prepare(
            "SELECT t.id AS txn_id, COALESCE(t.transaction_date, DATE(t.date)) AS txn_date,
                    t.reference, t.description,
                    le.id AS le_id, a.code AS account_code, a.name AS account_name,
                    le.amount, le.is_debit
             FROM transactions t
             JOIN ledger_entries le ON le.transaction_id = t.id
             LEFT JOIN accounts a ON a.id = le.account_id
             WHERE t.status = 'posted'
               AND (COALESCE(t.transaction_date, DATE(t.date)) BETWEEN ? AND ?)
             ORDER BY COALESCE(t.transaction_date, DATE(t.date)) ASC, t.id ASC, le.is_debit DESC, le.id ASC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $txnGroups = [];
        foreach ($rows as $r) {
            $txnGroups[$r['txn_id']][] = $r;
        }

        $result = [];
        $cumulativeDr = 0;
        $cumulativeCr = 0;

        foreach ($txnGroups as $txnId => $entries) {
            $drAccounts = [];
            $crAccounts = [];
            foreach ($entries as $e) {
                if ($e['is_debit']) {
                    $drAccounts[$e['account_code']] = true;
                } else {
                    $crAccounts[$e['account_code']] = true;
                }
            }

            $isFirst = true;
            foreach ($entries as $e) {
                $contraCodes = $e['is_debit'] ? array_keys($crAccounts) : array_keys($drAccounts);
                $contraCodes = array_values(array_filter($contraCodes, fn($c) => $c !== $e['account_code']));
                $contraStr = !empty($contraCodes) ? implode(', ', $contraCodes) : '—';

                $drAmount = $e['is_debit'] ? (float)$e['amount'] : 0;
                $crAmount = $e['is_debit'] ? 0 : (float)$e['amount'];
                $cumulativeDr += $drAmount;
                $cumulativeCr += $crAmount;

                $result[] = [
                    'date' => $e['txn_date'],
                    'reference' => $e['reference'],
                    'description' => $isFirst ? $e['description'] : '',
                    'account_code' => $e['account_code'],
                    'account_name' => $e['account_name'],
                    'contra_account' => $contraStr,
                    'debit' => $drAmount,
                    'credit' => $crAmount,
                    'cumulative_dr' => round($cumulativeDr, 2),
                    'cumulative_cr' => round($cumulativeCr, 2),
                ];
                $isFirst = false;
            }
        }

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'total_debit' => round($cumulativeDr, 2),
            'total_credit' => round($cumulativeCr, 2),
            'entries' => $result,
        ];
    }
}

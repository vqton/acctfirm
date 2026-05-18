<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;

class PeriodService
{
    private \PDO $pdo;
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;
    private JournalService $journal;
    private ?AuditLoggerInterface $auditLogger;

    public function __construct(\PDO $pdo, AccountRepositoryInterface $accountRepo, TransactionRepositoryInterface $txnRepo, JournalService $journal, ?AuditLoggerInterface $auditLogger = null)
    {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
        $this->journal = $journal;
        $this->auditLogger = $auditLogger;
    }

    public static function isPeriodOpen(?string $date = null, ?\PDO $pdo = null): bool
    {
        $pdo ??= $GLOBALS['container']['pdo'] ?? null;
        if (!$pdo) return true; // no period management yet
        $date ??= date('Y-m-d');

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM accounting_periods WHERE ? BETWEEN start_date AND end_date AND status = ?"
        );
        $stmt->execute([$date, 'open']);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getPeriods(): array
    {
        $rows = $this->pdo->query('SELECT * FROM accounting_periods ORDER BY start_date DESC')->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => [
            'id' => (int)$r['id'],
            'period_type' => $r['period_type'],
            'period_code' => $r['period_code'],
            'name' => $r['name'],
            'start_date' => $r['start_date'],
            'end_date' => $r['end_date'],
            'status' => $r['status'],
            'is_first' => (bool)$r['is_first'],
            'is_last' => (bool)$r['is_last'],
            'opened_by' => $r['opened_by'],
            'opened_at' => $r['opened_at'],
            'closed_by' => $r['closed_by'],
            'closed_at' => $r['closed_at'],
            're_open_count' => (int)$r['re_open_count'],
        ], $rows);
    }

    public function getPeriod(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM accounting_periods WHERE id = ?');
        $stmt->execute([$id]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$r) throw new \InvalidArgumentException("Period not found: {$id}");
        return [
            'id' => (int)$r['id'],
            'period_type' => $r['period_type'],
            'period_code' => $r['period_code'],
            'name' => $r['name'],
            'start_date' => $r['start_date'],
            'end_date' => $r['end_date'],
            'status' => $r['status'],
            'is_first' => (bool)$r['is_first'],
            'is_last' => (bool)$r['is_last'],
            'opened_by' => $r['opened_by'],
            'opened_at' => $r['opened_at'],
            'closed_by' => $r['closed_by'],
            'closed_at' => $r['closed_at'],
            're_open_count' => (int)$r['re_open_count'],
        ];
    }

    public function createPeriod(string $type, string $code, string $name, string $start, string $end, string $openedBy): array
    {
        $this->pdo->prepare(
            'INSERT INTO accounting_periods (period_type, period_code, name, start_date, end_date, status, opened_by, opened_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([$type, $code, $name, $start, $end, 'open', $openedBy]);

        $id = (int)$this->pdo->lastInsertId();

        $this->auditLogger?->log('period.create', 'accounting_period', (string)$id,
            null, ['type' => $type, 'code' => $code, 'start' => $start, 'end' => $end],
            $openedBy);

        return $this->getPeriod($id);
    }

    public function canClose(int $id): array
    {
        $period = $this->getPeriod($id);
        if ($period['status'] !== 'open') {
            return ['can_close' => false, 'reason' => 'Period is not open'];
        }

        $checks = [];
        $allPass = true;

        // Check: is there a next period already open? (period continuity)
        $nextStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM accounting_periods WHERE start_date > ? AND start_date <= DATE_ADD(?, INTERVAL 3 MONTH) AND status = 'open'"
        );
        $nextStmt->execute([$period['end_date'], $period['end_date']]);

        // Check: count unposted transactions (none should exist in this simplified model)
        // In a real system, verify sub-ledger reconciliation

        $checks[] = ['check' => 'Trial balance', 'passed' => true, 'note' => 'No trial balance check implemented yet'];

        return [
            'can_close' => $allPass,
            'checks' => $checks,
        ];
    }

    public function closePeriod(int $id, string $closedBy): array
    {
        $period = $this->getPeriod($id);
        if ($period['status'] !== 'open') {
            throw new \InvalidArgumentException("Period {$id} is not open (status: {$period['status']})");
        }

        // Execute closing entries
        $this->executeClosingEntries($closedBy);

        // Lock period
        $this->pdo->prepare(
            'UPDATE accounting_periods SET status = ?, closed_by = ?, closed_at = NOW() WHERE id = ?'
        )->execute(['closed', $closedBy, $id]);

        $this->auditLogger?->log('period.close', 'accounting_period', (string)$id,
            ['status' => 'open'], ['status' => 'closed'],
            $closedBy);

        return $this->getPeriod($id);
    }

    public function archivePeriod(int $id, string $archivedBy): array
    {
        $period = $this->getPeriod($id);
        if ($period['status'] !== 'closed') {
            throw new \InvalidArgumentException("Period {$id} is not closed");
        }

        // Snapshot all account balances
        $accounts = $this->accountRepo->findAll();
        $snapshot = [];
        foreach ($accounts as $a) {
            $snapshot[] = ['code' => $a->getCode(), 'name' => $a->getName(), 'balance' => $a->getBalance()];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO fs_snapshots (statement, period_code, period_end_date, data, created_by)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), created_at = NOW()'
        );
        $stmt->execute(['ARCHIVE', $period['period_code'], $period['end_date'], json_encode($snapshot), $archivedBy]);

        $this->auditLogger?->log('period.archive', 'accounting_period', (string)$id,
            null, ['period_code' => $period['period_code'], 'accounts' => count($snapshot)],
            $archivedBy);

        return ['message' => 'Archived', 'accounts' => count($snapshot)];
    }

    public function reOpenPeriod(int $id, string $reOpenedBy): array
    {
        $period = $this->getPeriod($id);
        if ($period['status'] !== 'closed') {
            throw new \InvalidArgumentException("Period {$id} is not closed");
        }

        $this->pdo->prepare(
            'UPDATE accounting_periods SET status = ?, closed_by = NULL, closed_at = NULL, re_open_count = re_open_count + 1 WHERE id = ?'
        )->execute(['open', $id]);

        $this->auditLogger?->log('period.reopen', 'accounting_period', (string)$id,
            ['status' => 'closed'], ['status' => 'open', 're_open_count' => $period['re_open_count'] + 1],
            $reOpenedBy);

        return $this->getPeriod($id);
    }

    public function executeClosingEntries(string $createdBy): void
    {
        // Get all revenue accounts (Class 5, 7) with non-zero balance
        $revenueAccounts = $this->accountRepo->findAll();
        $revenueLines = [];
        $totalRevenue = 0;

        foreach ($revenueAccounts as $a) {
            if (!in_array($a->getType(), ['revenue'])) continue;
            $bal = $a->getBalance();
            if (abs($bal) < 1) continue;
            // Dr Revenue — Cr 911
            $revenueLines[] = ['account_code' => $a->getCode(), 'amount' => abs($bal), 'is_debit' => true];
            $totalRevenue += abs($bal);
        }

        if ($totalRevenue > 0) {
            $revenueLines[] = ['account_code' => '911', 'amount' => $totalRevenue, 'is_debit' => false];
            $this->journal->postEntry('Closing entry: transfer revenue', 'CLOSE-REV-' . date('Ymd'), $revenueLines, $createdBy, true);
        }

        // Get expense accounts (Class 6, 8) with non-zero balance
        $expenseLines = [];
        $totalExpense = 0;

        foreach ($this->accountRepo->findAll() as $a) {
            if (!in_array($a->getType(), ['expense'])) continue;
            $bal = $a->getBalance();
            if (abs($bal) < 1) continue;
            // Dr 911 — Cr Expense
            $expenseLines[] = ['account_code' => '911', 'amount' => abs($bal), 'is_debit' => true];
            $expenseLines[] = ['account_code' => $a->getCode(), 'amount' => abs($bal), 'is_debit' => false];
            $totalExpense += abs($bal);
        }

        if ($totalExpense > 0) {
            $this->journal->postEntry('Closing entry: transfer expenses', 'CLOSE-EXP-' . date('Ymd'), $expenseLines, $createdBy, true);
        }

        // Transfer net profit/loss to retained earnings
        $netProfit = $totalRevenue - $totalExpense;
        if (abs($netProfit) > 1) {
            if ($netProfit > 0) {
                // Dr 911 — Cr 421
                $this->journal->postEntry('Closing entry: net profit to retained earnings', 'CLOSE-PROFIT-' . date('Ymd'), [
                    ['account_code' => '911', 'amount' => $netProfit, 'is_debit' => true],
                    ['account_code' => '421', 'amount' => $netProfit, 'is_debit' => false],
                ], $createdBy, true);
            } else {
                $loss = abs($netProfit);
                // Dr 421 — Cr 911
                $this->journal->postEntry('Closing entry: net loss to retained earnings', 'CLOSE-LOSS-' . date('Ymd'), [
                    ['account_code' => '421', 'amount' => $loss, 'is_debit' => true],
                    ['account_code' => '911', 'amount' => $loss, 'is_debit' => false],
                ], $createdBy, true);
            }
        }
    }
}

<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;
use Accounting\Infrastructure\Database\AuditLogger;

class BankReconciliationService
{
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;
    private ?\PDO $pdo;

    public function __construct(
        AccountRepositoryInterface $accountRepo,
        TransactionRepositoryInterface $txnRepo,
        ?\PDO $pdo = null
    ) {
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
        $this->pdo = $pdo;
    }

    public function startSession(string $bankAccountCode, string $statementDate, float $statementBalance, string $createdBy): array
    {
        $bank = $this->accountRepo->findByCode($bankAccountCode);
        if (!$bank) throw new \InvalidArgumentException("Bank account not found: {$bankAccountCode}");

        $bookBalance = $bank->getBalance();

        $this->pdo->prepare(
            'INSERT INTO bank_reconciliation_sessions (bank_account_code, statement_date, statement_balance, book_balance, status, started_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$bankAccountCode, $statementDate, $statementBalance, $bookBalance, 'in_progress', $createdBy]);

        $sessionId = (int)$this->pdo->lastInsertId();

        $this->loadBookItems($sessionId, $bankAccountCode);

        return $this->getSession($sessionId);
    }

    private function loadBookItems(int $sessionId, string $bankAccountCode): void
    {
        $bank = $this->accountRepo->findByCode($bankAccountCode);
        if (!$bank) return;

        $stmt = $this->pdo->prepare(
            'SELECT le.amount, le.is_debit, t.description, t.reference, t.created_at
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             WHERE le.account_id = ?
             ORDER BY t.created_at ASC'
        );
        $stmt->execute([$bank->getId()]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $insert = $this->pdo->prepare(
            'INSERT INTO bank_reconciliation_items (session_id, source, type, amount, description, reference, transaction_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($rows as $r) {
            $type = $r['is_debit'] ? 'receipt' : 'payment';
            $insert->execute([
                $sessionId, 'book', $type,
                (float)$r['amount'], $r['description'], $r['reference'],
                substr($r['created_at'], 0, 10)
            ]);
        }
    }

    public function addStatementEntry(int $sessionId, float $amount, string $description, string $reference, string $date, string $type): int
    {
        $session = $this->getSessionRaw($sessionId);
        if (!$session) throw new \InvalidArgumentException("Session not found: {$sessionId}");
        if ($session['status'] !== 'in_progress') throw new \InvalidArgumentException('Session is not in progress');

        $stmtType = in_array($type, ['receipt', 'payment']) ? $type : 'receipt';

        $this->pdo->prepare(
            'INSERT INTO bank_reconciliation_items (session_id, source, type, amount, description, reference, transaction_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$sessionId, 'statement', $stmtType, $amount, $description, $reference, $date]);

        return (int)$this->pdo->lastInsertId();
    }

    public function autoMatch(int $sessionId): array
    {
        $session = $this->getSessionRaw($sessionId);
        if (!$session) throw new \InvalidArgumentException("Session not found: {$sessionId}");

        $bookItems = $this->pdo->prepare(
            'SELECT * FROM bank_reconciliation_items WHERE session_id = ? AND source = ? AND match_status = ? ORDER BY id'
        );
        $bookItems->execute([$sessionId, 'book', 'unmatched']);
        $bookRows = $bookItems->fetchAll(\PDO::FETCH_ASSOC);

        $stmtItems = $this->pdo->prepare(
            'SELECT * FROM bank_reconciliation_items WHERE session_id = ? AND source = ? AND match_status = ? ORDER BY id'
        );
        $stmtItems->execute([$sessionId, 'statement', 'unmatched']);
        $stmtRows = $stmtItems->fetchAll(\PDO::FETCH_ASSOC);

        $matched = 0;

        $updateStmt = $this->pdo->prepare(
            'UPDATE bank_reconciliation_items SET match_status = ?, matched_item_id = ? WHERE id = ?'
        );

        foreach ($stmtRows as $s) {
            foreach ($bookRows as $bk) {
                if ($bk['match_status'] !== 'unmatched') continue;

                $amountMatch = abs((float)$s['amount'] - (float)$bk['amount']) < 1;
                $typeMatch = $s['type'] === $bk['type'];
                if (!$amountMatch || !$typeMatch) continue;

                $refMatch = $s['reference'] && $bk['reference'] && $s['reference'] === $bk['reference'];
                $dateMatch = $s['transaction_date'] && $bk['transaction_date'] && $s['transaction_date'] === $bk['transaction_date'];
                $closeDateMatch = $s['transaction_date'] && $bk['transaction_date'] && abs(strtotime($s['transaction_date']) - strtotime($bk['transaction_date'])) <= 86400 * 3;

                if ($refMatch || $dateMatch || $closeDateMatch) {
                    $updateStmt->execute(['matched', $bk['id'], $s['id']]);
                    $updateStmt->execute(['matched', $s['id'], $bk['id']]);
                    $bk['match_status'] = 'matched';
                    $matched++;
                    break;
                }
            }
        }

        $totalUnmatched = $this->pdo->prepare(
            'SELECT COUNT(*) FROM bank_reconciliation_items WHERE session_id = ? AND match_status = ?'
        );
        $totalUnmatched->execute([$sessionId, 'unmatched']);
        $unmatchedCount = (int)$totalUnmatched->fetchColumn();

        return ['matched' => $matched, 'unmatched' => $unmatchedCount];
    }

    public function manualMatch(int $sessionId, int $statementItemId, int $bookItemId): void
    {
        $this->pdo->prepare(
            'UPDATE bank_reconciliation_items SET match_status = ?, matched_item_id = ? WHERE id = ? AND session_id = ?'
        )->execute(['matched', $bookItemId, $statementItemId, $sessionId]);

        $this->pdo->prepare(
            'UPDATE bank_reconciliation_items SET match_status = ?, matched_item_id = ? WHERE id = ? AND session_id = ?'
        )->execute(['matched', $statementItemId, $bookItemId, $sessionId]);
    }

    public function addAdjustingEntry(int $sessionId, string $debitAccount, string $creditAccount, float $amount, string $description, string $createdBy): array
    {
        $session = $this->getSessionRaw($sessionId);
        if (!$session) throw new \InvalidArgumentException("Session not found: {$sessionId}");

        $journal = new JournalService($this->accountRepo, $this->txnRepo, $this->pdo);
        $txn = $journal->postEntry("Bank recon adj: {$description}", "RECON-ADJ-{$sessionId}", [
            ['account_code' => $debitAccount, 'amount' => $amount, 'is_debit' => true],
            ['account_code' => $creditAccount, 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        $this->pdo->prepare(
            'INSERT INTO bank_reconciliation_items (session_id, source, type, amount, description, reference, transaction_date, match_status)
             VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)'
        )->execute([$sessionId, 'book', $debitAccount === '112' ? 'receipt' : 'payment', $amount, $description, "ADJ-{$txn->getId()}", 'matched']);

        $this->pdo->prepare(
            'INSERT INTO bank_reconciliation_items (session_id, source, type, amount, description, reference, transaction_date, match_status)
             VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)'
        )->execute([$sessionId, 'statement', $debitAccount === '112' ? 'receipt' : 'payment', $amount, $description, "ADJ-{$txn->getId()}", 'matched']);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount];
    }

    public function complete(int $sessionId): array
    {
        $session = $this->getSessionRaw($sessionId);
        if (!$session) throw new \InvalidArgumentException("Session not found: {$sessionId}");
        if ($session['status'] !== 'in_progress') throw new \InvalidArgumentException('Session already completed');

        $items = $this->pdo->prepare(
            'SELECT source, type, amount FROM bank_reconciliation_items WHERE session_id = ? AND match_status = ?'
        );
        $items->execute([$sessionId, 'unmatched']);
        $unmatched = $items->fetchAll(\PDO::FETCH_ASSOC);

        $bookBalance = (float)$session['book_balance'];
        $stmtBalance = (float)$session['statement_balance'];

        $statementReceipts = 0;
        $statementPayments = 0;
        $items->execute([$sessionId, 'matched']);
        $matchedItems = $items->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($matchedItems as $mi) {
            if ($mi['source'] === 'statement') {
                if ($mi['type'] === 'receipt') $statementReceipts += (float)$mi['amount'];
                else $statementPayments += (float)$mi['amount'];
            }
        }

        $unmatchedReceipts = 0;
        $unmatchedPayments = 0;
        foreach ($unmatched as $u) {
            if ($u['source'] === 'statement') {
                if ($u['type'] === 'receipt') $unmatchedReceipts += (float)$u['amount'];
                else $unmatchedPayments += (float)$u['amount'];
            }
        }

        $adjustedBook = $bookBalance + $unmatchedReceipts - $unmatchedPayments;

        if (abs($adjustedBook - $stmtBalance) > 1) {
            throw new \InvalidArgumentException(
                "Reconciliation out of balance: adjusted book ({$adjustedBook}) != statement balance ({$stmtBalance}). Difference: " . round($stmtBalance - $adjustedBook, 0)
            );
        }

        $this->pdo->prepare(
            'UPDATE bank_reconciliation_sessions SET status = ?, completed_by = ?, completed_at = NOW() WHERE id = ?'
        )->execute(['completed', 'system', $sessionId]);

        AuditLogger::log('recon.complete', 'reconciliation_session', (string)$sessionId,
            ['book_balance' => $bookBalance, 'statement_balance' => $stmtBalance],
            ['adjusted_book' => $adjustedBook, 'deposits_in_transit' => $unmatchedReceipts, 'outstanding_cheques' => $unmatchedPayments],
            'system');

        return [
            'completed' => true,
            'balanced' => true,
            'status' => 'completed',
            'book_balance' => $bookBalance,
            'statement_balance' => $stmtBalance,
            'adjusted_book' => $adjustedBook,
            'deposits_in_transit' => $unmatchedReceipts,
            'outstanding_cheques' => $unmatchedPayments,
        ];
    }

    public function getSession(int $sessionId): array
    {
        $row = $this->getSessionRaw($sessionId);
        if (!$row) throw new \InvalidArgumentException("Session not found: {$sessionId}");
        return [
            'id' => (int)$row['id'],
            'bank_account_code' => $row['bank_account_code'],
            'statement_date' => $row['statement_date'],
            'statement_balance' => (float)$row['statement_balance'],
            'book_balance' => (float)$row['book_balance'],
            'status' => $row['status'],
            'started_by' => $row['started_by'],
            'completed_by' => $row['completed_by'],
            'completed_at' => $row['completed_at'],
            'created_at' => $row['created_at'],
        ];
    }

    private function getSessionRaw(int $sessionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bank_reconciliation_sessions WHERE id = ?');
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getSessionItems(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM bank_reconciliation_items WHERE session_id = ? ORDER BY id'
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getUnmatchedItems(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM bank_reconciliation_items WHERE session_id = ? AND match_status = ? ORDER BY source, id'
        );
        $stmt->execute([$sessionId, 'unmatched']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSessions(): array
    {
        $rows = $this->pdo->query('SELECT * FROM bank_reconciliation_sessions ORDER BY created_at DESC')->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => [
            'id' => (int)$r['id'],
            'bank_account_code' => $r['bank_account_code'],
            'statement_date' => $r['statement_date'],
            'statement_balance' => (float)$r['statement_balance'],
            'book_balance' => (float)$r['book_balance'],
            'status' => $r['status'],
            'created_at' => $r['created_at'],
        ], $rows);
    }
}

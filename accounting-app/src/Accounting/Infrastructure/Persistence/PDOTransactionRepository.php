<?php
// src/Accounting/Infrastructure/Repository/PDOTransactionRepository.php

namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\LedgerEntry;
use Accounting\Domain\Model\Transaction;
use Accounting\Domain\Repository\TransactionRepositoryInterface;
use PDO;

class PDOTransactionRepository implements TransactionRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?Transaction
    {
        $stmt = $this->pdo->prepare('SELECT id, date, description, reference, status, created_by FROM transactions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $transaction = new Transaction(
            $row['id'],
            new \DateTimeImmutable($row['date']),
            $row['description'],
            $row['reference']
        );
        
         $transaction->setStatus($row['status']);
         $transaction->setCreatedBy($row['created_by']);

        // Load ledger entries
        $stmt = $this->pdo->prepare('SELECT id, account_id, amount, is_debit, note FROM ledger_entries WHERE transaction_id = ? ORDER BY id');
        $stmt->execute([$id]);
        
        while ($entryRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ledgerEntry = new LedgerEntry(
                $entryRow['id'],
                $entryRow['account_id'],
                (float) $entryRow['amount'],
                (bool) $entryRow['is_debit'],
                $entryRow['note']
            );
            $transaction->addLedgerEntry($ledgerEntry);
        }

        return $transaction;
    }

    public function findByReference(string $reference): ?Transaction
    {
        $stmt = $this->pdo->prepare('SELECT id, date, description, reference, status, created_by FROM transactions WHERE reference = ?');
        $stmt->execute([$reference]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $transaction = new Transaction(
            $row['id'],
            new \DateTimeImmutable($row['date']),
            $row['description'],
            $row['reference']
        );
        
         $transaction->setStatus($row['status']);
         $transaction->setCreatedBy($row['created_by']);

        // Load ledger entries
        $stmt = $this->pdo->prepare('SELECT id, account_id, amount, is_debit, note FROM ledger_entries WHERE transaction_id = ? ORDER BY id');
        $stmt->execute([$row['id']]);
        
        while ($entryRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ledgerEntry = new LedgerEntry(
                $entryRow['id'],
                $entryRow['account_id'],
                (float) $entryRow['amount'],
                (bool) $entryRow['is_debit'],
                $entryRow['note']
            );
            $transaction->addLedgerEntry($ledgerEntry);
        }

        return $transaction;
    }

    public function save(Transaction $transaction): void
    {
        // Save transaction
        $stmt = $this->pdo->prepare(
            'INSERT INTO transactions (id, date, description, reference, status, created_by) VALUES (?, ?, ?, ?, ?, ?) ' .
            'ON DUPLICATE KEY UPDATE date = ?, description = ?, reference = ?, status = ?, created_by = ?'
        );
        $stmt->execute([
            $transaction->getId(),
            $transaction->getDate()->format('Y-m-d H:i:s'),
            $transaction->getDescription(),
            $transaction->getReference(),
            $transaction->getStatus(),
            $transaction->getCreatedBy(),
            $transaction->getDate()->format('Y-m-d H:i:s'),
            $transaction->getDescription(),
            $transaction->getReference(),
            $transaction->getStatus(),
            $transaction->getCreatedBy()
        ]);

        // Delete existing ledger entries for this transaction
        $stmt = $this->pdo->prepare('DELETE FROM ledger_entries WHERE transaction_id = ?');
        $stmt->execute([$transaction->getId()]);

        // Insert new ledger entries
        $stmt = $this->pdo->prepare(
            'INSERT INTO ledger_entries (id, transaction_id, account_id, amount, is_debit, note) VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($transaction->getLedgerEntries() as $entry) {
            $stmt->execute([
                $entry->getId(),
                $transaction->getId(),
                $entry->getAccountId(),
                $entry->getAmount(),
                $entry->isDebit() ? 1 : 0,
                $entry->getNote()
            ]);
        }
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT t.id, t.date, t.description, t.reference, t.status, t.created_by,
                    le.id AS le_id, le.account_id, le.amount AS le_amount, le.is_debit, le.note
             FROM transactions t
             LEFT JOIN ledger_entries le ON le.transaction_id = t.id
             ORDER BY t.date DESC, t.id DESC, le.id'
        );
        return $this->buildTransactions($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getTransactionsByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id, t.date, t.description, t.reference, t.status, t.created_by,
                    le.id AS le_id, le.account_id, le.amount AS le_amount, le.is_debit, le.note
             FROM transactions t
             LEFT JOIN ledger_entries le ON le.transaction_id = t.id
             WHERE t.date >= ? AND t.date <= ?
             ORDER BY t.date DESC, t.id DESC, le.id'
        );
        $stmt->execute([
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        ]);
        return $this->buildTransactions($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getTransactionsByPeriod(string $periodCode): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id, t.date, t.description, t.reference, t.status, t.created_by,
                    le.id AS le_id, le.account_id, le.amount AS le_amount, le.is_debit, le.note
             FROM transactions t
             LEFT JOIN ledger_entries le ON le.transaction_id = t.id
             WHERE DATE_FORMAT(t.date, \'%Y-%m\') = ? OR
                   (t.date >= (SELECT start_date FROM accounting_periods WHERE period_code = ?)
                    AND t.date <= (SELECT end_date FROM accounting_periods WHERE period_code = ?))
             ORDER BY t.date DESC, t.id DESC, le.id'
        );
        $stmt->execute([$periodCode, $periodCode, $periodCode]);
        return $this->buildTransactions($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function buildTransactions(array $rows): array
    {
        $transactions = [];
        $current = null;

        foreach ($rows as $row) {
            if ($current === null || $current['id'] !== $row['id']) {
                if ($current !== null) {
                    $txn = new Transaction(
                        $current['id'],
                        new \DateTimeImmutable($current['date']),
                        $current['description'],
                        $current['reference']
                    );
                    $txn->setStatus($current['status']);
                    $txn->setCreatedBy($current['created_by']);
                    foreach ($current['entries'] as $entry) {
                        $txn->addLedgerEntry($entry);
                    }
                    $transactions[] = $txn;
                }
                $current = [
                    'id' => $row['id'],
                    'date' => $row['date'],
                    'description' => $row['description'],
                    'reference' => $row['reference'],
                    'status' => $row['status'],
                    'created_by' => $row['created_by'],
                    'entries' => [],
                ];
            }
            if ($row['le_id'] !== null) {
                $current['entries'][] = new LedgerEntry(
                    $row['le_id'],
                    $row['account_id'],
                    (float) $row['le_amount'],
                    (bool) $row['is_debit'],
                    $row['note']
                );
            }
        }

        if ($current !== null) {
            $txn = new Transaction(
                $current['id'],
                new \DateTimeImmutable($current['date']),
                $current['description'],
                $current['reference']
            );
            $txn->setStatus($current['status']);
            $txn->setCreatedBy($current['created_by']);
            foreach ($current['entries'] as $entry) {
                $txn->addLedgerEntry($entry);
            }
            $transactions[] = $txn;
        }

        return $transactions;
    }
}
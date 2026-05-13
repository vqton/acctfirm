<?php
// src/Accounting/Infrastructure/Repository/PDOTransactionRepository.php

namespace Accounting\Infrastructure\Repository;

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
        $this->pdo->beginTransaction();

        try {
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

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, date, description, reference, status, created_by FROM transactions ORDER BY date DESC, id DESC');
        $transactions = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transaction = new Transaction(
                $row['id'],
                new \DateTimeImmutable($row['date']),
                $row['description'],
                $row['reference']
            );
            
             $transaction->setStatus($row['status']);
             $transaction->setCreatedBy($row['created_by']);

            // Load ledger entries
            $stmtEntries = $this->pdo->prepare('SELECT id, account_id, amount, is_debit, note FROM ledger_entries WHERE transaction_id = ? ORDER BY id');
            $stmtEntries->execute([$row['id']]);
            
            while ($entryRow = $stmtEntries->fetch(PDO::FETCH_ASSOC)) {
                $ledgerEntry = new LedgerEntry(
                    $entryRow['id'],
                    $entryRow['account_id'],
                    (float) $entryRow['amount'],
                    (bool) $entryRow['is_debit'],
                    $entryRow['note']
                );
                $transaction->addLedgerEntry($ledgerEntry);
            }

            $transactions[] = $transaction;
        }

        return $transactions;
    }

    public function getTransactionsByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, date, description, reference, status, created_by FROM transactions ' .
            'WHERE date >= ? AND date <= ? ORDER BY date DESC, id DESC'
        );
        $stmt->execute([
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        ]);
        
        $transactions = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transaction = new Transaction(
                $row['id'],
                new \DateTimeImmutable($row['date']),
                $row['description'],
                $row['reference']
            );
            
             $transaction->setStatus($row['status']);
             $transaction->setCreatedBy($row['created_by']);

            // Load ledger entries
            $stmtEntries = $this->pdo->prepare('SELECT id, account_id, amount, is_debit, note FROM ledger_entries WHERE transaction_id = ? ORDER BY id');
            $stmtEntries->execute([$row['id']]);
            
            while ($entryRow = $stmtEntries->fetch(PDO::FETCH_ASSOC)) {
                $ledgerEntry = new LedgerEntry(
                    $entryRow['id'],
                    $entryRow['account_id'],
                    (float) $entryRow['amount'],
                    (bool) $entryRow['is_debit'],
                    $entryRow['note']
                );
                $transaction->addLedgerEntry($ledgerEntry);
            }

            $transactions[] = $transaction;
        }

        return $transactions;
    }
}
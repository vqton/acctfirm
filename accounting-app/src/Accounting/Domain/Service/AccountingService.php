<?php
// src/Accounting/Domain/Service/AccountingService.php

namespace Accounting\Domain\Service;

use Accounting\Domain\Model\Account;
use Accounting\Domain\Model\LedgerEntry;
use Accounting\Domain\Model\Transaction;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;

class AccountingService
{
    private AccountRepositoryInterface $accountRepository;
    private TransactionRepositoryInterface $transactionRepository;

    public function __construct(
        AccountRepositoryInterface $accountRepository,
        TransactionRepositoryInterface $transactionRepository
    ) {
        $this->accountRepository = $accountRepository;
        $this->transactionRepository = $transactionRepository;
    }

    public function createAccount(string $id, string $name, string $type): Account
    {
        $account = new Account($id, $name, $type);
        $this->accountRepository->save($account);
        return $account;
    }

    public function recordTransaction(
        string $transactionId,
        \DateTimeImmutable $date,
        string $description,
        string $reference,
        array $ledgerEntries
    ): Transaction {
        $transaction = new Transaction($transactionId, $date, $description, $reference);

        foreach ($ledgerEntries as $entry) {
            $transaction->addLedgerEntry($entry);
        }

        $this->transactionRepository->save($transaction);
        return $transaction;
    }

    public function postTransaction(string $transactionId, string $createdBy): void
    {
        $transaction = $this->transactionRepository->findById($transactionId);
        if (!$transaction) {
            throw new \InvalidArgumentException('Transaction not found');
        }

        $transaction->post($createdBy);
        $this->transactionRepository->save($transaction);

        // Apply ledger entries to accounts
        foreach ($transaction->getLedgerEntries() as $entry) {
            $account = $this->accountRepository->findById($entry->getAccountId());
            if ($account) {
                if ($entry->isDebit()) {
                    $account->debit($entry->getAmount());
                } else {
                    $account->credit($entry->getAmount());
                }
                $this->accountRepository->save($account);
            }
        }
    }

    public function reverseTransaction(string $transactionId, string $reversedBy): void
    {
        $transaction = $this->transactionRepository->findById($transactionId);
        if (!$transaction) {
            throw new \InvalidArgumentException('Transaction not found');
        }

        if ($transaction->getStatus() !== 'posted') {
            throw new \InvalidArgumentException('Only posted transactions can be reversed');
        }

        $transaction->reverse($reversedBy);
        $this->transactionRepository->save($transaction);

        // Reverse ledger entries on accounts
        foreach ($transaction->getLedgerEntries() as $entry) {
            $account = $this->accountRepository->findById($entry->getAccountId());
            if ($account) {
                if ($entry->isDebit()) {
                    $account->credit($entry->getAmount());
                } else {
                    $account->debit($entry->getAmount());
                }
                $this->accountRepository->save($account);
            }
        }
    }

    public function getAccountBalance(string $accountId): float
    {
        $account = $this->accountRepository->findById($accountId);
        return $account ? $account->getBalance() : 0.0;
    }

    public function getTrialBalance(): array
    {
        $accounts = $this->accountRepository->getAll();
        $trialBalance = [];
        
        foreach ($accounts as $account) {
            $trialBalance[] = [
                'id' => $account->getId(),
                'name' => $account->getName(),
                'type' => $account->getType(),
                'balance' => $account->getBalance()
            ];
        }
        
        return $trialBalance;
    }
}
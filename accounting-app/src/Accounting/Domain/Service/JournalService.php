<?php
// src/Accounting/Domain/Service/JournalService.php

namespace Accounting\Domain\Service;

use Accounting\Domain\Model\Account;
use Accounting\Domain\Model\LedgerEntry;
use Accounting\Domain\Model\Transaction;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;

class JournalService
{
    private AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function recordSale(string $customerId, string $accountId, float $amount, string $description, string $reference): Transaction
    {
        $transactionId = uniqid('txn_');
        $date = new \DateTimeImmutable();

        // Create ledger entries: debit accounts receivable, credit revenue
        $debitEntry = new LedgerEntry(uniqid('led_'), $accountId, $amount, true, "Sale to customer {$customerId}");
        $creditEntry = new LedgerEntry(uniqid('led_'), 'revenue_account', $amount, false, "Sale to customer {$customerId}");

        $transaction = $this->accountingService->recordTransaction(
            $transactionId,
            $date,
            $description,
            $reference,
            [$debitEntry, $creditEntry]
        );

        return $transaction;
    }

    public function recordExpense(string $vendorId, string $expenseAccountId, string $cashAccountId, float $amount, string $description, string $reference): Transaction
    {
        $transactionId = uniqid('txn_');
        $date = new \DateTimeImmutable();

        // Create ledger entries: debit expense, credit cash
        $debitEntry = new LedgerEntry(uniqid('led_'), $expenseAccountId, $amount, true, "Expense to vendor {$vendorId}");
        $creditEntry = new LedgerEntry(uniqid('led_'), $cashAccountId, $amount, false, "Expense to vendor {$vendorId}");

        $transaction = $this->accountingService->recordTransaction(
            $transactionId,
            $date,
            $description,
            $reference,
            [$debitEntry, $creditEntry]
        );

        return $transaction;
    }

    public function recordPayment(string $customerId, string $accountsReceivableId, string $cashAccountId, float $amount, string $description, string $reference): Transaction
    {
        $transactionId = uniqid('txn_');
        $date = new \DateTimeImmutable();

        // Create ledger entries: debit cash, credit accounts receivable
        $debitEntry = new LedgerEntry(uniqid('led_'), $cashAccountId, $amount, true, "Payment from customer {$customerId}");
        $creditEntry = new LedgerEntry(uniqid('led_'), $accountsReceivableId, $amount, false, "Payment from customer {$customerId}");

        $transaction = $this->accountingService->recordTransaction(
            $transactionId,
            $date,
            $description,
            $reference,
            [$debitEntry, $creditEntry]
        );

        return $transaction;
    }
}
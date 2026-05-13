<?php
// src/Accounting/Interfaces/HTTP/TransactionController.php

namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\AccountingService;
use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Model\Account;
use Accounting\Domain\Model\LedgerEntry;
use Accounting\Domain\Model\Transaction;

class TransactionController
{
    private AccountingService $accountingService;
    private JournalService $journalService;

    public function __construct(AccountingService $accountingService, JournalService $journalService)
    {
        $this->accountingService = $accountingService;
        $this->journalService = $journalService;
    }

    public function recordSale(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['customer_id']) || !isset($data['account_id']) || !isset($data['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }

        try {
            $transaction = $this->journalService->recordSale(
                $data['customer_id'],
                $data['account_id'],
                (float) $data['amount'],
                $data['description'] ?? '',
                $data['reference'] ?? uniqid('ref_')
            );
            
            http_response_code(201);
            echo json_encode([
                'id' => $transaction->getId(),
                'date' => $transaction->getDate()->format('Y-m-d H:i:s'),
                'description' => $transaction->getDescription(),
                'reference' => $transaction->getReference(),
                'status' => $transaction->getStatus(),
                'ledger_entries' => array_map(function($entry) {
                    return [
                        'id' => $entry->getId(),
                        'account_id' => $entry->getAccountId(),
                        'amount' => $entry->getAmount(),
                        'is_debit' => $entry->isDebit(),
                        'note' => $entry->getNote()
                    ];
                }, $transaction->getLedgerEntries())
            ]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    public function recordExpense(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['vendor_id']) || !isset($data['expense_account_id']) || !isset($data['cash_account_id']) || !isset($data['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }

        try {
            $transaction = $this->journalService->recordExpense(
                $data['vendor_id'],
                $data['expense_account_id'],
                $data['cash_account_id'],
                (float) $data['amount'],
                $data['description'] ?? '',
                $data['reference'] ?? uniqid('ref_')
            );
            
            http_response_code(201);
            echo json_encode([
                'id' => $transaction->getId(),
                'date' => $transaction->getDate()->format('Y-m-d H:i:s'),
                'description' => $transaction->getDescription(),
                'reference' => $transaction->getReference(),
                'status' => $transaction->getStatus(),
                'ledger_entries' => array_map(function($entry) {
                    return [
                        'id' => $entry->getId(),
                        'account_id' => $entry->getAccountId(),
                        'amount' => $entry->getAmount(),
                        'is_debit' => $entry->isDebit(),
                        'note' => $entry->getNote()
                    ];
                }, $transaction->getLedgerEntries())
            ]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    public function recordPayment(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['customer_id']) || !isset($data['accounts_receivable_id']) || !isset($data['cash_account_id']) || !isset($data['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }

        try {
            $transaction = $this->journalService->recordPayment(
                $data['customer_id'],
                $data['accounts_receivable_id'],
                $data['cash_account_id'],
                (float) $data['amount'],
                $data['description'] ?? '',
                $data['reference'] ?? uniqid('ref_')
            );
            
            http_response_code(201);
            echo json_encode([
                'id' => $transaction->getId(),
                'date' => $transaction->getDate()->format('Y-m-d H:i:s'),
                'description' => $transaction->getDescription(),
                'reference' => $transaction->getReference(),
                'status' => $transaction->getStatus(),
                'ledger_entries' => array_map(function($entry) {
                    return [
                        'id' => $entry->getId(),
                        'account_id' => $entry->getAccountId(),
                        'amount' => $entry->getAmount(),
                        'is_debit' => $entry->isDebit(),
                        'note' => $entry->getNote()
                    ];
                }, $transaction->getLedgerEntries())
            ]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    public function postTransaction(string $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['created_by'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }

        try {
            $this->accountingService->postTransaction($id, $data['created_by']);
            
            http_response_code(200);
            echo json_encode(['message' => 'Transaction posted successfully']);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    public function reverseTransaction(string $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['reversed_by'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }

        try {
            $this->accountingService->reverseTransaction($id, $data['reversed_by']);
            
            http_response_code(200);
            echo json_encode(['message' => 'Transaction reversed successfully']);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }
}
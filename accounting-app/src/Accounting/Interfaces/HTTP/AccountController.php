<?php
// src/Accounting/Interfaces/HTTP/AccountController.php

namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\AccountingService;
use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Model\Account;
use Accounting\Domain\Model\LedgerEntry;
use Accounting\Domain\Model\Transaction;

class AccountController
{
    private AccountingService $accountingService;
    private JournalService $journalService;

    public function __construct(AccountingService $accountingService, JournalService $journalService)
    {
        $this->accountingService = $accountingService;
        $this->journalService = $journalService;
    }

    public function createAccount(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['id']) || !isset($data['name']) || !isset($data['type'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }

        try {
            $account = $this->accountingService->createAccount(
                $data['id'],
                $data['name'],
                $data['type']
            );
            
            http_response_code(201);
            echo json_encode([
                'id' => $account->getId(),
                'name' => $account->getName(),
                'type' => $account->getType(),
                'balance' => $account->getBalance(),
                'created_at' => $account->getCreatedAt()->format('Y-m-d H:i:s')
            ]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    public function getAccount(string $id): void
    {
        try {
            $account = $this->accountingService->getAccountBalance($id);
            
            http_response_code(200);
            echo json_encode([
                'id' => $id,
                'balance' => $account
            ]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(404);
            echo json_encode(['error' => 'Account not found']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    public function getTrialBalance(): void
    {
        try {
            $trialBalance = $this->accountingService->getTrialBalance();
            
            http_response_code(200);
            echo json_encode($trialBalance);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }
}
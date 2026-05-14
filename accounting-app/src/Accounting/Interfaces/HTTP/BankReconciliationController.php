<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\BankReconciliationService;
use Accounting\Domain\Repository\AccountRepositoryInterface;

class BankReconciliationController
{
    private BankReconciliationService $recon;
    private AccountRepositoryInterface $accountRepo;

    public function __construct(BankReconciliationService $recon, AccountRepositoryInterface $accountRepo)
    {
        $this->recon = $recon;
        $this->accountRepo = $accountRepo;
    }

    public function sessions(): void
    {
        echo json_encode($this->recon->getSessions());
    }

    public function startSession(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['bank_account_code'], $data['statement_date'], $data['statement_balance'])) {
            http_response_code(400);
            echo json_encode(['error' => 'bank_account_code, statement_date, statement_balance required']);
            return;
        }
        try {
            $result = $this->recon->startSession(
                $data['bank_account_code'], $data['statement_date'],
                (float)$data['statement_balance'],
                $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getSession(int $id): void
    {
        try {
            echo json_encode($this->recon->getSession($id));
        } catch (\InvalidArgumentException $e) {
            http_response_code(404);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function items(int $id): void
    {
        echo json_encode($this->recon->getSessionItems($id));
    }

    public function unmatched(int $id): void
    {
        echo json_encode($this->recon->getUnmatchedItems($id));
    }

    public function addStatementEntry(int $sessionId): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['type'])) {
            http_response_code(400);
            echo json_encode(['error' => 'amount, type required']);
            return;
        }
        try {
            $id = $this->recon->addStatementEntry(
                $sessionId, (float)$data['amount'],
                $data['description'] ?? '', $data['reference'] ?? '',
                $data['date'] ?? date('Y-m-d'), $data['type']
            );
            http_response_code(201);
            echo json_encode(['id' => $id]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function autoMatch(int $sessionId): void
    {
        try {
            echo json_encode($this->recon->autoMatch($sessionId));
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function manualMatch(int $sessionId): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['statement_item_id'], $data['book_item_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'statement_item_id, book_item_id required']);
            return;
        }
        try {
            $this->recon->manualMatch($sessionId, (int)$data['statement_item_id'], (int)$data['book_item_id']);
            echo json_encode(['matched' => true]);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function addAdjustingEntry(int $sessionId): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['debit_account'], $data['credit_account'], $data['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'debit_account, credit_account, amount required']);
            return;
        }
        try {
            $result = $this->recon->addAdjustingEntry(
                $sessionId, $data['debit_account'], $data['credit_account'],
                (float)$data['amount'], $data['description'] ?? 'Adjustment',
                $data['created_by'] ?? 'system'
            );
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function complete(int $sessionId): void
    {
        try {
            echo json_encode($this->recon->complete($sessionId));
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function bankAccounts(): void
    {
        $all = $this->accountRepo->findAll();
        $bankAccounts = array_filter($all, fn($a) => str_starts_with($a->getCode(), '112'));
        echo json_encode(array_map(fn($a) => [
            'code' => $a->getCode(), 'name' => $a->getName(),
            'balance' => $a->getBalance(),
        ], $bankAccounts));
    }
}

<?php
namespace Accounting\Interfaces\HTTP\Cash;

use Accounting\Domain\Service\BankReconciliationService;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

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
        JsonResponse::ok($this->recon->getSessions());
    }

    public function startSession(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['bank_account_code'], $data['statement_date'], $data['statement_balance'])) {
            JsonResponse::error('bank_account_code, statement_date, statement_balance required', 400);
            return;
        }
        try {
            $result = $this->recon->startSession(
                $data['bank_account_code'], $data['statement_date'],
                (float)$data['statement_balance'],
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function getSession(int $id): void
    {
        try {
            JsonResponse::ok($this->recon->getSession($id));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    public function items(int $id): void
    {
        JsonResponse::ok($this->recon->getSessionItems($id));
    }

    public function unmatched(int $id): void
    {
        JsonResponse::ok($this->recon->getUnmatchedItems($id));
    }

    public function addStatementEntry(int $sessionId): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['type'])) {
            JsonResponse::error('amount, type required', 400);
            return;
        }
        try {
            $id = $this->recon->addStatementEntry(
                $sessionId, (float)$data['amount'],
                $data['description'] ?? '', $data['reference'] ?? '',
                $data['date'] ?? date('Y-m-d'), $data['type']
            );
            JsonResponse::ok(['id' => $id], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function autoMatch(int $sessionId): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['statement_item_id'], $data['book_item_id'])) {
            JsonResponse::error('statement_item_id, book_item_id required', 400);
            return;
        }
        try {
            $this->recon->manualMatch($sessionId, (int)$data['statement_item_id'], (int)$data['book_item_id']);
            JsonResponse::ok(['matched' => true]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function manualMatch(int $sessionId): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['statement_item_id'], $data['book_item_id'])) {
            JsonResponse::error('statement_item_id, book_item_id required', 400);
            return;
        }
        try {
            $this->recon->manualMatch($sessionId, (int)$data['statement_item_id'], (int)$data['book_item_id']);
            JsonResponse::ok(['matched' => true]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function addAdjustingEntry(int $sessionId): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['debit_account'], $data['credit_account'], $data['amount'])) {
            JsonResponse::error('debit_account, credit_account, amount required', 400);
            return;
        }
        try {
            $result = $this->recon->addAdjustingEntry(
                $sessionId, $data['debit_account'], $data['credit_account'],
                (float)$data['amount'], $data['description'] ?? 'Adjustment',
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function complete(int $sessionId): void
    {
        Auth::checkCsrf();
        try {
            JsonResponse::ok($this->recon->complete($sessionId));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function bankAccounts(): void
    {
        $all = $this->accountRepo->findAll();
        $bankAccounts = array_filter($all, fn($a) => str_starts_with($a->getCode(), '112'));
        JsonResponse::ok(array_map(fn($a) => [
            'code' => $a->getCode(), 'name' => $a->getName(),
            'balance' => $a->getBalance(),
        ], $bankAccounts));
    }
}

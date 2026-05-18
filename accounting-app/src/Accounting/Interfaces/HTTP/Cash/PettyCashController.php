<?php
namespace Accounting\Interfaces\HTTP\Cash;

use Accounting\Domain\Service\PettyCashService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class PettyCashController
{
    private PettyCashService $pettyCash;

    public function __construct(PettyCashService $pettyCash)
    {
        $this->pettyCash = $pettyCash;
    }

    public function funds(): void
    {
        JsonResponse::ok($this->pettyCash->getPettyCashFunds());
    }

    public function createFund(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_name'], $data['imprest_amount'])) {
            JsonResponse::error('fund_name, imprest_amount required', 400);
            return;
        }
        try {
            $result = $this->pettyCash->establishPettyCash(
                $data['fund_name'], (float)$data['imprest_amount'],
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function disburse(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_id'], $data['amount'])) {
            JsonResponse::error('fund_id, amount required', 400);
            return;
        }
        try {
            $result = $this->pettyCash->disbursePettyCash(
                $data['fund_id'], (float)$data['amount'],
                $data['description'] ?? 'Petty cash disbursement',
                $data['reference'] ?? uniqid('pc_'),
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function replenish(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_id'], $data['expense_account'], $data['total_amount'])) {
            JsonResponse::error('fund_id, expense_account, total_amount required', 400);
            return;
        }
        try {
            $result = $this->pettyCash->replenishPettyCash(
                $data['fund_id'], $data['expense_account'], (float)$data['total_amount'],
                $data['description'] ?? 'Petty cash replenishment',
                $data['reference'] ?? uniqid('pc_'),
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function closeFund(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_id'])) {
            JsonResponse::error('fund_id required', 400);
            return;
        }
        try {
            $result = $this->pettyCash->closePettyCash(
                $data['fund_id'], (float)($data['return_amount'] ?? 0),
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function transactions(string $fundId): void
    {
        JsonResponse::ok($this->pettyCash->getPettyCashTransactions($fundId));
    }
}

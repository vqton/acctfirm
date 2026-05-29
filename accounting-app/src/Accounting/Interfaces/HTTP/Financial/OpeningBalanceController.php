<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\OpeningBalanceService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class OpeningBalanceController
{
    private OpeningBalanceService $service;

    public function __construct(OpeningBalanceService $service) { $this->service = $service; }

    public function list(): void
    {
        Auth::requirePermission('system', 'read');
        $period = $_GET['period'] ?? null;
        JsonResponse::ok($this->service->getOpeningBalances($period));
    }

    public function set(): void
    {
        Auth::requirePermission('system', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $accountCode = $data['account_code'] ?? '';
        $period = $data['period'] ?? date('Y-m');
        $debitBalance = (float)($data['debit_balance'] ?? 0);
        $creditBalance = (float)($data['credit_balance'] ?? 0);
        if (empty($accountCode)) {
            JsonResponse::error('Vui lòng nhập mã tài khoản', 400);
            return;
        }
        try {
            $result = $this->service->setOpeningBalance($accountCode, $period, $debitBalance, $creditBalance, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function verify(string $accountCode, string $period): void
    {
        Auth::requirePermission('system', 'update');
        Auth::checkCsrf();
        try {
            $result = $this->service->verify($accountCode, $period, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function convert(): void
    {
        Auth::requirePermission('system', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y-m');
        try {
            $result = $this->service->convertToJournalEntry($period, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/opening_balances.php';
    }
}

<?php
namespace Accounting\Interfaces\HTTP\FixedAsset;

use Accounting\Domain\Service\FixedAssetService;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\Helpers;
use Accounting\Infrastructure\JsonResponse;

class LifecycleController
{
    private FixedAssetService $fixedAssetService;
    private AccountRepositoryInterface $accountRepo;
    private \PDO $pdo;

    public function __construct(
        FixedAssetService $fixedAssetService,
        AccountRepositoryInterface $accountRepo,
        \PDO $pdo
    ) {
        $this->fixedAssetService = $fixedAssetService;
        $this->accountRepo = $accountRepo;
        $this->pdo = $pdo;
    }

    public function acquire(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['code'], $data['name'], $data['original_cost'])) {
            JsonResponse::error('Vui lòng nhập mã, tên và nguyên giá TSCĐ');
            return;
        }
        try {
            $result = $this->fixedAssetService->recordAcquisition(
                $data,
                $data['acquisition_type'] ?? 'purchase_cash',
                $data['counterparty_account'] ?? '111',
                $data['created_by'] ?? $_SESSION['user_id'] ?? 'system',
                (float)($data['vat_amount'] ?? 0),
                $data['vat_account'] ?? '1332'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    public function dispose(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fixed_asset_id'])) {
            JsonResponse::error('Vui lòng nhập mã TSCĐ cần thanh lý');
            return;
        }
        try {
            $result = $this->fixedAssetService->recordDisposal(
                $data['fixed_asset_id'],
                $data['disposal_type'] ?? 'liquidation',
                (float)($data['proceeds'] ?? 0),
                $data['proceeds_account'] ?? null,
                (float)($data['costs'] ?? 0),
                $data['costs_account'] ?? null,
                $data['disposal_date'] ?? date('Y-m-d'),
                $data['created_by'] ?? $_SESSION['user_id'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    public function acquisitionView(): void
    {
        require __DIR__ . '/../../../../../public/views/fixed_asset_acquisition.php';
    }

    public function disposalView(): void
    {
        require __DIR__ . '/../../../../../public/views/fixed_asset_disposal.php';
    }
}

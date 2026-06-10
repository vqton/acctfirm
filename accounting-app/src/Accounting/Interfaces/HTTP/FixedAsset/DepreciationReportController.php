<?php
namespace Accounting\Interfaces\HTTP\FixedAsset;

use Accounting\Domain\Service\FixedAssetService;
use Accounting\Domain\Service\DepreciationBatchService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class DepreciationReportController
{
    private FixedAssetService $fixedAssetService;
    private DepreciationBatchService $batchService;
    private \PDO $pdo;

    public function __construct(
        FixedAssetService $fixedAssetService,
        DepreciationBatchService $batchService,
        \PDO $pdo
    ) {
        $this->fixedAssetService = $fixedAssetService;
        $this->batchService = $batchService;
        $this->pdo = $pdo;
    }

    // Sinh Mẫu 06-TSCĐ cho kỳ
    public function report(string $period): void
    {
        Auth::requirePermission('fixed_asset', 'read');
        try {
            $data = $this->batchService->generateReport($period);
            JsonResponse::ok($data);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // Lưu batch (sau khi chạy depreciation)
    public function saveBatch(): void
    {
        Auth::checkCsrf();
        $input = json_decode(file_get_contents('php://input'), true);
        $period = $input['period'] ?? date('Y-m');
        try {
            $id = $this->batchService->saveBatch($period, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok(['batch_id' => $id, 'period' => $period]);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // Xem batch đã lưu
    public function getBatch(string $period): void
    {
        Auth::requirePermission('fixed_asset', 'read');
        $batch = $this->batchService->loadBatch($period);
        if (!$batch) {
            JsonResponse::error('Không tìm thấy batch cho kỳ ' . $period, 404);
            return;
        }
        JsonResponse::ok($batch);
    }

    // Lịch sử các batch
    public function listBatches(): void
    {
        Auth::requirePermission('fixed_asset', 'read');
        $stmt = $this->pdo->query(
            "SELECT * FROM fa_depreciation_batches ORDER BY period DESC LIMIT 24"
        );
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}

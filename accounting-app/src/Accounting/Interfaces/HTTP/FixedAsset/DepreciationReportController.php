<?php
namespace Accounting\Interfaces\HTTP\FixedAsset;

use Accounting\Domain\Service\FixedAssetService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Báo cáo Khấu hao TSCĐ
 *
 * Mục đích nghiệp vụ:
 *   - Báo cáo chi tiết khấu hao từng tài sản cố định
 *   - Bảng tính khấu hao theo kỳ
 *   - Tổng hợp khấu hao theo loại TSCĐ
 *
 * API endpoints:
 *   GET /api/fixed-asset/depreciation/report — Báo cáo khấu hao
 *   GET /api/fixed-asset/depreciation/schedule — Lịch khấu hao
 *
 * Rủi ro:
 *   - Sai phương pháp khấu hao -> sai BC01 (214) và BC02 (642, 627)
 *
 * Tích hợp:
 *   - FixedAssetService đọc từ fixed_assets + depreciation_schedules
 */
class DepreciationReportController
{
    private FixedAssetService $service;

    public function __construct(FixedAssetService $service) { $this->service = $service; }

    /**
     * Báo cáo khấu hao tài sản cố định
     *
     * @return void
     */
    public function report(): void
    {
        Auth::requirePermission('fixed_asset', 'read');
        $period = $_GET['period'] ?? date('Y-m');
        $assetId = $_GET['asset_id'] ?? null;
        JsonResponse::ok($this->service->getDepreciationReport($period, $assetId));
    }

    /**
     * Lịch khấu hao chi tiết
     *
     * @return void
     */
    public function schedule(): void
    {
        Auth::requirePermission('fixed_asset', 'read');
        $assetId = $_GET['asset_id'] ?? '';
        if (!$assetId) { JsonResponse::error('Vui lòng nhập mã tài sản', 400); return; }
        JsonResponse::ok($this->service->getDepreciationSchedule($assetId));
    }
}

<?php
namespace Accounting\Interfaces\HTTP\FixedAsset;

use Accounting\Domain\Service\FixedAssetService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Vòng đời TSCĐ (Fixed Asset Lifecycle)
 *
 * Mục đích nghiệp vụ:
 *   - Ghi nhận tăng TSCĐ (mua sắm, xây dựng cơ bản, được cấp, nhận góp vốn)
 *   - Tính khấu hao TSCĐ theo tháng/quý/năm
 *   - Ghi nhận giảm TSCĐ (thanh lý, nhượng bán, điều chuyển)
 *   - Đánh giá lại TSCĐ
 *   - Kiểm kê TSCĐ
 *
 * API endpoints:
 *   POST /api/fixed-asset/acquisition — Ghi nhận tăng TSCĐ
 *   POST /api/fixed-asset/depreciation — Tính khấu hao
 *   POST /api/fixed-asset/disposal — Ghi nhận giảm TSCĐ
 *   POST /api/fixed-asset/revalue — Đánh giá lại
 *   POST /api/fixed-asset/inventory — Kiểm kê
 *
 * Rủi ro:
 *   - Sai nguyên giá -> sai khấu hao -> sai BC
 *   - Sai thời gian sử dụng -> sai mức khấu hao
 *   - R001: Không ghi nhận khấu hao nếu kỳ đã đóng
 *
 * Tích hợp:
 *   - FixedAssetService gọi JournalService cho bút toán
 *   - Hạch toán: Nợ 211/Có 331 (tăng), Nợ 642,627/Có 214 (khấu hao)
 */
class LifecycleController
{
    private FixedAssetService $service;

    public function __construct(FixedAssetService $service) { $this->service = $service; }

    /**
     * Ghi nhận tăng TSCĐ (mua sắm, xây dựng, được cấp)
     *
     * @return void
     */
    public function acquisition(): void
    {
        Auth::requirePermission('fixed_asset', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['name'], $data['cost'])) {
            JsonResponse::error('Vui lòng nhập tên và nguyên giá tài sản', 400);
            return;
        }
        try {
            $result = $this->service->recordAcquisition($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Tính và ghi nhận khấu hao TSCĐ trong kỳ
     *
     * @return void
     */
    public function depreciation(): void
    {
        Auth::requirePermission('fixed_asset', 'post');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y-m');
        try {
            $result = $this->service->calculateDepreciation($period, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Ghi nhận giảm TSCĐ (thanh lý, nhượng bán)
     *
     * @return void
     */
    public function disposal(): void
    {
        Auth::requirePermission('fixed_asset', 'delete');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['asset_id'])) {
            JsonResponse::error('Vui lòng nhập mã tài sản', 400);
            return;
        }
        try {
            $result = $this->service->recordDisposal($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Đánh giá lại TSCĐ
     *
     * @return void
     */
    public function revalue(): void
    {
        Auth::requirePermission('fixed_asset', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['asset_id'], $data['new_cost'])) {
            JsonResponse::error('Vui lòng nhập mã tài sản và nguyên giá mới', 400);
            return;
        }
        try {
            $result = $this->service->revalueAsset($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Kiểm kê TSCĐ
     *
     * @return void
     */
    public function inventory(): void
    {
        Auth::requirePermission('fixed_asset', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $result = $this->service->recordInventory($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }
}

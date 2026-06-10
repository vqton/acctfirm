<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Đánh giá lại hàng tồn kho (Impairment)
 *
 * Mục đích nghiệp vụ:
 *   - Đánh giá lại giá trị hàng tồn kho nếu giá thị trường giảm
 *   - Trích lập dự phòng giảm giá hàng tồn kho (TK 2294)
 *   - Tuân thủ VAS 02 và TT 48
 *
 * API endpoints:
 *   POST /api/inventory/impairment — Đánh giá và ghi nhận
 *   GET  /api/inventory/impairment/report — Báo cáo
 *
 * Rủi ro:
 *   - Sai giá thị trường -> sai dự phòng -> sai BC01
 *
 * Tích hợp:
 *   - InventoryService gọi JournalService
 */
class ImpairmentController
{
    private InventoryServiceInterface $inventory;

    public function __construct(InventoryServiceInterface $inventory) { $this->inventory = $inventory; }

    /**
     * Đánh giá và ghi nhận giảm giá trị hàng tồn kho
     *
     * @return void
     */
    public function assess(): void
    {
        Auth::requirePermission('inventory', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y-m');
        try {
            $result = $this->inventory->assessImpairment($period, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Báo cáo dự phòng giảm giá hàng tồn kho
     *
     * @return void
     */
    public function report(): void
    {
        Auth::requirePermission('inventory', 'read');
        $period = $_GET['period'] ?? date('Y-m');
        JsonResponse::ok($this->inventory->getImpairmentReport($period));
    }
}

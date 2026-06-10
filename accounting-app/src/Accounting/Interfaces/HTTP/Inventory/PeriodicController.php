<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Kiểm kê định kỳ (Periodic Inventory)
 *
 * Mục đích nghiệp vụ:
 *   - Thực hiện kiểm kê hàng tồn kho định kỳ
 *   - So sánh tồn kho thực tế vs sổ sách
 *   - Ghi nhận chênh lệch thừa/thiếu
 *
 * API endpoints:
 *   POST /api/inventory/periodic — Tạo phiên kiểm kê
 *   POST /api/inventory/periodic/{id}/confirm — Xác nhận kết quả
 *   GET  /api/inventory/periodic/{id} — Chi tiết
 *
 * Rủi ro:
 *   - Không xử lý chênh lệch -> sai tồn kho
 *
 * Tích hợp:
 *   - InventoryService ghi nhận điều chỉnh
 */
class PeriodicController
{
    private InventoryServiceInterface $inventory;

    public function __construct(InventoryServiceInterface $inventory) { $this->inventory = $inventory; }

    /**
     * Tạo phiên kiểm kê mới
     *
     * @return void
     */
    public function create(): void
    {
        Auth::requirePermission('inventory', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['warehouse_id'])) {
            JsonResponse::error('Vui lòng nhập kho', 400);
            return;
        }
        try {
            $result = $this->inventory->startPeriodicCount($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Xác nhận kết quả kiểm kê
     *
     * @param string $id ID phiên kiểm kê
     * @return void
     */
    public function confirm(string $id): void
    {
        Auth::requirePermission('inventory', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $result = $this->inventory->confirmPeriodicCount($id, $data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Chi tiết phiên kiểm kê
     *
     * @param string $id ID phiên kiểm kê
     * @return void
     */
    public function get(string $id): void
    {
        Auth::requirePermission('inventory', 'read');
        try {
            JsonResponse::ok($this->inventory->getPeriodicCount($id));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }
}

<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Kiểm kê thực tế (Physical Count)
 *
 * Mục đích nghiệp vụ:
 *   - Nhập số lượng kiểm kê thực tế cho từng mặt hàng
 *   - So sánh với số lượng sổ sách
 *   - Ghi nhận điều chỉnh chênh lệch
 *
 * API endpoints:
 *   POST /api/inventory/physical-count — Ghi nhận kiểm kê
 *   GET  /api/inventory/physical-count/{sessionId} — Kết quả
 *
 * Rủi ro:
 *   - Nhập sai số lượng thực tế -> điều chỉnh sai
 *
 * Tích hợp:
 *   - PeriodicController quản lý phiên kiểm kê
 */
class PhysicalCountController
{
    private InventoryServiceInterface $inventory;

    public function __construct(InventoryServiceInterface $inventory) { $this->inventory = $inventory; }

    /**
     * Ghi nhận kết quả kiểm kê thực tế
     *
     * @return void
     */
    public function record(): void
    {
        Auth::requirePermission('inventory', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['session_id'], $data['items'])) {
            JsonResponse::error('Vui lòng nhập phiên kiểm kê và danh sách hàng', 400);
            return;
        }
        try {
            $result = $this->inventory->recordPhysicalCount($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Kết quả kiểm kê của một phiên
     *
     * @param string $sessionId ID phiên kiểm kê
     * @return void
     */
    public function get(string $sessionId): void
    {
        Auth::requirePermission('inventory', 'read');
        try {
            JsonResponse::ok($this->inventory->getPhysicalCountResult($sessionId));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }
}

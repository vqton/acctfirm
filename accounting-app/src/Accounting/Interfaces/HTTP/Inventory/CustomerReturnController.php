<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Hàng bán trả lại (Customer Return)
 *
 * Mục đích nghiệp vụ:
 *   - Xử lý hàng bán bị trả lại từ khách hàng
 *   - Nhập kho hàng trả lại
 *   - Điều chỉnh giảm doanh thu và công nợ
 *
 * API endpoints:
 *   GET  /api/inventory/customer-returns — Danh sách
 *   POST /api/inventory/customer-returns — Tạo mới
 *
 * Rủi ro:
 *   - Sai TK hạch toán -> sai doanh thu, sai tồn kho
 *   - Không điều chỉnh thuế GTGT
 *
 * Tích hợp:
 *   - ArService xử lý ghi giảm công nợ
 *   - InventoryService nhập kho
 */
class CustomerReturnController
{
    private InventoryServiceInterface $inventory;

    public function __construct(InventoryServiceInterface $inventory) { $this->inventory = $inventory; }

    /**
     * Danh sách hàng bán trả lại
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('inventory', 'read');
        JsonResponse::ok($this->inventory->getCustomerReturns());
    }

    /**
     * Ghi nhận hàng bán trả lại
     *
     * @return void
     */
    public function create(): void
    {
        Auth::requirePermission('inventory', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'])) {
            JsonResponse::error('Vui lòng nhập mã hàng và số lượng', 400);
            return;
        }
        try {
            $result = $this->inventory->recordCustomerReturn($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }
}

<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Trả lại hàng cho nhà cung cấp (Return to Supplier)
 *
 * Mục đích nghiệp vụ:
 *   - Xử lý hàng mua trả lại cho NCC
 *   - Xuất kho hàng trả lại
 *   - Điều chỉnh giảm công nợ NCC
 *
 * API endpoints:
 *   POST /api/inventory/returns — Tạo mới
 *   GET  /api/inventory/returns — Danh sách
 *
 * Rủi ro:
 *   - Sai TK hạch toán -> sai tồn kho, sai công nợ
 *   - Không điều chỉnh VAT đầu vào
 *
 * Tích hợp:
 *   - ApService ghi giảm công nợ
 *   - InventoryService xuất kho
 */
class ReturnToSupplierController
{
    private InventoryServiceInterface $inventory;

    public function __construct(InventoryServiceInterface $inventory) { $this->inventory = $inventory; }

    /**
     * Ghi nhận trả lại hàng cho NCC
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
            $result = $this->inventory->recordReturnToSupplier($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Danh sách hàng trả lại NCC
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('inventory', 'read');
        JsonResponse::ok($this->inventory->getReturnsToSupplier());
    }
}

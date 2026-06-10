<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Hàng gửi bán (Consignment)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý hàng gửi bán đại lý (TK 157)
 *   - Ghi nhận gửi hàng và xác nhận bán được
 *
 * API endpoints:
 *   GET  /api/inventory/consignment — Danh sách
 *   POST /api/inventory/consignment — Gửi hàng
 *   POST /api/inventory/consignment/{id}/confirm — Xác nhận bán được
 *
 * Rủi ro:
 *   - Sai hạch toán 157 -> sai tồn kho
 *
 * Tích hợp:
 *   - InventoryService gọi JournalService
 *   - Order Processing module
 */
class ConsignmentController
{
    private InventoryServiceInterface $inventory;

    public function __construct(InventoryServiceInterface $inventory) { $this->inventory = $inventory; }

    /**
     * Danh sách hàng gửi bán
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('inventory', 'read');
        JsonResponse::ok($this->inventory->getConsignmentList());
    }

    /**
     * Ghi nhận gửi hàng bán đại lý
     *
     * @return void
     */
    public function send(): void
    {
        Auth::requirePermission('inventory', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'])) {
            JsonResponse::error('Vui lòng nhập mã hàng và số lượng', 400);
            return;
        }
        try {
            $result = $this->inventory->sendConsignment($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Xác nhận hàng gửi bán đã bán được
     *
     * @param string $id Mã giao dịch gửi hàng
     * @return void
     */
    public function confirm(string $id): void
    {
        Auth::requirePermission('inventory', 'create');
        Auth::checkCsrf();
        try {
            $result = $this->inventory->confirmConsignmentSale($id, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }
}

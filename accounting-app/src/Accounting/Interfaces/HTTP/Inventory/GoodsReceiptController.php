<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Service\GoodsReceiptService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Nhập kho chi tiết (Goods Receipt Detail)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý phiếu nhập kho chi tiết
 *   - Multi-line nhập kho
 *   - Lifecycle: draft -> posted -> cancelled
 *
 * API endpoints:
 *   POST /api/inventory/goods-receipts — Tạo mới
 *   POST /api/inventory/goods-receipts/{id}/post  — Ghi sổ
 *   POST /api/inventory/goods-receipts/{id}/cancel — Hủy
 *   GET  /api/inventory/goods-receipts/list — Danh sách
 *
 * Rủi ro:
 *   - R007: Nhập kho không ghi nhận bút toán
 *
 * Tích hợp:
 *   - GoodsReceiptService gọi JournalService
 */
class GoodsReceiptController
{
    private GoodsReceiptService $service;

    public function __construct(GoodsReceiptService $service) { $this->service = $service; }

    /**
     * Tạo phiếu nhập kho mới
     *
     * @return void
     */
    public function create(): void
    {
        Auth::requirePermission('inventory', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['lines'])) {
            JsonResponse::error('Vui lòng nhập danh sách hàng nhập', 400);
            return;
        }
        try {
            $result = $this->service->createDraft($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Ghi sổ phiếu nhập kho
     *
     * @param string $id ID phiếu nhập
     * @return void
     */
    public function post(string $id): void
    {
        Auth::requirePermission('inventory', 'create');
        Auth::checkCsrf();
        try {
            $result = $this->service->postReceipt($id, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Hủy phiếu nhập kho
     *
     * @param string $id ID phiếu nhập
     * @return void
     */
    public function cancel(string $id): void
    {
        Auth::requirePermission('inventory', 'delete');
        Auth::checkCsrf();
        try {
            $result = $this->service->cancelDraft($id, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Danh sách phiếu nhập kho
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('inventory', 'read');
        $status = $_GET['status'] ?? null;
        $limit = (int)($_GET['limit'] ?? 50);
        JsonResponse::ok($this->service->listReceipts($status, $limit));
    }
}

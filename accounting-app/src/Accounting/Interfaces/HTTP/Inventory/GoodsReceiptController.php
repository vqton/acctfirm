<?php
declare(strict_types=1);
// PHIEU NHAP KHO — Mẫu 01-VT Controller
// API endpoints cho full lifecycle: draft → posted → cancelled
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Service\GoodsReceiptService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class GoodsReceiptController
{
    private GoodsReceiptService $service;

    public function __construct(GoodsReceiptService $service)
    {
        $this->service = $service;
    }

    // TAO MOI PHIEU NHAP KHO (draft)
    // POST /api/goods-receipt/draft
    public function createDraft(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['lines'])) {
            JsonResponse::error('Vui lòng nhập thông tin phiếu nhập kho và danh sách hàng hóa', 400);
            return;
        }
        try {
            $result = $this->service->createDraft(
                $data['po_id'] ?? null,
                $data['supplier_name'] ?? null,
                $data['supplier_address'] ?? null,
                $data['receipt_type'] ?? 'purchase',
                $data['warehouse_id'] ?? null,
                $data['received_date'] ?? date('Y-m-d'),
                $data['department'] ?? null,
                $data['note'] ?? null,
                $data['lines'],
                $_SESSION['user_id'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // GHI SO PHIEU NHAP KHO
    // POST /api/goods-receipt/{id}/post
    public function postReceipt(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'post');
        try {
            $result = $this->service->postReceipt($id, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // HUY PHIEU NHAP KHO
    // POST /api/goods-receipt/{id}/cancel
    public function cancelReceipt(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'delete');
        try {
            $result = $this->service->cancelReceipt($id, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // LAY CHI TIET
    // GET /api/goods-receipt/{id}
    public function getDetail(string $id): void
    {
        Auth::requirePermission('inventory', 'read');
        try {
            JsonResponse::ok($this->service->getReceipt($id));
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    // DANH SACH
    // GET /api/goods-receipt/list
    public function list(): void
    {
        Auth::requirePermission('inventory', 'read');
        $status = $_GET['status'] ?? null;
        $limit = (int)($_GET['limit'] ?? 50);
        JsonResponse::ok($this->service->listReceipts($status, $limit));
    }

    // VIEW
    public function viewIndex(): void
    {
        require __DIR__ . '/../../../../public/views/goods_receipt.php';
    }
}

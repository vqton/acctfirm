<?php
declare(strict_types=1);
namespace Accounting\Interfaces\HTTP\Sales;

use Accounting\Domain\Service\SalesOrderService;
use Accounting\Domain\Repository\SalesOrderRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Đơn bán hàng (Sales Orders)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý đơn bán hàng, báo giá
 *   - Theo dõi trạng thái đơn hàng, xác nhận, giao hàng
 *   - Tích hợp với xuất kho, ghi nhận doanh thu
 *
 * API endpoints:
 *   GET    /api/sales-orders       — Danh sách
 *   GET    /api/sales-orders/{id}  — Chi tiết
 *   POST   /api/sales-orders       — Tạo đơn hàng
 *   PUT    /api/sales-orders/{id}  — Cập nhật
 *   POST   /api/sales-orders/{id}/approve  — Xác nhận
 *   POST   /api/sales-orders/{id}/cancel   — Hủy
 *
 * Rủi ro:
 *   - Ghi nhận doanh thu sai thời điểm
 *
 * Tích hợp:
 *   - ArController xử lý công nợ
 *   - InventoryController xuất kho
 */
class SalesOrderController
{
    private SalesOrderService $soService;
    private SalesOrderRepositoryInterface $soRepo;

    /**
     * @param SalesOrderService $soService
     * @param SalesOrderRepositoryInterface $soRepo
     */
    public function __construct(SalesOrderService $soService, SalesOrderRepositoryInterface $soRepo)
    {
        $this->soService = $soService;
        $this->soRepo = $soRepo;
    }

    /**
     * Danh sách đơn bán hàng
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('sales', 'read');
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = ($page - 1) * $limit;
        $orders = [];
        foreach ($this->soRepo->findAll($limit, $offset) as $o) {
            $orders[] = $o->toArray();
        }
        JsonResponse::ok($orders);
    }

    /**
     * Chi tiết đơn bán hàng
     *
     * @param string $id
     * @return void
     */
    public function get(string $id): void
    {
        Auth::requirePermission('sales', 'read');
        $order = $this->soRepo->findById($id);
        if (!$order) { JsonResponse::error('Không tìm thấy đơn hàng', 404); return; }
        JsonResponse::ok($order->toArray());
    }

    /**
     * Tạo đơn bán hàng mới
     *
     * @return void
     */
    public function create(): void
    {
        Auth::requirePermission('sales', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['customer_id']) || !isset($data['lines'])) {
            JsonResponse::error('Vui lòng nhập khách hàng và danh sách mặt hàng', 400);
            return;
        }
        try {
            $order = $this->soService->createOrder($data);
            JsonResponse::ok($order, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Cập nhật đơn bán hàng
     *
     * @param string $id
     * @return void
     */
    public function update(string $id): void
    {
        Auth::requirePermission('sales', 'edit');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { JsonResponse::error('Không có dữ liệu', 400); return; }
        try {
            $this->soService->updateOrder($id, $data);
            JsonResponse::ok(['message' => 'Đã cập nhật đơn hàng']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Xác nhận đơn bán hàng
     *
     * @param string $id
     * @return void
     */
    public function approve(string $id): void
    {
        Auth::requirePermission('sales', 'approve');
        Auth::checkCsrf();
        try {
            $this->soService->approveOrder($id, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok(['message' => 'Đã xác nhận đơn hàng']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Hủy đơn bán hàng
     *
     * @param string $id
     * @return void
     */
    public function cancel(string $id): void
    {
        Auth::requirePermission('sales', 'edit');
        Auth::checkCsrf();
        try {
            $this->soService->cancelOrder($id, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok(['message' => 'Đã hủy đơn hàng']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }
}

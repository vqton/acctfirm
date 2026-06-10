<?php
declare(strict_types=1);
namespace Accounting\Interfaces\HTTP\Sales;

use Accounting\Domain\Service\SalesOrderService;
use Accounting\Domain\Repository\SalesOrderRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Bán hàng — Đơn đặt hàng (Sales Order / O2C Lifecycle)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý toàn bộ vòng đời đơn hàng bán: tạo → xác nhận → giao hàng → xuất hóa đơn → thu tiền
 *   - Tra cứu, tìm kiếm, xuất báo cáo đơn hàng
 *   - Dashboard tổng quan tình hình bán hàng
 *
 * API endpoints:
 *   GET    /api/sales/orders              — Danh sách (phân trang)
 *   POST   /api/sales/orders              — Tạo đơn mới
 *   GET    /api/sales/orders/search       — Tìm kiếm nâng cao
 *   GET    /api/sales/orders/dashboard    — Thống kê tổng quan
 *   GET    /api/sales/orders/export       — Xuất báo cáo (csv/xls/pdf)
 *   GET    /api/sales/orders/:id          — Chi tiết đơn hàng
 *   PUT    /api/sales/orders/:id          — Sửa đơn hàng (chỉ draft)
 *   DELETE /api/sales/orders/:id          — Xóa đơn hàng (chỉ draft/cancelled)
 *   POST   /api/sales/orders/:id/confirm  — Xác nhận đơn hàng
 *   POST   /api/sales/orders/:id/ship     — Giao hàng (ghi nhận xuất kho)
 *   POST   /api/sales/orders/:id/invoice  — Xuất hóa đơn
 *   POST   /api/sales/orders/:id/payment  — Thu tiền
 *   POST   /api/sales/orders/:id/cancel   — Hủy đơn hàng
 *   GET    /api/sales/orders/:id/links    — Liên kết chứng từ liên quan
 *
 * View routes:
 *   GET /ban/don-dat-hang          — Trang danh sách
 *   GET /ban/don-dat-hang/them     — Trang tạo mới
 *   GET /ban/don-dat-hang/:id      — Trang chi tiết/sửa
 *
 * Tích hợp:
 *   - SalesOrderService (xử lý nghiệp vụ)
 *   - SalesOrderRepositoryInterface (truy xuất dữ liệu)
 *   - JournalService (ghi nhận bút toán khi xuất hóa đơn/thu tiền)
 *   - InventoryService (xuất kho khi giao hàng)
 *
 * Rủi ro:
 *   - Không kiểm tra tồn kho trước khi giao hàng → xuất kho âm
 *   - Sai số tiền thu → sai công nợ phải thu (TK 131)
 *   - Sai thời điểm ghi nhận doanh thu → BCTC sai kỳ
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
     * Danh sách đơn hàng (phân trang)
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
     * Chi tiết đơn hàng
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
     * Tạo đơn hàng mới
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
            $order = $this->soService->createOrder($data, Auth::currentUser());
            JsonResponse::ok($order->toArray(), 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Cập nhật đơn hàng (chỉ draft)
     *
     * @param string $id
     * @return void
     */
    public function update(string $id): void
    {
        Auth::requirePermission('sales', 'update');
        Auth::checkCsrf();
        $order = $this->soRepo->findById($id);
        if (!$order) { JsonResponse::error('Không tìm thấy đơn hàng', 404); return; }
        if ($order->getStatus() !== 'draft') {
            JsonResponse::error('Chỉ có thể sửa đơn hàng ở trạng thái nháp', 400);
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $updated = $this->soService->createOrder(
                array_merge($data, ['customer_id' => $order->getCustomerId()]),
                Auth::currentUser()
            );
            $this->soRepo->delete($id);
            JsonResponse::ok($updated->toArray());
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Xóa đơn hàng (chỉ draft hoặc cancelled)
     *
     * @param string $id
     * @return void
     */
    public function delete(string $id): void
    {
        Auth::requirePermission('sales', 'delete');
        Auth::checkCsrf();
        $order = $this->soRepo->findById($id);
        if (!$order) { JsonResponse::error('Không tìm thấy đơn hàng', 404); return; }
        if ($order->getStatus() !== 'draft' && $order->getStatus() !== 'cancelled') {
            JsonResponse::error('Chỉ có thể xóa đơn hàng nháp hoặc đã hủy', 400);
            return;
        }
        $this->soRepo->delete($id);
        JsonResponse::ok(['message' => 'Đã xóa đơn hàng']);
    }

    /**
     * Xác nhận đơn hàng (chuyển từ draft → confirmed)
     *
     * @param string $id
     * @return void
     */
    public function confirm(string $id): void
    {
        Auth::requirePermission('sales', 'approve');
        Auth::checkCsrf();
        try {
            $order = $this->soService->confirmOrder($id, Auth::currentUser());
            JsonResponse::ok($order->toArray());
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Giao hàng (ghi nhận xuất kho)
     *
     * @param string $id
     * @return void
     */
    public function ship(string $id): void
    {
        Auth::requirePermission('sales', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $order = $this->soService->shipOrder($id, (float)($data['qty'] ?? 1), Auth::currentUser());
            JsonResponse::ok($order->toArray());
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Xuất hóa đơn cho đơn hàng
     *
     * @param string $id
     * @return void
     */
    public function invoice(string $id): void
    {
        Auth::requirePermission('sales', 'update');
        Auth::checkCsrf();
        try {
            $order = $this->soService->invoiceOrder($id, Auth::currentUser());
            JsonResponse::ok($order->toArray());
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            JsonResponse::error('Lỗi khi xuất hóa đơn: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Thu tiền từ đơn hàng
     *
     * @param string $id
     * @return void
     */
    public function receivePayment(string $id): void
    {
        Auth::requirePermission('cash', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $order = $this->soService->receivePayment(
                $id, (float)($data['amount'] ?? 0),
                $data['method'] ?? 'cash', Auth::currentUser()
            );
            JsonResponse::ok($order->toArray());
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Hủy đơn hàng
     *
     * @param string $id
     * @return void
     */
    public function cancel(string $id): void
    {
        Auth::requirePermission('sales', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $order = $this->soService->cancelOrder($id, $data['reason'] ?? '', Auth::currentUser());
            JsonResponse::ok($order->toArray());
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Tìm kiếm nâng cao đơn hàng
     *
     * @return void
     */
    public function search(): void
    {
        Auth::requirePermission('sales', 'read');
        $orders = $this->soService->searchOrders(
            isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null,
            $_GET['status'] ?? null, $_GET['date_from'] ?? null,
            $_GET['date_to'] ?? null, $_GET['keyword'] ?? '',
            (int)($_GET['limit'] ?? 50), (int)((($_GET['page'] ?? 1) - 1) * 50)
        );
        JsonResponse::ok($orders);
    }

    /**
     * Dashboard tổng quan bán hàng
     *
     * @return void
     */
    public function dashboard(): void
    {
        Auth::requirePermission('sales', 'read');
        $stats = $this->soService->getDashboardStats();
        $recent = [];
        foreach ($this->soRepo->findAll(10, 0) as $o) {
            $recent[] = $o->toArray();
        }
        JsonResponse::ok(['stats' => $stats, 'recent' => $recent]);
    }

    /**
     * Liên kết chứng từ liên quan (hóa đơn, phiếu thu, phiếu xuất kho...)
     *
     * @param string $id
     * @return void
     */
    public function links(string $id): void
    {
        Auth::requirePermission('sales', 'read');
        JsonResponse::ok($this->soService->getLinks($id));
    }

    /**
     * Xuất báo cáo đơn hàng (csv, xls, pdf)
     *
     * @return void
     */
    public function export(): void
    {
        Auth::requirePermission('sales', 'read');
        $format = $_GET['format'] ?? 'csv';
        $result = $this->soService->exportSalesOrders($format, $_GET);
        header('Content-Type: ' . $result['mime']);
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        echo $result['content'];
    }

    /**
     * View: danh sách đơn hàng
     *
     * @return void
     */
    public function viewIndex(): void
    {
        Auth::requirePermission('sales', 'read');
        ob_start();
        require __DIR__ . '/../../../../public/views/sales-orders.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../../../public/views/layout.php';
    }

    /**
     * View: tạo mới / chi tiết đơn hàng
     *
     * @param string|null $id
     * @return void
     */
    public function viewForm(?string $id = null): void
    {
        Auth::requirePermission('sales', 'read');
        $order = $id ? $this->soRepo->findById($id) : null;
        ob_start();
        require __DIR__ . '/../../../../public/views/sales-order-form.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../../../public/views/layout.php';
    }
}

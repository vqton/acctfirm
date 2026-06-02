<?php
declare(strict_types=1);
namespace Accounting\Interfaces\HTTP\Sales;

use Accounting\Domain\Service\SalesOrderService;
use Accounting\Domain\Repository\SalesOrderRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class SalesOrderController
{
    private SalesOrderService $soService;
    private SalesOrderRepositoryInterface $soRepo;

    public function __construct(SalesOrderService $soService, SalesOrderRepositoryInterface $soRepo)
    {
        $this->soService = $soService;
        $this->soRepo = $soRepo;
    }

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

    public function get(string $id): void
    {
        Auth::requirePermission('sales', 'read');
        $order = $this->soRepo->findById($id);
        if (!$order) { JsonResponse::error('Không tìm thấy đơn hàng', 404); return; }
        JsonResponse::ok($order->toArray());
    }

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

    public function links(string $id): void
    {
        Auth::requirePermission('sales', 'read');
        JsonResponse::ok($this->soService->getLinks($id));
    }

    public function export(): void
    {
        Auth::requirePermission('sales', 'read');
        $format = $_GET['format'] ?? 'csv';
        $result = $this->soService->exportSalesOrders($format, $_GET);
        header('Content-Type: ' . $result['mime']);
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        echo $result['content'];
    }

    public function viewIndex(): void
    {
        Auth::requirePermission('sales', 'read');
        ob_start();
        require __DIR__ . '/../../../../public/views/sales-orders.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../../../public/views/layout.php';
    }

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

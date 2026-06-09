<?php
declare(strict_types=1);
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\ManufacturingService;
use Accounting\Domain\Repository\BomRepositoryInterface;
use Accounting\Domain\Repository\ProductionOrderRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class ManufacturingController
{
    private ManufacturingService $service;
    private BomRepositoryInterface $bomRepo;
    private ProductionOrderRepositoryInterface $poRepo;

    public function __construct(
        ManufacturingService $service,
        BomRepositoryInterface $bomRepo,
        ProductionOrderRepositoryInterface $poRepo
    ) {
        $this->service = $service;
        $this->bomRepo = $bomRepo;
        $this->poRepo = $poRepo;
    }

    // === BOM ===
    public function listBom(): void
    {
        Auth::requirePermission('manufacturing', 'read');
        $items = [];
        foreach ($this->bomRepo->findAll() as $b) $items[] = $b->toArray();
        JsonResponse::ok($items);
    }

    public function getBom(string $id): void
    {
        Auth::requirePermission('manufacturing', 'read');
        try {
            JsonResponse::ok($this->service->getBomDetails($id));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    public function createBom(): void
    {
        Auth::requirePermission('manufacturing', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['product_id']) || !isset($data['effective_date']) || !isset($data['lines'])) {
            JsonResponse::error('Vui lòng nhập sản phẩm, ngày hiệu lực và định mức', 400);
            return;
        }
        try {
            $bom = $this->service->createBom(
                $data['product_id'], $data['effective_date'], $data['lines'],
                $data['notes'] ?? null, Auth::currentUser()
            );
            JsonResponse::ok($bom->toArray(), 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function activateBom(string $id): void
    {
        Auth::requirePermission('manufacturing', 'update');
        Auth::checkCsrf();
        try {
            $this->service->activateBom($id);
            JsonResponse::ok(['message' => 'Đã kích hoạt BOM']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // === PRODUCTION ORDERS ===
    public function listOrders(): void
    {
        Auth::requirePermission('manufacturing', 'read');
        $items = [];
        foreach ($this->poRepo->findAll() as $po) $items[] = $po->toArray();
        JsonResponse::ok($items);
    }

    public function getOrder(string $id): void
    {
        Auth::requirePermission('manufacturing', 'read');
        try {
            JsonResponse::ok($this->service->getProductionReport($id));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    public function createOrder(): void
    {
        Auth::requirePermission('manufacturing', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['product_id']) || !isset($data['qty'])) {
            JsonResponse::error('Vui lòng nhập sản phẩm và số lượng', 400);
            return;
        }
        try {
            $po = $this->service->createProductionOrder(
                $data['product_id'], (float)$data['qty'], $data['bom_id'] ?? null,
                $data['start_date'] ?? null, $data['due_date'] ?? null, Auth::currentUser()
            );
            JsonResponse::ok($po->toArray(), 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function releaseOrder(string $id): void
    {
        Auth::requirePermission('manufacturing', 'update');
        Auth::checkCsrf();
        try {
            $this->service->releaseProductionOrder($id);
            JsonResponse::ok(['message' => 'Đã release lệnh SX']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function issueMaterial(string $id): void
    {
        Auth::requirePermission('manufacturing', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['material_id']) || !isset($data['qty']) || !isset($data['unit_cost'])) {
            JsonResponse::error('Vui lòng nhập vật tư, số lượng và đơn giá', 400);
            return;
        }
        try {
            $this->service->issueMaterial(
                $id, $data['material_id'], (float)$data['qty'], (float)$data['unit_cost'],
                (float)$data['qty'] * (float)$data['unit_cost'], $data['transaction_id'] ?? null
            );
            JsonResponse::ok(['message' => 'Đã xuất NVL cho lệnh SX']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function recordLabor(string $id): void
    {
        Auth::requirePermission('manufacturing', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['hours']) || !isset($data['rate'])) {
            JsonResponse::error('Vui lòng nhập số giờ và đơn giá', 400);
            return;
        }
        try {
            $this->service->recordLabor(
                $id, $data['labor_type'] ?? 'direct', (float)$data['hours'],
                (float)$data['rate'], $data['transaction_id'] ?? null
            );
            JsonResponse::ok(['message' => 'Đã ghi nhận nhân công']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function recordOverhead(string $id): void
    {
        Auth::requirePermission('manufacturing', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['type']) || !isset($data['base']) || !isset($data['rate'])) {
            JsonResponse::error('Vui lòng nhập loại, cơ sở và tỷ lệ', 400);
            return;
        }
        try {
            $this->service->recordOverhead($id, $data['type'], (float)$data['base'], (float)$data['rate']);
            JsonResponse::ok(['message' => 'Đã ghi nhận CPSXC']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function completeOrder(string $id): void
    {
        Auth::requirePermission('manufacturing', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['completed_qty'])) {
            JsonResponse::error('Vui lòng nhập số lượng hoàn thành', 400);
            return;
        }
        try {
            $this->service->completeProductionOrder(
                $id, (float)$data['completed_qty'], $data['end_date'] ?? date('Y-m-d')
            );
            JsonResponse::ok(['message' => 'Đã hoàn thành lệnh SX']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function calculateCost(string $id): void
    {
        Auth::requirePermission('manufacturing', 'update');
        Auth::checkCsrf();
        try {
            $result = $this->service->calculateCost($id);
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function closeOrder(string $id): void
    {
        Auth::requirePermission('manufacturing', 'delete');
        Auth::checkCsrf();
        try {
            $this->service->closeProductionOrder($id);
            JsonResponse::ok(['message' => 'Đã đóng lệnh SX']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function dashboard(): void
    {
        Auth::requirePermission('manufacturing', 'read');
        JsonResponse::ok($this->service->getDashboard());
    }

    public function exportReport(string $id): void
    {
        Auth::requirePermission('manufacturing', 'read');
        $result = $this->service->exportReport($_GET['format'] ?? 'csv', $id);
        header('Content-Type: ' . $result['mime']);
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        echo $result['content'];
    }

    public function viewIndex(): void
    {
        Auth::requirePermission('manufacturing', 'read');
        require __DIR__ . '/../../../../public/views/manufacturing.php';
    }
}

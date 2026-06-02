<?php
declare(strict_types=1);
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\BomRepositoryInterface;
use Accounting\Domain\Repository\ProductionOrderRepositoryInterface;
use Accounting\Domain\Model\Bom;
use Accounting\Domain\Model\ProductionOrder;
use PDO;

class ManufacturingService
{
    private BomRepositoryInterface $bomRepo;
    private ProductionOrderRepositoryInterface $poRepo;
    private PDO $pdo;
    private ReportExportService $export;
    private JournalService $journalService;
    private string $userId;

    public function __construct(
        BomRepositoryInterface $bomRepo,
        ProductionOrderRepositoryInterface $poRepo,
        PDO $pdo,
        ReportExportService $export,
        JournalService $journalService
    ) {
        $this->bomRepo = $bomRepo;
        $this->poRepo = $poRepo;
        $this->pdo = $pdo;
        $this->export = $export;
        $this->journalService = $journalService;
    }

    // === BOM ===
    public function createBom(string $productId, string $effectiveDate, array $lines, ?string $notes = null, ?string $createdBy = null): Bom
    {
        $existing = $this->bomRepo->findByProduct($productId);
        $version = count($existing) + 1;
        $id = uniqid('bom_');
        $bom = new Bom($id, $productId, $version, $effectiveDate, $notes, $createdBy);
        $bom->setLines($lines);
        $this->bomRepo->save($bom);
        return $bom;
    }

    public function activateBom(string $id): void
    {
        $bom = $this->bomRepo->findById($id);
        if (!$bom) throw new \InvalidArgumentException('Không tìm thấy BOM');
        $bom->setStatus('active');
        $this->bomRepo->save($bom);
    }

    public function getBomDetails(string $id): array
    {
        $bom = $this->bomRepo->findById($id);
        if (!$bom) throw new \InvalidArgumentException('Không tìm thấy BOM');
        return $bom->toArray();
    }

    // === PRODUCTION ORDERS ===
    public function createProductionOrder(string $productId, float $qty, ?string $bomId = null, ?string $startDate = null, ?string $dueDate = null, ?string $createdBy = null): ProductionOrder
    {
        $year = date('Y');
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM production_orders WHERE reference LIKE 'PO{$year}-%'");
        $stmt->execute();
        $count = (int)$stmt->fetchColumn() + 1;
        $reference = sprintf("PO%s-%06d", $year, $count);

        $id = uniqid('po_');
        $po = new ProductionOrder($id, $reference, $productId, $qty, $startDate, $dueDate, $bomId);
        $po->setCreatedBy($createdBy);
        $this->poRepo->save($po);
        return $po;
    }

    public function releaseProductionOrder(string $id): void
    {
        $po = $this->poRepo->findById($id);
        if (!$po) throw new \InvalidArgumentException('Không tìm thấy lệnh SX');
        if ($po->getStatus() !== 'draft') throw new \InvalidArgumentException('Chỉ release lệnh SX ở trạng thái nháp');
        $po->setStatus('released');
        $this->poRepo->save($po);
    }

    public function issueMaterial(string $poId, string $materialId, float $qty, float $unitCost, float $totalCost, ?string $txnId = null): void
    {
        $po = $this->poRepo->findById($poId);
        if (!$po) throw new \InvalidArgumentException('Không tìm thấy lệnh SX');

        $id = uniqid('pom_');
        $stmt = $this->pdo->prepare(
            'INSERT INTO production_order_materials (id,production_order_id,material_id,planned_qty,actual_qty,unit_cost,total_cost,transaction_id)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$id, $poId, $materialId, $qty, $qty, $unitCost, $totalCost, $txnId]);

        $po->setMaterialCost($po->getMaterialCost() + $totalCost);
        $this->poRepo->save($po);
    }

    public function recordLabor(string $poId, string $laborType, float $hours, float $rate, ?string $txnId = null): void
    {
        $po = $this->poRepo->findById($poId);
        if (!$po) throw new \InvalidArgumentException('Không tìm thấy lệnh SX');

        $total = $hours * $rate;
        $id = uniqid('pol_');
        $stmt = $this->pdo->prepare(
            'INSERT INTO production_order_labor (id,production_order_id,labor_type,actual_hours,hourly_rate,total_cost,transaction_id)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([$id, $poId, $laborType, $hours, $rate, $total, $txnId]);

        $po->setLaborCost($po->getLaborCost() + $total);
        $this->poRepo->save($po);
    }

    public function recordOverhead(string $poId, string $type, float $base, float $rate): void
    {
        $po = $this->poRepo->findById($poId);
        if (!$po) throw new \InvalidArgumentException('Không tìm thấy lệnh SX');

        $total = $base * $rate;
        $id = uniqid('poo_');
        $stmt = $this->pdo->prepare(
            'INSERT INTO production_order_overhead (id,production_order_id,overhead_type,allocation_base,rate,total_cost)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([$id, $poId, $type, $base, $rate, $total]);

        $po->setOverheadCost($po->getOverheadCost() + $total);
        $this->poRepo->save($po);
    }

    public function completeProductionOrder(string $poId, float $completedQty, string $endDate): void
    {
        $po = $this->poRepo->findById($poId);
        if (!$po) throw new \InvalidArgumentException('Không tìm thấy lệnh SX');
        if ($po->getStatus() !== 'released') throw new \InvalidArgumentException('Chỉ hoàn thành lệnh SX đã release');

        $po->setCompletedQty($completedQty);
        $po->setEndDate($endDate);
        $po->setStatus('completed');
        $this->poRepo->save($po);
    }

    public function calculateCost(string $poId): array
    {
        $po = $this->poRepo->findById($poId);
        if (!$po) throw new \InvalidArgumentException('Không tìm thấy lệnh SX');

        $totalCost = $po->getMaterialCost() + $po->getLaborCost() + $po->getOverheadCost();
        $unitCost = $po->getCompletedQty() > 0 ? $totalCost / $po->getCompletedQty() : 0;

        $stmt = $this->pdo->prepare(
            'UPDATE production_orders SET material_cost=?, labor_cost=?, overhead_cost=?, total_cost=?, unit_cost=?, status="costed" WHERE id=?'
        );
        $stmt->execute([$po->getMaterialCost(), $po->getLaborCost(), $po->getOverheadCost(), $totalCost, $unitCost, $poId]);

        return [
            'total_cost' => $totalCost,
            'material_cost' => $po->getMaterialCost(),
            'labor_cost' => $po->getLaborCost(),
            'overhead_cost' => $po->getOverheadCost(),
            'unit_cost' => $unitCost,
        ];
    }

    public function closeProductionOrder(string $poId): void
    {
        $po = $this->poRepo->findById($poId);
        if (!$po) throw new \InvalidArgumentException('Không tìm thấy lệnh SX');
        $po->setStatus('closed');
        $this->poRepo->save($po);
    }

    public function getDashboard(): array
    {
        return [
            'stats' => $this->pdo->query("
                SELECT COUNT(*) as total,
                       SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) as draft,
                       SUM(CASE WHEN status='released' THEN 1 ELSE 0 END) as released,
                       SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
                       SUM(CASE WHEN status='costed' THEN 1 ELSE 0 END) as costed,
                       COALESCE(SUM(total_cost),0) as total_cost
                FROM production_orders
            ")->fetch(PDO::FETCH_ASSOC),
            'orders' => $this->getActiveOrders(),
        ];
    }

    public function getActiveOrders(): array
    {
        $rows = $this->pdo->query("
            SELECT po.*, i.code as product_code, i.name as product_name
            FROM production_orders po
            JOIN items i ON po.product_id = i.id
            WHERE po.status IN ('draft','released','completed','costed')
            ORDER BY po.created_at DESC LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    public function getProductionReport(string $poId): array
    {
        $po = $this->poRepo->findById($poId);
        if (!$po) throw new \InvalidArgumentException('Không tìm thấy lệnh SX');
        return [
            'order' => $po->toArray(),
            'materials' => $this->poRepo->getMaterials($poId),
            'labor' => $this->poRepo->getLabor($poId),
            'overhead' => $this->poRepo->getOverhead($poId),
        ];
    }

    public function exportReport(string $format, string $poId): array
    {
        $report = $this->getProductionReport($poId);
        $o = $report['order'];
        $headers = ['Hạng mục', 'Giá trị'];
        $data = [
            ['Mã lệnh', $o['reference']], ['Sản phẩm', $o['product_id']],
            ['Số lượng', $o['qty']], ['Hoàn thành', $o['completed_qty']],
            ['CP NVL', $o['material_cost']], ['CP NC', $o['labor_cost']],
            ['CP SXC', $o['overhead_cost']], ['Tổng CP', $o['total_cost']],
            ['Đơn giá', $o['unit_cost']],
        ];
        return $this->export->exportCsv($headers, $data, 'lenh_sx_' . $o['reference'] . '_' . date('Ymd') . '.csv');
    }
}

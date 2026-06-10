<?php
declare(strict_types=1);
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\BomRepositoryInterface;
use Accounting\Domain\Repository\ProductionOrderRepositoryInterface;
use Accounting\Domain\Model\Bom;
use Accounting\Domain\Model\ProductionOrder;
use PDO;

/**
 * ManufacturingService — Nghiệp vụ sản xuất: quản lý định mức nguyên vật liệu (BOM),
 * lệnh sản xuất (Production Order), xuất kho NVL, nhập kho thành phẩm, tập hợp chi phí.
 *
 * Module này phục vụ kế toán chi phí sản xuất và tính giá thành sản phẩm.
 * Các tài khoản liên quan: 154 (CPSXKD dở dang), 155 (Thành phẩm),
 * 621 (CP NVL trực tiếp), 622 (CP nhân công trực tiếp), 627 (CP SXC), 632 (Giá vốn hàng bán).
 *
 * @package Accounting\Domain\Service
 */
class ManufacturingService
{
    private BomRepositoryInterface $bomRepo;
    private ProductionOrderRepositoryInterface $poRepo;
    private PDO $pdo;
    private ReportExportService $export;
    private JournalService $journalService;
    private string $userId;

    /**
     * @param BomRepositoryInterface $bomRepo         Repository quản lý định mức NVL (BOM)
     * @param ProductionOrderRepositoryInterface $poRepo Repository quản lý lệnh sản xuất
     * @param PDO $pdo                                   Kết nối PDO đến MySQL
     * @param ReportExportService $export                Dịch vụ xuất báo cáo (CSV)
     * @param JournalService $journalService             Dịch vụ bút toán kế toán
     */
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

    // =========================================================================
    // ĐỊNH MỨC NGUYÊN VẬT LIỆU (BOM)
    // =========================================================================

    /**
     * Tạo định mức nguyên vật liệu (BOM) cho một sản phẩm.
     *
     * Nghiệp vụ: Mỗi sản phẩm có thể có nhiều phiên bản BOM (version tăng dần).
     * BOM xác định danh sách NVL cần thiết để sản xuất một đơn vị sản phẩm.
     *
     * @param string $productId      ID của sản phẩm thành phẩm
     * @param string $effectiveDate  Ngày hiệu lực của BOM (định dạng YYYY-MM-DD)
     * @param array  $lines          Danh sách dòng NVL, mỗi dòng gồm material_id, qty, unit_cost
     * @param string|null $notes     Ghi chú cho BOM
     * @param string|null $createdBy ID người tạo
     * @return Bom                   Đối tượng BOM đã được lưu
     */
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

    /**
     * Kích hoạt BOM — chuyển trạng thái thành 'active'.
     *
     * Chỉ BOM ở trạng thái active mới được sử dụng cho lệnh sản xuất.
     *
     * @param string $id ID của BOM cần kích hoạt
     * @return void
     * @throws \InvalidArgumentException Không tìm thấy BOM
     */
    public function activateBom(string $id): void
    {
        $bom = $this->bomRepo->findById($id);
        if (!$bom) throw new \InvalidArgumentException('Không tìm thấy BOM');
        $bom->setStatus('active');
        $this->bomRepo->save($bom);
    }

    /**
     * Lấy thông tin chi tiết của một BOM.
     *
     * @param string $id ID của BOM
     * @return array Mảng dữ liệu BOM (bao gồm các dòng NVL)
     * @throws \InvalidArgumentException Không tìm thấy BOM
     */
    public function getBomDetails(string $id): array
    {
        $bom = $this->bomRepo->findById($id);
        if (!$bom) throw new \InvalidArgumentException('Không tìm thấy BOM');
        return $bom->toArray();
    }

    // =========================================================================
    // LỆNH SẢN XUẤT (PRODUCTION ORDERS)
    // =========================================================================

    /**
     * Tạo lệnh sản xuất mới.
     *
     * Nghiệp vụ: Sinh số chứng từ tự động theo năm (PO{YYYY}-{000000}).
     * Lệnh sản xuất ở trạng thái 'draft' sau khi tạo.
     *
     * @param string      $productId  ID sản phẩm cần sản xuất
     * @param float       $qty        Số lượng sản xuất kế hoạch
     * @param string|null $bomId      ID định mức NVL (BOM) áp dụng
     * @param string|null $startDate  Ngày bắt đầu dự kiến (YYYY-MM-DD)
     * @param string|null $dueDate    Ngày kết thúc dự kiến (YYYY-MM-DD)
     * @param string|null $createdBy  ID người tạo lệnh
     * @return ProductionOrder        Đối tượng lệnh sản xuất đã được lưu
     */
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

    /**
     * Release lệnh sản xuất — chuyển từ 'draft' sang 'released'.
     *
     * Chỉ lệnh sản xuất ở trạng thái nháp (draft) mới có thể release.
     * Sau khi release, lệnh sẵn sàng để xuất kho NVL và thực hiện gia công.
     *
     * @param string $id ID lệnh sản xuất
     * @return void
     * @throws \InvalidArgumentException Không tìm thấy lệnh SX hoặc không ở trạng thái nháp
     */
    public function releaseProductionOrder(string $id): void
    {
        $po = $this->poRepo->findById($id);
        if (!$po) throw new \InvalidArgumentException('Không tìm thấy lệnh SX');
        if ($po->getStatus() !== 'draft') throw new \InvalidArgumentException('Chỉ release lệnh SX ở trạng thái nháp');
        $po->setStatus('released');
        $this->poRepo->save($po);
    }

    /**
     * Ghi nhận xuất kho nguyên vật liệu cho lệnh sản xuất.
     *
     * Nghiệp vụ: Xuất NVL từ kho — ghi tăng chi phí NVL trực tiếp (TK 621)
     * và giảm tồn kho NVL (TK 152). Mỗi lần xuất được ghi vào bảng
     * production_order_materials và cộng dồn vào material_cost của lệnh.
     *
     * @param string      $poId        ID lệnh sản xuất
     * @param string      $materialId  ID nguyên vật liệu
     * @param float       $qty         Số lượng xuất kho
     * @param float       $unitCost    Đơn giá xuất kho
     * @param float       $totalCost   Tổng giá trị xuất kho
     * @param string|null $txnId       ID bút toán kế toán (nếu đã ghi nhận)
     * @return void
     * @throws \InvalidArgumentException Không tìm thấy lệnh SX
     */
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

    /**
     * Ghi nhận chi phí nhân công trực tiếp cho lệnh sản xuất.
     *
     * Nghiệp vụ: Tập hợp chi phí nhân công (TK 622) dựa trên số giờ lao động
     * thực tế và đơn giá giờ công. Mỗi lần ghi được lưu vào bảng
     * production_order_labor và cộng dồn vào labor_cost của lệnh.
     *
     * @param string      $poId       ID lệnh sản xuất
     * @param string      $laborType  Loại lao động (VD: 'direct', 'indirect')
     * @param float       $hours      Số giờ lao động thực tế
     * @param float       $rate       Đơn giá giờ công
     * @param string|null $txnId      ID bút toán kế toán (nếu đã ghi nhận)
     * @return void
     * @throws \InvalidArgumentException Không tìm thấy lệnh SX
     */
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

    /**
     * Ghi nhận chi phí sản xuất chung (SXC) cho lệnh sản xuất.
     *
     * Nghiệp vụ: Phân bổ chi phí SXC (TK 627) dựa trên căn cứ phân bổ
     * (allocation_base) nhân với tỷ lệ phân bổ (rate). Mỗi lần ghi được lưu
     * vào bảng production_order_overhead và cộng dồn vào overhead_cost của lệnh.
     *
     * @param string $poId  ID lệnh sản xuất
     * @param string $type  Loại chi phí SXC (VD: 'electricity', 'rent', 'depreciation')
     * @param float  $base  Căn cứ phân bổ (VD: số giờ máy, số lượng sản phẩm)
     * @param float  $rate  Tỷ lệ phân bổ
     * @return void
     * @throws \InvalidArgumentException Không tìm thấy lệnh SX
     */
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

    /**
     * Hoàn thành lệnh sản xuất — ghi nhận số lượng hoàn thành và chuyển sang 'completed'.
     *
     * Chỉ lệnh sản xuất ở trạng thái 'released' mới có thể hoàn thành.
     * Nghiệp vụ: Nhập kho thành phẩm — ghi tăng thành phẩm (TK 155)
     * và giảm CPSXKD dở dang (TK 154).
     *
     * @param string $poId         ID lệnh sản xuất
     * @param float  $completedQty Số lượng sản phẩm hoàn thành nhập kho
     * @param string $endDate      Ngày kết thúc thực tế (YYYY-MM-DD)
     * @return void
     * @throws \InvalidArgumentException Không tìm thấy lệnh SX hoặc chưa release
     */
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

    /**
     * Tính giá thành sản xuất thực tế cho lệnh sản xuất.
     *
     * Nghiệp vụ: Tổng hợp chi phí NVL (621) + nhân công (622) + SXC (627)
     * để tính tổng giá thành và giá thành đơn vị.
     * Sau khi tính, lệnh chuyển sang trạng thái 'costed'.
     * Đơn giá = Tổng chi phí / Số lượng hoàn thành.
     *
     * RỦI RO: Nếu số lượng hoàn thành = 0, đơn giá = 0 (tránh chia cho 0).
     *
     * @param string $poId ID lệnh sản xuất
     * @return array Mảng kết quả gồm total_cost, material_cost, labor_cost, overhead_cost, unit_cost
     * @throws \InvalidArgumentException Không tìm thấy lệnh SX
     */
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

    /**
     * Đóng lệnh sản xuất — chuyển sang trạng thái 'closed'.
     *
     * Lệnh đã đóng không thể thay đổi dữ liệu chi phí.
     * Đây là trạng thái cuối cùng trong vòng đời lệnh sản xuất.
     *
     * @param string $poId ID lệnh sản xuất
     * @return void
     * @throws \InvalidArgumentException Không tìm thấy lệnh SX
     */
    public function closeProductionOrder(string $poId): void
    {
        $po = $this->poRepo->findById($poId);
        if (!$po) throw new \InvalidArgumentException('Không tìm thấy lệnh SX');
        $po->setStatus('closed');
        $this->poRepo->save($po);
    }

    /**
     * Lấy dữ liệu tổng quan (dashboard) cho module sản xuất.
     *
     * Trả về thống kê số lượng lệnh sản xuất theo trạng thái
     * và danh sách lệnh đang hoạt động.
     *
     * @return array Mảng gồm 'stats' (thống kê) và 'orders' (lệnh đang hoạt động)
     */
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

    /**
     * Lấy danh sách lệnh sản xuất đang hoạt động.
     *
     * Bao gồm các lệnh ở trạng thái: draft, released, completed, costed.
     * Kết hợp với bảng items để lấy mã và tên sản phẩm.
     * Giới hạn 100 lệnh gần nhất.
     *
     * @return array Mảng các lệnh sản xuất đang hoạt động
     */
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

    /**
     * Lấy báo cáo chi tiết cho một lệnh sản xuất.
     *
     * Trả về thông tin lệnh, danh sách NVL đã xuất, nhân công và SXC.
     *
     * @param string $poId ID lệnh sản xuất
     * @return array Mảng gồm 'order', 'materials', 'labor', 'overhead'
     * @throws \InvalidArgumentException Không tìm thấy lệnh SX
     */
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

    /**
     * Xuất báo cáo lệnh sản xuất ra file CSV.
     *
     * @param string $format Định dạng xuất (hiện tại chỉ hỗ trợ 'csv')
     * @param string $poId   ID lệnh sản xuất
     * @return array         Kết quả từ ReportExportService::exportCsv
     * @throws \InvalidArgumentException Không tìm thấy lệnh SX
     */
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

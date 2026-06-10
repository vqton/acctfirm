<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

/**
 * Lệnh sản xuất — Yêu cầu sản xuất một lượng thành phẩm.
 *
 * Mỗi lệnh sản xuất có định mức (BOM), theo dõi chi phí nguyên vật liệu,
 * nhân công, chi phí sản xuất chung, tính giá thành đơn vị.
 *
 * NGHIỆP VỤ:
 * - $status: 'draft', 'in_progress', 'completed', 'cancelled'
 * - $materialCost: chi phí NVL trực tiếp (TK 621)
 * - $laborCost: chi phí nhân công trực tiếp (TK 622)
 * - $overheadCost: chi phí sản xuất chung (TK 627)
 * - $totalCost = materialCost + laborCost + overheadCost
 * - $unitCost = totalCost / completedQty
 */
class ProductionOrder
{
    private string $id;
    private string $reference;
    private string $productId;
    private ?string $bomId;
    private float $qty;
    private float $completedQty;
    private ?string $startDate;
    private ?string $endDate;
    private ?string $dueDate;
    private string $status;
    private float $materialCost;
    private float $laborCost;
    private float $overheadCost;
    private float $totalCost;
    private float $unitCost;
    private ?string $notes;
    private ?string $createdBy;

    /**
     * Khởi tạo lệnh sản xuất.
     *
     * @param string $id Định danh
     * @param string $reference Số tham chiếu
     * @param string $productId ID thành phẩm
     * @param float $qty Số lượng sản xuất
     * @param string|null $startDate Ngày bắt đầu
     * @param string|null $dueDate Ngày dự kiến hoàn thành
     * @param string|null $bomId ID định mức NVL
     */
    public function __construct(string $id, string $reference, string $productId, float $qty, ?string $startDate = null, ?string $dueDate = null, ?string $bomId = null)
    {
        $this->id = $id; $this->reference = $reference; $this->productId = $productId;
        $this->bomId = $bomId; $this->qty = $qty; $this->completedQty = 0;
        $this->startDate = $startDate; $this->endDate = null; $this->dueDate = $dueDate;
        $this->status = 'draft'; $this->materialCost = 0; $this->laborCost = 0;
        $this->overheadCost = 0; $this->totalCost = 0; $this->unitCost = 0;
        $this->notes = null; $this->createdBy = null;
    }

    /** @return string Định danh lệnh sản xuất */
    public function getId(): string { return $this->id; }

    /** @return string Số tham chiếu */
    public function getReference(): string { return $this->reference; }

    /** @return string ID thành phẩm */
    public function getProductId(): string { return $this->productId; }

    /** @return string|null ID định mức NVL */
    public function getBomId(): ?string { return $this->bomId; }

    /** @return float Số lượng sản xuất */
    public function getQty(): float { return $this->qty; }

    /** @return float Số lượng hoàn thành */
    public function getCompletedQty(): float { return $this->completedQty; }

    /** @return string|null Ngày bắt đầu */
    public function getStartDate(): ?string { return $this->startDate; }

    /** @return string|null Ngày dự kiến hoàn thành */
    public function getDueDate(): ?string { return $this->dueDate; }

    /** @return string Trạng thái */
    public function getStatus(): string { return $this->status; }

    /** @return float Chi phí NVL trực tiếp (TK 621) */
    public function getMaterialCost(): float { return $this->materialCost; }

    /** @return float Chi phí nhân công trực tiếp (TK 622) */
    public function getLaborCost(): float { return $this->laborCost; }

    /** @return float Chi phí sản xuất chung (TK 627) */
    public function getOverheadCost(): float { return $this->overheadCost; }

    /** @return float Tổng chi phí sản xuất */
    public function getTotalCost(): float { return $this->totalCost; }

    /** @return float Giá thành đơn vị */
    public function getUnitCost(): float { return $this->unitCost; }

    /** @return string|null Ghi chú */
    public function getNotes(): ?string { return $this->notes; }

    /** @return string|null Người tạo */
    public function getCreatedBy(): ?string { return $this->createdBy; }

    /** @param string $v Trạng thái mới */
    public function setStatus(string $v): void { $this->status = $v; }

    /** @param float $v Số lượng hoàn thành mới */
    public function setCompletedQty(float $v): void { $this->completedQty = $v; }

    /** @param string|null $v Ngày kết thúc mới */
    public function setEndDate(?string $v): void { $this->endDate = $v; }

    /** @param float $v Chi phí NVL mới */
    public function setMaterialCost(float $v): void { $this->materialCost = $v; $this->recalc(); }

    /** @param float $v Chi phí nhân công mới */
    public function setLaborCost(float $v): void { $this->laborCost = $v; $this->recalc(); }

    /** @param float $v Chi phí SXC mới */
    public function setOverheadCost(float $v): void { $this->overheadCost = $v; $this->recalc(); }

    /** @param string|null $v Ghi chú mới */
    public function setNotes(?string $v): void { $this->notes = $v; }

    /** @param string|null $v Người tạo mới */
    public function setCreatedBy(?string $v): void { $this->createdBy = $v; }

    /**
     * Tính lại tổng chi phí và giá thành đơn vị.
     *
     * Tự động gọi khi setMaterialCost, setLaborCost, setOverheadCost.
     */
    private function recalc(): void
    {
        $this->totalCost = $this->materialCost + $this->laborCost + $this->overheadCost;
        $this->unitCost = $this->completedQty > 0 ? $this->totalCost / $this->completedQty : 0;
    }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu lệnh sản xuất dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'reference' => $this->reference, 'product_id' => $this->productId,
            'bom_id' => $this->bomId, 'qty' => $this->qty, 'completed_qty' => $this->completedQty,
            'start_date' => $this->startDate, 'due_date' => $this->dueDate,
            'status' => $this->status, 'material_cost' => $this->materialCost,
            'labor_cost' => $this->laborCost, 'overhead_cost' => $this->overheadCost,
            'total_cost' => $this->totalCost, 'unit_cost' => $this->unitCost,
            'notes' => $this->notes, 'created_by' => $this->createdBy,
        ];
    }
}

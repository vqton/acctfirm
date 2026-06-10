<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

/**
 * Phiếu xuất kho — Mẫu 02-VT theo Thông tư 99/2025/TT-BTC.
 *
 * NGHIỆP VỤ:
 * - Ghi nhận hàng hóa xuất kho từ nhiều nguồn (bán hàng, sản xuất, khác)
 * - Hạch toán: Nợ 632 (Giá vốn) / Có 15x (Hàng tồn kho)
 * - Lifecycle: draft → posted → cancelled
 * - $issueType: 'sale', 'production', 'transfer', 'return_supplier', 'adjustment', 'other'
 */
class GoodsIssue
{
    private ?string $id;
    private ?string $issueNumber;
    private ?string $issueDate;
    private ?string $warehouseId;
    private ?string $receiverName;
    private ?string $receiverDepartment;
    private ?string $issueReason;
    private string $issueType;
    private string $status;
    private ?string $reference;
    private ?string $notes;
    private float $totalAmount;
    private string $createdBy;
    private ?string $createdAt;
    private ?string $updatedAt;
    private array $lines;

    /**
     * Khởi tạo phiếu xuất kho.
     *
     * @param string|null $id Định danh
     * @param string|null $issueNumber Số phiếu xuất
     * @param string|null $issueDate Ngày xuất
     * @param string|null $warehouseId ID kho
     * @param string|null $receiverName Người nhận
     * @param string|null $receiverDepartment Bộ phận nhận
     * @param string|null $issueReason Lý do xuất
     * @param string $issueType Loại xuất: 'sale', 'production', 'transfer', 'return_supplier', 'adjustment', 'other'
     * @param string $status Trạng thái: 'draft', 'posted', 'cancelled'
     * @param string|null $reference Tham chiếu
     * @param string|null $notes Ghi chú
     * @param float $totalAmount Tổng tiền
     * @param string $createdBy Người tạo
     * @param string|null $createdAt Thời điểm tạo
     * @param string|null $updatedAt Thời điểm cập nhật
     * @param array $lines Danh sách dòng
     */
    public function __construct(
        ?string $id = null,
        ?string $issueNumber = null,
        ?string $issueDate = null,
        ?string $warehouseId = null,
        ?string $receiverName = null,
        ?string $receiverDepartment = null,
        ?string $issueReason = null,
        string $issueType = 'sale',
        string $status = 'draft',
        ?string $reference = null,
        ?string $notes = null,
        float $totalAmount = 0.0,
        string $createdBy = 'system',
        ?string $createdAt = null,
        ?string $updatedAt = null,
        array $lines = []
    ) {
        $this->id = $id;
        $this->issueNumber = $issueNumber;
        $this->issueDate = $issueDate;
        $this->warehouseId = $warehouseId;
        $this->receiverName = $receiverName;
        $this->receiverDepartment = $receiverDepartment;
        $this->issueReason = $issueReason;
        $this->issueType = $issueType;
        $this->status = $status;
        $this->reference = $reference;
        $this->notes = $notes;
        $this->totalAmount = $totalAmount;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->lines = $lines;
    }

    /** @return string|null Định danh phiếu xuất */
    public function getId(): ?string { return $this->id; }

    /** @param string|null $id Định danh mới */
    public function setId(?string $id): void { $this->id = $id; }

    /** @return string|null Số phiếu xuất */
    public function getIssueNumber(): ?string { return $this->issueNumber; }

    /** @param string|null $v Số phiếu mới */
    public function setIssueNumber(?string $v): void { $this->issueNumber = $v; }

    /** @return string|null Ngày xuất */
    public function getIssueDate(): ?string { return $this->issueDate; }

    /** @param string|null $v Ngày xuất mới */
    public function setIssueDate(?string $v): void { $this->issueDate = $v; }

    /** @return string|null ID kho */
    public function getWarehouseId(): ?string { return $this->warehouseId; }

    /** @param string|null $v ID kho mới */
    public function setWarehouseId(?string $v): void { $this->warehouseId = $v; }

    /** @return string|null Người nhận */
    public function getReceiverName(): ?string { return $this->receiverName; }

    /** @param string|null $v Người nhận mới */
    public function setReceiverName(?string $v): void { $this->receiverName = $v; }

    /** @return string|null Bộ phận nhận */
    public function getReceiverDepartment(): ?string { return $this->receiverDepartment; }

    /** @param string|null $v Bộ phận nhận mới */
    public function setReceiverDepartment(?string $v): void { $this->receiverDepartment = $v; }

    /** @return string|null Lý do xuất */
    public function getIssueReason(): ?string { return $this->issueReason; }

    /** @param string|null $v Lý do xuất mới */
    public function setIssueReason(?string $v): void { $this->issueReason = $v; }

    /** @return string Loại xuất */
    public function getIssueType(): string { return $this->issueType; }

    /** @param string $v Loại xuất mới */
    public function setIssueType(string $v): void { $this->issueType = $v; }

    /** @return string Trạng thái */
    public function getStatus(): string { return $this->status; }

    /** @param string $v Trạng thái mới */
    public function setStatus(string $v): void { $this->status = $v; }

    /** @return string|null Tham chiếu */
    public function getReference(): ?string { return $this->reference; }

    /** @param string|null $v Tham chiếu mới */
    public function setReference(?string $v): void { $this->reference = $v; }

    /** @return string|null Ghi chú */
    public function getNotes(): ?string { return $this->notes; }

    /** @param string|null $v Ghi chú mới */
    public function setNotes(?string $v): void { $this->notes = $v; }

    /** @return float Tổng tiền */
    public function getTotalAmount(): float { return $this->totalAmount; }

    /** @param float $v Tổng tiền mới */
    public function setTotalAmount(float $v): void { $this->totalAmount = $v; }

    /** @return string Người tạo */
    public function getCreatedBy(): string { return $this->createdBy; }

    /** @param string $v Người tạo mới */
    public function setCreatedBy(string $v): void { $this->createdBy = $v; }

    /** @return string|null Thời điểm tạo */
    public function getCreatedAt(): ?string { return $this->createdAt; }

    /** @param string|null $v Thời điểm tạo mới */
    public function setCreatedAt(?string $v): void { $this->createdAt = $v; }

    /** @return string|null Thời điểm cập nhật */
    public function getUpdatedAt(): ?string { return $this->updatedAt; }

    /** @param string|null $v Thời điểm cập nhật mới */
    public function setUpdatedAt(?string $v): void { $this->updatedAt = $v; }

    /** @return array Danh sách dòng */
    public function getLines(): array { return $this->lines; }

    /** @param array $v Danh sách dòng mới */
    public function setLines(array $v): void { $this->lines = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu phiếu xuất dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'issue_number' => $this->issueNumber,
            'issue_date' => $this->issueDate,
            'warehouse_id' => $this->warehouseId,
            'receiver_name' => $this->receiverName,
            'receiver_department' => $this->receiverDepartment,
            'issue_reason' => $this->issueReason,
            'issue_type' => $this->issueType,
            'status' => $this->status,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'total_amount' => $this->totalAmount,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'lines' => array_map(fn($l) => $l instanceof GoodsIssueItem ? $l->toArray() : $l, $this->lines),
        ];
    }
}

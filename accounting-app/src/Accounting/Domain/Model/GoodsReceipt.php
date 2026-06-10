<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

/**
 * Phiếu nhập kho — Mẫu 01-VT theo Thông tư 99/2025/TT-BTC.
 *
 * NGHIỆP VỤ:
 * - Ghi nhận hàng hóa nhập kho từ nhiều nguồn (mua, trả lại SX, khác)
 * - Hạch toán: Nợ 15x (Giá trị hàng = SL × ĐG) / Có 331 (Phải trả người bán) hoặc Có 111/112
 * - Lifecycle: draft → posted → cancelled
 * - $receiptType: 'purchase', 'return', 'production', 'transfer', 'other'
 */
class GoodsReceipt
{
    private ?string $id;
    private ?string $grNumber;
    private ?string $poId;
    private ?string $supplierName;
    private ?string $supplierAddress;
    private string $receiptType;
    private string $status;
    private ?string $warehouseId;
    private ?string $receivedDate;
    private ?string $department;
    private ?string $note;
    private ?float $totalAmount;
    private ?string $amountInWords;
    private ?string $createdBy;
    private ?string $createdAt;
    private ?string $updatedAt;
    private ?string $invoiceRef;
    private ?string $invoiceDate;
    private ?string $delivererName;
    private ?string $warehouseLocation;
    private ?string $attachDoc;

    /**
     * Khởi tạo phiếu nhập kho.
     *
     * @param string|null $id Định danh
     * @param string|null $grNumber Số phiếu nhập
     * @param string|null $poId ID đơn đặt hàng
     * @param string|null $supplierName Tên nhà cung cấp
     * @param string|null $supplierAddress Địa chỉ NCC
     * @param string $receiptType Loại nhập: 'purchase', 'return', 'production', 'transfer', 'other'
     * @param string $status Trạng thái: 'draft', 'posted', 'cancelled'
     * @param string|null $warehouseId ID kho
     * @param string|null $receivedDate Ngày nhập
     * @param string|null $department Bộ phận
     * @param string|null $note Ghi chú
     * @param float|null $totalAmount Tổng tiền
     * @param string|null $amountInWords Số tiền bằng chữ
     * @param string|null $createdBy Người tạo
     * @param string|null $createdAt Thời điểm tạo
     * @param string|null $updatedAt Thời điểm cập nhật
     * @param string|null $invoiceRef Số hóa đơn
     * @param string|null $invoiceDate Ngày hóa đơn
     * @param string|null $delivererName Người giao hàng
     * @param string|null $warehouseLocation Vị trí lưu kho
     * @param string|null $attachDoc File đính kèm
     */
    public function __construct(
        ?string $id = null,
        ?string $grNumber = null,
        ?string $poId = null,
        ?string $supplierName = null,
        ?string $supplierAddress = null,
        string $receiptType = 'purchase',
        string $status = 'draft',
        ?string $warehouseId = null,
        ?string $receivedDate = null,
        ?string $department = null,
        ?string $note = null,
        ?float $totalAmount = null,
        ?string $amountInWords = null,
        ?string $createdBy = null,
        ?string $createdAt = null,
        ?string $updatedAt = null,
        ?string $invoiceRef = null,
        ?string $invoiceDate = null,
        ?string $delivererName = null,
        ?string $warehouseLocation = null,
        ?string $attachDoc = null
    ) {
        $this->id = $id;
        $this->grNumber = $grNumber;
        $this->poId = $poId;
        $this->supplierName = $supplierName;
        $this->supplierAddress = $supplierAddress;
        $this->receiptType = $receiptType;
        $this->status = $status;
        $this->warehouseId = $warehouseId;
        $this->receivedDate = $receivedDate;
        $this->department = $department;
        $this->note = $note;
        $this->totalAmount = $totalAmount;
        $this->amountInWords = $amountInWords;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->invoiceRef = $invoiceRef;
        $this->invoiceDate = $invoiceDate;
        $this->delivererName = $delivererName;
        $this->warehouseLocation = $warehouseLocation;
        $this->attachDoc = $attachDoc;
    }

    /** @return string|null Định danh phiếu nhập */
    public function getId(): ?string { return $this->id; }

    /** @param string|null $id Định danh mới */
    public function setId(?string $id): void { $this->id = $id; }

    /** @return string|null Số phiếu nhập */
    public function getGrNumber(): ?string { return $this->grNumber; }

    /** @param string|null $grNumber Số phiếu nhập mới */
    public function setGrNumber(?string $grNumber): void { $this->grNumber = $grNumber; }

    /** @return string|null ID đơn đặt hàng */
    public function getPoId(): ?string { return $this->poId; }

    /** @param string|null $poId ID đơn hàng mới */
    public function setPoId(?string $poId): void { $this->poId = $poId; }

    /** @return string|null Tên nhà cung cấp */
    public function getSupplierName(): ?string { return $this->supplierName; }

    /** @param string|null $v Tên NCC mới */
    public function setSupplierName(?string $v): void { $this->supplierName = $v; }

    /** @return string|null Địa chỉ NCC */
    public function getSupplierAddress(): ?string { return $this->supplierAddress; }

    /** @param string|null $v Địa chỉ NCC mới */
    public function setSupplierAddress(?string $v): void { $this->supplierAddress = $v; }

    /** @return string Loại nhập */
    public function getReceiptType(): string { return $this->receiptType; }

    /** @param string $v Loại nhập mới */
    public function setReceiptType(string $v): void { $this->receiptType = $v; }

    /** @return string Trạng thái */
    public function getStatus(): string { return $this->status; }

    /** @param string $v Trạng thái mới */
    public function setStatus(string $v): void { $this->status = $v; }

    /** @return string|null ID kho */
    public function getWarehouseId(): ?string { return $this->warehouseId; }

    /** @param string|null $v ID kho mới */
    public function setWarehouseId(?string $v): void { $this->warehouseId = $v; }

    /** @return string|null Ngày nhập */
    public function getReceivedDate(): ?string { return $this->receivedDate; }

    /** @param string|null $v Ngày nhập mới */
    public function setReceivedDate(?string $v): void { $this->receivedDate = $v; }

    /** @return string|null Bộ phận */
    public function getDepartment(): ?string { return $this->department; }

    /** @param string|null $v Bộ phận mới */
    public function setDepartment(?string $v): void { $this->department = $v; }

    /** @return string|null Ghi chú */
    public function getNote(): ?string { return $this->note; }

    /** @param string|null $v Ghi chú mới */
    public function setNote(?string $v): void { $this->note = $v; }

    /** @return float|null Tổng tiền */
    public function getTotalAmount(): ?float { return $this->totalAmount; }

    /** @param float|null $v Tổng tiền mới */
    public function setTotalAmount(?float $v): void { $this->totalAmount = $v; }

    /** @return string|null Số tiền bằng chữ */
    public function getAmountInWords(): ?string { return $this->amountInWords; }

    /** @param string|null $v Số tiền bằng chữ mới */
    public function setAmountInWords(?string $v): void { $this->amountInWords = $v; }

    /** @return string|null Người tạo */
    public function getCreatedBy(): ?string { return $this->createdBy; }

    /** @param string|null $v Người tạo mới */
    public function setCreatedBy(?string $v): void { $this->createdBy = $v; }

    /** @return string|null Thời điểm tạo */
    public function getCreatedAt(): ?string { return $this->createdAt; }

    /** @param string|null $v Thời điểm tạo mới */
    public function setCreatedAt(?string $v): void { $this->createdAt = $v; }

    /** @return string|null Thời điểm cập nhật */
    public function getUpdatedAt(): ?string { return $this->updatedAt; }

    /** @param string|null $v Thời điểm cập nhật mới */
    public function setUpdatedAt(?string $v): void { $this->updatedAt = $v; }

    /** @return string|null Số hóa đơn */
    public function getInvoiceRef(): ?string { return $this->invoiceRef; }

    /** @param string|null $v Số hóa đơn mới */
    public function setInvoiceRef(?string $v): void { $this->invoiceRef = $v; }

    /** @return string|null Ngày hóa đơn */
    public function getInvoiceDate(): ?string { return $this->invoiceDate; }

    /** @param string|null $v Ngày hóa đơn mới */
    public function setInvoiceDate(?string $v): void { $this->invoiceDate = $v; }

    /** @return string|null Người giao hàng */
    public function getDelivererName(): ?string { return $this->delivererName; }

    /** @param string|null $v Người giao hàng mới */
    public function setDelivererName(?string $v): void { $this->delivererName = $v; }

    /** @return string|null Vị trí lưu kho */
    public function getWarehouseLocation(): ?string { return $this->warehouseLocation; }

    /** @param string|null $v Vị trí lưu kho mới */
    public function setWarehouseLocation(?string $v): void { $this->warehouseLocation = $v; }

    /** @return string|null File đính kèm */
    public function getAttachDoc(): ?string { return $this->attachDoc; }

    /** @param string|null $v File đính kèm mới */
    public function setAttachDoc(?string $v): void { $this->attachDoc = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu phiếu nhập dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gr_number' => $this->grNumber,
            'po_id' => $this->poId,
            'supplier_name' => $this->supplierName,
            'supplier_address' => $this->supplierAddress,
            'receipt_type' => $this->receiptType,
            'status' => $this->status,
            'warehouse_id' => $this->warehouseId,
            'received_date' => $this->receivedDate,
            'department' => $this->department,
            'note' => $this->note,
            'total_amount' => $this->totalAmount,
            'amount_in_words' => $this->amountInWords,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'invoice_ref' => $this->invoiceRef,
            'invoice_date' => $this->invoiceDate,
            'deliverer_name' => $this->delivererName,
            'warehouse_location' => $this->warehouseLocation,
            'attach_doc' => $this->attachDoc,
        ];
    }
}

<?php
declare(strict_types=1);
// PHIẾU NHẬP KHO — Mẫu 01-VT theo Thông tư 99/2025/TT-BTC
//
// Nghiệp vụ: Ghi nhận hàng hóa nhập kho từ nhiều nguồn (mua, trả lại SX, khác)
// Hạch toán: Nợ 15x (Giá trị hàng = SL × ĐG) / Có 331 (Phải trả người bán) hoặc Có 111/112
//
// Lifecycle: draft → posted → cancelled
namespace Accounting\Domain\Model;

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

    public function getId(): ?string { return $this->id; }
    public function setId(?string $id): void { $this->id = $id; }
    public function getGrNumber(): ?string { return $this->grNumber; }
    public function setGrNumber(?string $grNumber): void { $this->grNumber = $grNumber; }
    public function getPoId(): ?string { return $this->poId; }
    public function setPoId(?string $poId): void { $this->poId = $poId; }
    public function getSupplierName(): ?string { return $this->supplierName; }
    public function setSupplierName(?string $v): void { $this->supplierName = $v; }
    public function getSupplierAddress(): ?string { return $this->supplierAddress; }
    public function setSupplierAddress(?string $v): void { $this->supplierAddress = $v; }
    public function getReceiptType(): string { return $this->receiptType; }
    public function setReceiptType(string $v): void { $this->receiptType = $v; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): void { $this->status = $v; }
    public function getWarehouseId(): ?string { return $this->warehouseId; }
    public function setWarehouseId(?string $v): void { $this->warehouseId = $v; }
    public function getReceivedDate(): ?string { return $this->receivedDate; }
    public function setReceivedDate(?string $v): void { $this->receivedDate = $v; }
    public function getDepartment(): ?string { return $this->department; }
    public function setDepartment(?string $v): void { $this->department = $v; }
    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $v): void { $this->note = $v; }
    public function getTotalAmount(): ?float { return $this->totalAmount; }
    public function setTotalAmount(?float $v): void { $this->totalAmount = $v; }
    public function getAmountInWords(): ?string { return $this->amountInWords; }
    public function setAmountInWords(?string $v): void { $this->amountInWords = $v; }
    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function setCreatedBy(?string $v): void { $this->createdBy = $v; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function setCreatedAt(?string $v): void { $this->createdAt = $v; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    public function setUpdatedAt(?string $v): void { $this->updatedAt = $v; }
    public function getInvoiceRef(): ?string { return $this->invoiceRef; }
    public function setInvoiceRef(?string $v): void { $this->invoiceRef = $v; }
    public function getInvoiceDate(): ?string { return $this->invoiceDate; }
    public function setInvoiceDate(?string $v): void { $this->invoiceDate = $v; }
    public function getDelivererName(): ?string { return $this->delivererName; }
    public function setDelivererName(?string $v): void { $this->delivererName = $v; }
    public function getWarehouseLocation(): ?string { return $this->warehouseLocation; }
    public function setWarehouseLocation(?string $v): void { $this->warehouseLocation = $v; }
    public function getAttachDoc(): ?string { return $this->attachDoc; }
    public function setAttachDoc(?string $v): void { $this->attachDoc = $v; }

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

<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

/**
 * Đối chiếu hóa đơn mua hàng — So khớp hóa đơn nhà cung cấp với PO và phiếu nhập kho.
 *
 * Quy trình 3-way matching: PO ⇔ GR ⇔ Invoice.
 * Đảm bảo chỉ thanh toán khi hàng đã nhập và giá khớp với PO.
 *
 * NGHIỆP VỤ:
 * - $matchStatus: 'draft', 'matched', 'partially_matched', 'unmatched', 'cancelled'
 * - $invoiceAmount: số tiền trên hóa đơn NCC
 * - $vatAmount: số thuế GTGT trên hóa đơn
 */
class PurchaseInvoiceMatch
{
    private ?string $id;
    private ?string $poId;
    private ?string $grId;
    private ?string $supplierInvoiceNo;
    private ?string $invoiceDate;
    private ?float $invoiceAmount;
    private ?float $vatAmount;
    private string $matchStatus;
    private ?string $matchedBy;
    private ?string $matchedAt;
    private ?string $note;
    private ?string $createdAt;

    /**
     * Khởi tạo đối chiếu hóa đơn mua hàng.
     *
     * @param string|null $id Định danh
     * @param string|null $poId ID đơn đặt hàng
     * @param string|null $grId ID phiếu nhập kho
     * @param string|null $supplierInvoiceNo Số hóa đơn NCC
     * @param string|null $invoiceDate Ngày hóa đơn
     * @param float|null $invoiceAmount Số tiền hóa đơn
     * @param float|null $vatAmount Số thuế GTGT
     * @param string $matchStatus Trạng thái đối chiếu
     * @param string|null $matchedBy Người đối chiếu
     * @param string|null $matchedAt Thời điểm đối chiếu
     * @param string|null $note Ghi chú
     * @param string|null $createdAt Thời điểm tạo
     */
    public function __construct(
        ?string $id = null,
        ?string $poId = null,
        ?string $grId = null,
        ?string $supplierInvoiceNo = null,
        ?string $invoiceDate = null,
        ?float $invoiceAmount = null,
        ?float $vatAmount = null,
        string $matchStatus = 'draft',
        ?string $matchedBy = null,
        ?string $matchedAt = null,
        ?string $note = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->poId = $poId;
        $this->grId = $grId;
        $this->supplierInvoiceNo = $supplierInvoiceNo;
        $this->invoiceDate = $invoiceDate;
        $this->invoiceAmount = $invoiceAmount;
        $this->vatAmount = $vatAmount;
        $this->matchStatus = $matchStatus;
        $this->matchedBy = $matchedBy;
        $this->matchedAt = $matchedAt;
        $this->note = $note;
        $this->createdAt = $createdAt;
    }

    /** @return string|null Định danh */
    public function getId(): ?string { return $this->id; }

    /** @param string|null $id Định danh mới */
    public function setId(?string $id): void { $this->id = $id; }

    /** @return string|null ID đơn đặt hàng */
    public function getPoId(): ?string { return $this->poId; }

    /** @param string|null $poId ID đơn hàng mới */
    public function setPoId(?string $poId): void { $this->poId = $poId; }

    /** @return string|null ID phiếu nhập kho */
    public function getGrId(): ?string { return $this->grId; }

    /** @param string|null $grId ID phiếu nhập mới */
    public function setGrId(?string $grId): void { $this->grId = $grId; }

    /** @return string|null Số hóa đơn NCC */
    public function getSupplierInvoiceNo(): ?string { return $this->supplierInvoiceNo; }

    /** @param string|null $supplierInvoiceNo Số hóa đơn mới */
    public function setSupplierInvoiceNo(?string $supplierInvoiceNo): void { $this->supplierInvoiceNo = $supplierInvoiceNo; }

    /** @return string|null Ngày hóa đơn */
    public function getInvoiceDate(): ?string { return $this->invoiceDate; }

    /** @param string|null $invoiceDate Ngày hóa đơn mới */
    public function setInvoiceDate(?string $invoiceDate): void { $this->invoiceDate = $invoiceDate; }

    /** @return float|null Số tiền hóa đơn */
    public function getInvoiceAmount(): ?float { return $this->invoiceAmount; }

    /** @param float|null $invoiceAmount Số tiền hóa đơn mới */
    public function setInvoiceAmount(?float $invoiceAmount): void { $this->invoiceAmount = $invoiceAmount; }

    /** @return float|null Số thuế GTGT */
    public function getVatAmount(): ?float { return $this->vatAmount; }

    /** @param float|null $vatAmount Số thuế GTGT mới */
    public function setVatAmount(?float $vatAmount): void { $this->vatAmount = $vatAmount; }

    /** @return string Trạng thái đối chiếu */
    public function getMatchStatus(): string { return $this->matchStatus; }

    /** @param string $matchStatus Trạng thái mới */
    public function setMatchStatus(string $matchStatus): void { $this->matchStatus = $matchStatus; }

    /** @return string|null Người đối chiếu */
    public function getMatchedBy(): ?string { return $this->matchedBy; }

    /** @param string|null $matchedBy Người đối chiếu mới */
    public function setMatchedBy(?string $matchedBy): void { $this->matchedBy = $matchedBy; }

    /** @return string|null Thời điểm đối chiếu */
    public function getMatchedAt(): ?string { return $this->matchedAt; }

    /** @param string|null $matchedAt Thời điểm đối chiếu mới */
    public function setMatchedAt(?string $matchedAt): void { $this->matchedAt = $matchedAt; }

    /** @return string|null Ghi chú */
    public function getNote(): ?string { return $this->note; }

    /** @param string|null $note Ghi chú mới */
    public function setNote(?string $note): void { $this->note = $note; }

    /** @return string|null Thời điểm tạo */
    public function getCreatedAt(): ?string { return $this->createdAt; }

    /** @param string|null $createdAt Thời điểm tạo mới */
    public function setCreatedAt(?string $createdAt): void { $this->createdAt = $createdAt; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu đối chiếu hóa đơn dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'po_id' => $this->poId,
            'gr_id' => $this->grId,
            'supplier_invoice_no' => $this->supplierInvoiceNo,
            'invoice_date' => $this->invoiceDate,
            'invoice_amount' => $this->invoiceAmount,
            'vat_amount' => $this->vatAmount,
            'match_status' => $this->matchStatus,
            'matched_by' => $this->matchedBy,
            'matched_at' => $this->matchedAt,
            'note' => $this->note,
            'created_at' => $this->createdAt,
        ];
    }
}

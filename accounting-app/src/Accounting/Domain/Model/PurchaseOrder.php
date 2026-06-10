<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

/**
 * Đơn đặt hàng (Purchase Order) — Yêu cầu mua hàng chính thức gửi nhà cung cấp.
 *
 * PO là chứng từ pháp lý cho việc mua hàng, làm cơ sở cho nhập kho,
 * đối chiếu hóa đơn và thanh toán.
 *
 * NGHIỆP VỤ:
 * - $status: 'draft', 'approved', 'sent', 'partially_received', 'received', 'cancelled'
 * - $totalAmount: tổng giá trị hàng (chưa thuế)
 * - $taxAmount: tổng thuế GTGT
 * - Liên kết với PurchaseRequisition và Contract
 */
class PurchaseOrder
{
    private ?string $id;
    private ?string $poNumber;
    private string $status;
    private ?string $supplierId;
    private ?string $contractId;
    private ?string $buyerId;
    private ?string $paymentTerms;
    private ?string $deliveryTerms;
    private ?float $totalAmount;
    private ?float $taxAmount;
    private ?string $expectedDelivery;
    private ?string $note;
    private ?string $createdAt;
    private ?string $updatedAt;

    /**
     * Khởi tạo đơn đặt hàng.
     *
     * @param string|null $id Định danh
     * @param string|null $poNumber Số PO
     * @param string $status Trạng thái
     * @param string|null $supplierId ID nhà cung cấp
     * @param string|null $contractId ID hợp đồng
     * @param string|null $buyerId ID người mua
     * @param string|null $paymentTerms Điều khoản thanh toán
     * @param string|null $deliveryTerms Điều khoản giao hàng
     * @param float|null $totalAmount Tổng giá trị
     * @param float|null $taxAmount Tổng thuế
     * @param string|null $expectedDelivery Ngày giao dự kiến
     * @param string|null $note Ghi chú
     * @param string|null $createdAt Thời điểm tạo
     * @param string|null $updatedAt Thời điểm cập nhật
     */
    public function __construct(
        ?string $id = null,
        ?string $poNumber = null,
        string $status = 'draft',
        ?string $supplierId = null,
        ?string $contractId = null,
        ?string $buyerId = null,
        ?string $paymentTerms = null,
        ?string $deliveryTerms = null,
        ?float $totalAmount = null,
        ?float $taxAmount = null,
        ?string $expectedDelivery = null,
        ?string $note = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->poNumber = $poNumber;
        $this->status = $status;
        $this->supplierId = $supplierId;
        $this->contractId = $contractId;
        $this->buyerId = $buyerId;
        $this->paymentTerms = $paymentTerms;
        $this->deliveryTerms = $deliveryTerms;
        $this->totalAmount = $totalAmount;
        $this->taxAmount = $taxAmount;
        $this->expectedDelivery = $expectedDelivery;
        $this->note = $note;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /** @return string|null Định danh */
    public function getId(): ?string { return $this->id; }

    /** @param string|null $id Định danh mới */
    public function setId(?string $id): void { $this->id = $id; }

    /** @return string|null Số PO */
    public function getPoNumber(): ?string { return $this->poNumber; }

    /** @param string|null $poNumber Số PO mới */
    public function setPoNumber(?string $poNumber): void { $this->poNumber = $poNumber; }

    /** @return string Trạng thái */
    public function getStatus(): string { return $this->status; }

    /** @param string $status Trạng thái mới */
    public function setStatus(string $status): void { $this->status = $status; }

    /** @return string|null ID nhà cung cấp */
    public function getSupplierId(): ?string { return $this->supplierId; }

    /** @param string|null $supplierId ID nhà cung cấp mới */
    public function setSupplierId(?string $supplierId): void { $this->supplierId = $supplierId; }

    /** @return string|null ID hợp đồng */
    public function getContractId(): ?string { return $this->contractId; }

    /** @param string|null $contractId ID hợp đồng mới */
    public function setContractId(?string $contractId): void { $this->contractId = $contractId; }

    /** @return string|null ID người mua */
    public function getBuyerId(): ?string { return $this->buyerId; }

    /** @param string|null $buyerId ID người mua mới */
    public function setBuyerId(?string $buyerId): void { $this->buyerId = $buyerId; }

    /** @return string|null Điều khoản thanh toán */
    public function getPaymentTerms(): ?string { return $this->paymentTerms; }

    /** @param string|null $paymentTerms Điều khoản thanh toán mới */
    public function setPaymentTerms(?string $paymentTerms): void { $this->paymentTerms = $paymentTerms; }

    /** @return string|null Điều khoản giao hàng */
    public function getDeliveryTerms(): ?string { return $this->deliveryTerms; }

    /** @param string|null $deliveryTerms Điều khoản giao hàng mới */
    public function setDeliveryTerms(?string $deliveryTerms): void { $this->deliveryTerms = $deliveryTerms; }

    /** @return float|null Tổng giá trị */
    public function getTotalAmount(): ?float { return $this->totalAmount; }

    /** @param float|null $totalAmount Tổng giá trị mới */
    public function setTotalAmount(?float $totalAmount): void { $this->totalAmount = $totalAmount; }

    /** @return float|null Tổng thuế */
    public function getTaxAmount(): ?float { return $this->taxAmount; }

    /** @param float|null $taxAmount Tổng thuế mới */
    public function setTaxAmount(?float $taxAmount): void { $this->taxAmount = $taxAmount; }

    /** @return string|null Ngày giao dự kiến */
    public function getExpectedDelivery(): ?string { return $this->expectedDelivery; }

    /** @param string|null $expectedDelivery Ngày giao dự kiến mới */
    public function setExpectedDelivery(?string $expectedDelivery): void { $this->expectedDelivery = $expectedDelivery; }

    /** @return string|null Ghi chú */
    public function getNote(): ?string { return $this->note; }

    /** @param string|null $note Ghi chú mới */
    public function setNote(?string $note): void { $this->note = $note; }

    /** @return string|null Thời điểm tạo */
    public function getCreatedAt(): ?string { return $this->createdAt; }

    /** @param string|null $createdAt Thời điểm tạo mới */
    public function setCreatedAt(?string $createdAt): void { $this->createdAt = $createdAt; }

    /** @return string|null Thời điểm cập nhật */
    public function getUpdatedAt(): ?string { return $this->updatedAt; }

    /** @param string|null $updatedAt Thời điểm cập nhật mới */
    public function setUpdatedAt(?string $updatedAt): void { $this->updatedAt = $updatedAt; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu PO dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->poNumber,
            'status' => $this->status,
            'supplier_id' => $this->supplierId,
            'contract_id' => $this->contractId,
            'buyer_id' => $this->buyerId,
            'payment_terms' => $this->paymentTerms,
            'delivery_terms' => $this->deliveryTerms,
            'total_amount' => $this->totalAmount,
            'tax_amount' => $this->taxAmount,
            'expected_delivery' => $this->expectedDelivery,
            'note' => $this->note,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

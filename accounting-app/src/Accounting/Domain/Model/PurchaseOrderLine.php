<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

/**
 * Dòng đơn đặt hàng (PO line) — Chi tiết một mặt hàng trong đơn đặt hàng.
 *
 * Mỗi dòng ghi nhận số lượng đặt, số lượng đã nhập, số lượng đã xuất hóa đơn,
 * đơn giá và thông tin liên quan đến yêu cầu mua hàng (PR).
 */
class PurchaseOrderLine
{
    private ?string $id;
    private ?string $poId;
    private ?string $prLineId;
    private ?string $itemId;
    private ?string $freeTextName;
    private ?float $qtyOrdered;
    private ?float $qtyReceived;
    private ?float $qtyInvoiced;
    private ?string $uomId;
    private ?float $unitPrice;

    /**
     * Khởi tạo dòng đơn đặt hàng.
     *
     * @param string|null $id Định danh
     * @param string|null $poId ID đơn đặt hàng
     * @param string|null $prLineId ID dòng yêu cầu mua hàng
     * @param string|null $itemId ID mặt hàng
     * @param string|null $freeTextName Tên tự nhập (nếu không chọn item)
     * @param float|null $qtyOrdered Số lượng đặt
     * @param float|null $qtyReceived Số lượng đã nhập
     * @param float|null $qtyInvoiced Số lượng đã xuất hóa đơn
     * @param string|null $uomId ID đơn vị tính
     * @param float|null $unitPrice Đơn giá
     */
    public function __construct(
        ?string $id = null,
        ?string $poId = null,
        ?string $prLineId = null,
        ?string $itemId = null,
        ?string $freeTextName = null,
        ?float $qtyOrdered = null,
        ?float $qtyReceived = null,
        ?float $qtyInvoiced = null,
        ?string $uomId = null,
        ?float $unitPrice = null
    ) {
        $this->id = $id;
        $this->poId = $poId;
        $this->prLineId = $prLineId;
        $this->itemId = $itemId;
        $this->freeTextName = $freeTextName;
        $this->qtyOrdered = $qtyOrdered;
        $this->qtyReceived = $qtyReceived;
        $this->qtyInvoiced = $qtyInvoiced;
        $this->uomId = $uomId;
        $this->unitPrice = $unitPrice;
    }

    /** @return string|null Định danh */
    public function getId(): ?string { return $this->id; }

    /** @param string|null $id Định danh mới */
    public function setId(?string $id): void { $this->id = $id; }

    /** @return string|null ID đơn đặt hàng */
    public function getPoId(): ?string { return $this->poId; }

    /** @param string|null $poId ID đơn hàng mới */
    public function setPoId(?string $poId): void { $this->poId = $poId; }

    /** @return string|null ID dòng yêu cầu mua hàng */
    public function getPrLineId(): ?string { return $this->prLineId; }

    /** @param string|null $prLineId ID dòng PR mới */
    public function setPrLineId(?string $prLineId): void { $this->prLineId = $prLineId; }

    /** @return string|null ID mặt hàng */
    public function getItemId(): ?string { return $this->itemId; }

    /** @param string|null $itemId ID mặt hàng mới */
    public function setItemId(?string $itemId): void { $this->itemId = $itemId; }

    /** @return string|null Tên tự nhập */
    public function getFreeTextName(): ?string { return $this->freeTextName; }

    /** @param string|null $freeTextName Tên tự nhập mới */
    public function setFreeTextName(?string $freeTextName): void { $this->freeTextName = $freeTextName; }

    /** @return float|null Số lượng đặt */
    public function getQtyOrdered(): ?float { return $this->qtyOrdered; }

    /** @param float|null $qtyOrdered Số lượng đặt mới */
    public function setQtyOrdered(?float $qtyOrdered): void { $this->qtyOrdered = $qtyOrdered; }

    /** @return float|null Số lượng đã nhập */
    public function getQtyReceived(): ?float { return $this->qtyReceived; }

    /** @param float|null $qtyReceived Số lượng đã nhập mới */
    public function setQtyReceived(?float $qtyReceived): void { $this->qtyReceived = $qtyReceived; }

    /** @return float|null Số lượng đã xuất hóa đơn */
    public function getQtyInvoiced(): ?float { return $this->qtyInvoiced; }

    /** @param float|null $qtyInvoiced Số lượng đã xuất hóa đơn mới */
    public function setQtyInvoiced(?float $qtyInvoiced): void { $this->qtyInvoiced = $qtyInvoiced; }

    /** @return string|null ID đơn vị tính */
    public function getUomId(): ?string { return $this->uomId; }

    /** @param string|null $uomId ID đơn vị tính mới */
    public function setUomId(?string $uomId): void { $this->uomId = $uomId; }

    /** @return float|null Đơn giá */
    public function getUnitPrice(): ?float { return $this->unitPrice; }

    /** @param float|null $unitPrice Đơn giá mới */
    public function setUnitPrice(?float $unitPrice): void { $this->unitPrice = $unitPrice; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu dòng PO dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'po_id' => $this->poId,
            'pr_line_id' => $this->prLineId,
            'item_id' => $this->itemId,
            'free_text_name' => $this->freeTextName,
            'qty_ordered' => $this->qtyOrdered,
            'qty_received' => $this->qtyReceived,
            'qty_invoiced' => $this->qtyInvoiced,
            'uom_id' => $this->uomId,
            'unit_price' => $this->unitPrice,
        ];
    }
}

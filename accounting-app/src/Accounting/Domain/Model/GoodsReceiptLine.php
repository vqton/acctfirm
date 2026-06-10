<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

/**
 * Dòng phiếu nhập kho — Mẫu 01-VT theo Thông tư 99/2025/TT-BTC.
 *
 * Mỗi dòng là một mặt hàng trong phiếu nhập kho, ghi nhận số lượng
 * nhập, số lượng từ chối, đơn giá, thành tiền, lô và hạn dùng.
 *
 * NGHIỆP VỤ:
 * - $qtyReceived: số lượng thực nhập
 * - $qtyRejected: số lượng từ chối (hỏng, sai quy cách)
 * - $batchNo: số lô để theo dõi FIFO
 * - $expiryDate: hạn sử dụng (cho hàng có hạn)
 * - $qtyInDocument: số lượng trên chứng từ gốc (hóa đơn nhà cung cấp)
 */
class GoodsReceiptLine
{
    private ?string $id;
    private ?string $grId;
    private ?string $poLineId;
    private ?string $itemId;
    private ?string $itemName;
    private ?string $itemCode;
    private ?string $uom;
    private ?float $qtyReceived;
    private ?float $qtyRejected;
    private ?string $batchNo;
    private ?string $expiryDate;
    private ?float $unitPrice;
    private ?float $total;
    private int $lineNumber;
    private ?float $qtyInDocument;

    /**
     * Khởi tạo dòng phiếu nhập kho.
     *
     * @param string|null $id Định danh
     * @param string|null $grId ID phiếu nhập
     * @param string|null $poLineId ID dòng đơn hàng
     * @param string|null $itemId ID mặt hàng
     * @param string|null $itemName Tên mặt hàng
     * @param string|null $itemCode Mã mặt hàng
     * @param string|null $uom Đơn vị tính
     * @param float|null $qtyReceived Số lượng thực nhập
     * @param float|null $qtyRejected Số lượng từ chối
     * @param string|null $batchNo Số lô
     * @param string|null $expiryDate Hạn sử dụng
     * @param float|null $unitPrice Đơn giá
     * @param float|null $total Thành tiền
     * @param int $lineNumber Số dòng
     * @param float|null $qtyInDocument Số lượng trên chứng từ
     */
    public function __construct(
        ?string $id = null,
        ?string $grId = null,
        ?string $poLineId = null,
        ?string $itemId = null,
        ?string $itemName = null,
        ?string $itemCode = null,
        ?string $uom = null,
        ?float $qtyReceived = null,
        ?float $qtyRejected = null,
        ?string $batchNo = null,
        ?string $expiryDate = null,
        ?float $unitPrice = null,
        ?float $total = null,
        int $lineNumber = 0,
        ?float $qtyInDocument = null
    ) {
        $this->id = $id;
        $this->grId = $grId;
        $this->poLineId = $poLineId;
        $this->itemId = $itemId;
        $this->itemName = $itemName;
        $this->itemCode = $itemCode;
        $this->uom = $uom;
        $this->qtyReceived = $qtyReceived;
        $this->qtyRejected = $qtyRejected;
        $this->batchNo = $batchNo;
        $this->expiryDate = $expiryDate;
        $this->unitPrice = $unitPrice;
        $this->total = $total;
        $this->lineNumber = $lineNumber;
        $this->qtyInDocument = $qtyInDocument;
    }

    /** @return string|null Định danh dòng */
    public function getId(): ?string { return $this->id; }

    /** @param string|null $v Định danh mới */
    public function setId(?string $v): void { $this->id = $v; }

    /** @return string|null ID phiếu nhập */
    public function getGrId(): ?string { return $this->grId; }

    /** @param string|null $v ID phiếu nhập mới */
    public function setGrId(?string $v): void { $this->grId = $v; }

    /** @return string|null ID dòng đơn hàng */
    public function getPoLineId(): ?string { return $this->poLineId; }

    /** @param string|null $v ID dòng đơn hàng mới */
    public function setPoLineId(?string $v): void { $this->poLineId = $v; }

    /** @return string|null ID mặt hàng */
    public function getItemId(): ?string { return $this->itemId; }

    /** @param string|null $v ID mặt hàng mới */
    public function setItemId(?string $v): void { $this->itemId = $v; }

    /** @return string|null Tên mặt hàng */
    public function getItemName(): ?string { return $this->itemName; }

    /** @param string|null $v Tên mặt hàng mới */
    public function setItemName(?string $v): void { $this->itemName = $v; }

    /** @return string|null Mã mặt hàng */
    public function getItemCode(): ?string { return $this->itemCode; }

    /** @param string|null $v Mã mặt hàng mới */
    public function setItemCode(?string $v): void { $this->itemCode = $v; }

    /** @return string|null Đơn vị tính */
    public function getUom(): ?string { return $this->uom; }

    /** @param string|null $v Đơn vị tính mới */
    public function setUom(?string $v): void { $this->uom = $v; }

    /** @return float|null Số lượng thực nhập */
    public function getQtyReceived(): ?float { return $this->qtyReceived; }

    /** @param float|null $v Số lượng thực nhập mới */
    public function setQtyReceived(?float $v): void { $this->qtyReceived = $v; }

    /** @return float|null Số lượng từ chối */
    public function getQtyRejected(): ?float { return $this->qtyRejected; }

    /** @param float|null $v Số lượng từ chối mới */
    public function setQtyRejected(?float $v): void { $this->qtyRejected = $v; }

    /** @return string|null Số lô */
    public function getBatchNo(): ?string { return $this->batchNo; }

    /** @param string|null $v Số lô mới */
    public function setBatchNo(?string $v): void { $this->batchNo = $v; }

    /** @return string|null Hạn sử dụng */
    public function getExpiryDate(): ?string { return $this->expiryDate; }

    /** @param string|null $v Hạn sử dụng mới */
    public function setExpiryDate(?string $v): void { $this->expiryDate = $v; }

    /** @return float|null Đơn giá */
    public function getUnitPrice(): ?float { return $this->unitPrice; }

    /** @param float|null $v Đơn giá mới */
    public function setUnitPrice(?float $v): void { $this->unitPrice = $v; }

    /** @return float|null Thành tiền */
    public function getTotal(): ?float { return $this->total; }

    /** @param float|null $v Thành tiền mới */
    public function setTotal(?float $v): void { $this->total = $v; }

    /** @return int Số dòng */
    public function getLineNumber(): int { return $this->lineNumber; }

    /** @param int $v Số dòng mới */
    public function setLineNumber(int $v): void { $this->lineNumber = $v; }

    /** @return float|null Số lượng trên chứng từ */
    public function getQtyInDocument(): ?float { return $this->qtyInDocument; }

    /** @param float|null $v Số lượng trên chứng từ mới */
    public function setQtyInDocument(?float $v): void { $this->qtyInDocument = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu dòng phiếu nhập dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gr_id' => $this->grId,
            'po_line_id' => $this->poLineId,
            'item_id' => $this->itemId,
            'item_name' => $this->itemName,
            'item_code' => $this->itemCode,
            'uom' => $this->uom,
            'qty_received' => $this->qtyReceived,
            'qty_rejected' => $this->qtyRejected,
            'batch_no' => $this->batchNo,
            'expiry_date' => $this->expiryDate,
            'unit_price' => $this->unitPrice,
            'total' => $this->total,
            'line_number' => $this->lineNumber,
            'qty_in_document' => $this->qtyInDocument,
        ];
    }
}

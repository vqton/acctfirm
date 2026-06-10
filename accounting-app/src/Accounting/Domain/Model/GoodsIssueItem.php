<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

/**
 * Dòng phiếu xuất kho — Mẫu 02-VT theo Thông tư 99/2025/TT-BTC.
 *
 * Mỗi dòng là một mặt hàng trong phiếu xuất kho, ghi nhận số lượng
 * yêu cầu và thực xuất, đơn giá, thành tiền.
 *
 * NGHIỆP VỤ:
 * - $requestedQty: số lượng yêu cầu
 * - $actualQty: số lượng thực xuất
 * - $unitPrice: đơn giá xuất kho (tính theo phương pháp FIFO/bình quân)
 * - $totalAmount: thành tiền = actualQty * unitPrice
 */
class GoodsIssueItem
{
    private ?int $id;
    private string $issueId;
    private string $itemId;
    private string $itemCode;
    private string $itemName;
    private ?string $uom;
    private float $requestedQty;
    private float $actualQty;
    private float $unitPrice;
    private float $totalAmount;
    private int $lineNumber;
    private ?string $transactionId;

    /**
     * Khởi tạo dòng phiếu xuất kho.
     *
     * @param string $issueId ID phiếu xuất
     * @param string $itemId ID mặt hàng
     * @param string $itemCode Mã mặt hàng
     * @param string $itemName Tên mặt hàng
     * @param float $requestedQty Số lượng yêu cầu
     * @param float $actualQty Số lượng thực xuất
     * @param float $unitPrice Đơn giá
     * @param float $totalAmount Thành tiền
     * @param int $lineNumber Số dòng
     * @param int|null $id Định danh
     * @param string|null $uom Đơn vị tính
     * @param string|null $transactionId ID giao dịch đã post
     */
    public function __construct(
        string $issueId, string $itemId, string $itemCode, string $itemName,
        float $requestedQty, float $actualQty, float $unitPrice, float $totalAmount,
        int $lineNumber = 0, ?int $id = null, ?string $uom = null, ?string $transactionId = null
    ) {
        $this->issueId = $issueId;
        $this->itemId = $itemId;
        $this->itemCode = $itemCode;
        $this->itemName = $itemName;
        $this->requestedQty = $requestedQty;
        $this->actualQty = $actualQty;
        $this->unitPrice = $unitPrice;
        $this->totalAmount = $totalAmount;
        $this->lineNumber = $lineNumber;
        $this->id = $id;
        $this->uom = $uom;
        $this->transactionId = $transactionId;
    }

    /** @return int|null Định danh dòng */
    public function getId(): ?int { return $this->id; }

    /** @return string ID phiếu xuất */
    public function getIssueId(): string { return $this->issueId; }

    /** @return string ID mặt hàng */
    public function getItemId(): string { return $this->itemId; }

    /** @return string Mã mặt hàng */
    public function getItemCode(): string { return $this->itemCode; }

    /** @return string Tên mặt hàng */
    public function getItemName(): string { return $this->itemName; }

    /** @return string|null Đơn vị tính */
    public function getUom(): ?string { return $this->uom; }

    /** @return float Số lượng yêu cầu */
    public function getRequestedQty(): float { return $this->requestedQty; }

    /** @return float Số lượng thực xuất */
    public function getActualQty(): float { return $this->actualQty; }

    /** @return float Đơn giá */
    public function getUnitPrice(): float { return $this->unitPrice; }

    /** @return float Thành tiền */
    public function getTotalAmount(): float { return $this->totalAmount; }

    /** @return int Số dòng */
    public function getLineNumber(): int { return $this->lineNumber; }

    /** @return string|null ID giao dịch đã post */
    public function getTransactionId(): ?string { return $this->transactionId; }

    /** @param string|null $v ID giao dịch */
    public function setTransactionId(?string $v): void { $this->transactionId = $v; }

    /** @param float $v Đơn giá mới */
    public function setUnitPrice(float $v): void { $this->unitPrice = $v; }

    /** @param float $v Thành tiền mới */
    public function setTotalAmount(float $v): void { $this->totalAmount = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu dòng phiếu xuất dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'issue_id' => $this->issueId,
            'item_id' => $this->itemId,
            'item_code' => $this->itemCode,
            'item_name' => $this->itemName,
            'uom' => $this->uom,
            'requested_qty' => $this->requestedQty,
            'actual_qty' => $this->actualQty,
            'unit_price' => $this->unitPrice,
            'total_amount' => $this->totalAmount,
            'line_number' => $this->lineNumber,
            'transaction_id' => $this->transactionId,
        ];
    }
}

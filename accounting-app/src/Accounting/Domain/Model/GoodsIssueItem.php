<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

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

    public function getId(): ?int { return $this->id; }
    public function getIssueId(): string { return $this->issueId; }
    public function getItemId(): string { return $this->itemId; }
    public function getItemCode(): string { return $this->itemCode; }
    public function getItemName(): string { return $this->itemName; }
    public function getUom(): ?string { return $this->uom; }
    public function getRequestedQty(): float { return $this->requestedQty; }
    public function getActualQty(): float { return $this->actualQty; }
    public function getUnitPrice(): float { return $this->unitPrice; }
    public function getTotalAmount(): float { return $this->totalAmount; }
    public function getLineNumber(): int { return $this->lineNumber; }
    public function getTransactionId(): ?string { return $this->transactionId; }
    public function setTransactionId(?string $v): void { $this->transactionId = $v; }
    public function setUnitPrice(float $v): void { $this->unitPrice = $v; }
    public function setTotalAmount(float $v): void { $this->totalAmount = $v; }

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

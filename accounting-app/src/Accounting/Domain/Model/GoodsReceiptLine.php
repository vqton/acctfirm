<?php
declare(strict_types=1);
// Dòng phiếu nhập kho — Mẫu 01-VT
namespace Accounting\Domain\Model;

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
        int $lineNumber = 0
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
    }

    public function getId(): ?string { return $this->id; }
    public function setId(?string $v): void { $this->id = $v; }
    public function getGrId(): ?string { return $this->grId; }
    public function setGrId(?string $v): void { $this->grId = $v; }
    public function getPoLineId(): ?string { return $this->poLineId; }
    public function setPoLineId(?string $v): void { $this->poLineId = $v; }
    public function getItemId(): ?string { return $this->itemId; }
    public function setItemId(?string $v): void { $this->itemId = $v; }
    public function getItemName(): ?string { return $this->itemName; }
    public function setItemName(?string $v): void { $this->itemName = $v; }
    public function getItemCode(): ?string { return $this->itemCode; }
    public function setItemCode(?string $v): void { $this->itemCode = $v; }
    public function getUom(): ?string { return $this->uom; }
    public function setUom(?string $v): void { $this->uom = $v; }
    public function getQtyReceived(): ?float { return $this->qtyReceived; }
    public function setQtyReceived(?float $v): void { $this->qtyReceived = $v; }
    public function getQtyRejected(): ?float { return $this->qtyRejected; }
    public function setQtyRejected(?float $v): void { $this->qtyRejected = $v; }
    public function getBatchNo(): ?string { return $this->batchNo; }
    public function setBatchNo(?string $v): void { $this->batchNo = $v; }
    public function getExpiryDate(): ?string { return $this->expiryDate; }
    public function setExpiryDate(?string $v): void { $this->expiryDate = $v; }
    public function getUnitPrice(): ?float { return $this->unitPrice; }
    public function setUnitPrice(?float $v): void { $this->unitPrice = $v; }
    public function getTotal(): ?float { return $this->total; }
    public function setTotal(?float $v): void { $this->total = $v; }
    public function getLineNumber(): int { return $this->lineNumber; }
    public function setLineNumber(int $v): void { $this->lineNumber = $v; }

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
        ];
    }
}

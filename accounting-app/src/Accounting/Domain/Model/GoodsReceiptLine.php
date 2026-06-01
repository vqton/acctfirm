<?php

declare(strict_types=1);

namespace Accounting\Domain\Model;

class GoodsReceiptLine
{
    private ?string $id;
    private ?string $grId;
    private ?string $poLineId;
    private ?string $itemId;
    private ?float $qtyReceived;
    private ?float $qtyRejected;
    private ?string $batchNo;
    private ?string $expiryDate;
    private ?float $unitPrice;

    public function __construct(
        ?string $id = null,
        ?string $grId = null,
        ?string $poLineId = null,
        ?string $itemId = null,
        ?float $qtyReceived = null,
        ?float $qtyRejected = null,
        ?string $batchNo = null,
        ?string $expiryDate = null,
        ?float $unitPrice = null
    ) {
        $this->id = $id;
        $this->grId = $grId;
        $this->poLineId = $poLineId;
        $this->itemId = $itemId;
        $this->qtyReceived = $qtyReceived;
        $this->qtyRejected = $qtyRejected;
        $this->batchNo = $batchNo;
        $this->expiryDate = $expiryDate;
        $this->unitPrice = $unitPrice;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getGrId(): ?string
    {
        return $this->grId;
    }

    public function setGrId(?string $grId): void
    {
        $this->grId = $grId;
    }

    public function getPoLineId(): ?string
    {
        return $this->poLineId;
    }

    public function setPoLineId(?string $poLineId): void
    {
        $this->poLineId = $poLineId;
    }

    public function getItemId(): ?string
    {
        return $this->itemId;
    }

    public function setItemId(?string $itemId): void
    {
        $this->itemId = $itemId;
    }

    public function getQtyReceived(): ?float
    {
        return $this->qtyReceived;
    }

    public function setQtyReceived(?float $qtyReceived): void
    {
        $this->qtyReceived = $qtyReceived;
    }

    public function getQtyRejected(): ?float
    {
        return $this->qtyRejected;
    }

    public function setQtyRejected(?float $qtyRejected): void
    {
        $this->qtyRejected = $qtyRejected;
    }

    public function getBatchNo(): ?string
    {
        return $this->batchNo;
    }

    public function setBatchNo(?string $batchNo): void
    {
        $this->batchNo = $batchNo;
    }

    public function getExpiryDate(): ?string
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?string $expiryDate): void
    {
        $this->expiryDate = $expiryDate;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?float $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gr_id' => $this->grId,
            'po_line_id' => $this->poLineId,
            'item_id' => $this->itemId,
            'qty_received' => $this->qtyReceived,
            'qty_rejected' => $this->qtyRejected,
            'batch_no' => $this->batchNo,
            'expiry_date' => $this->expiryDate,
            'unit_price' => $this->unitPrice,
        ];
    }
}

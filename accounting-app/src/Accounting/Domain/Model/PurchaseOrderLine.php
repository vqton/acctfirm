<?php

declare(strict_types=1);

namespace Accounting\Domain\Model;

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

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getPoId(): ?string
    {
        return $this->poId;
    }

    public function setPoId(?string $poId): void
    {
        $this->poId = $poId;
    }

    public function getPrLineId(): ?string
    {
        return $this->prLineId;
    }

    public function setPrLineId(?string $prLineId): void
    {
        $this->prLineId = $prLineId;
    }

    public function getItemId(): ?string
    {
        return $this->itemId;
    }

    public function setItemId(?string $itemId): void
    {
        $this->itemId = $itemId;
    }

    public function getFreeTextName(): ?string
    {
        return $this->freeTextName;
    }

    public function setFreeTextName(?string $freeTextName): void
    {
        $this->freeTextName = $freeTextName;
    }

    public function getQtyOrdered(): ?float
    {
        return $this->qtyOrdered;
    }

    public function setQtyOrdered(?float $qtyOrdered): void
    {
        $this->qtyOrdered = $qtyOrdered;
    }

    public function getQtyReceived(): ?float
    {
        return $this->qtyReceived;
    }

    public function setQtyReceived(?float $qtyReceived): void
    {
        $this->qtyReceived = $qtyReceived;
    }

    public function getQtyInvoiced(): ?float
    {
        return $this->qtyInvoiced;
    }

    public function setQtyInvoiced(?float $qtyInvoiced): void
    {
        $this->qtyInvoiced = $qtyInvoiced;
    }

    public function getUomId(): ?string
    {
        return $this->uomId;
    }

    public function setUomId(?string $uomId): void
    {
        $this->uomId = $uomId;
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

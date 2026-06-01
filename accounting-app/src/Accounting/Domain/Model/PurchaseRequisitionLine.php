<?php

declare(strict_types=1);

namespace Accounting\Domain\Model;

class PurchaseRequisitionLine
{
    private ?string $id;
    private ?string $prId;
    private ?string $itemId;
    private ?string $freeTextName;
    private ?float $qty;
    private ?string $uomId;
    private ?float $priceEstimate;
    private ?float $totalEstimate;
    private bool $isCatalog;

    public function __construct(
        ?string $id = null,
        ?string $prId = null,
        ?string $itemId = null,
        ?string $freeTextName = null,
        ?float $qty = null,
        ?string $uomId = null,
        ?float $priceEstimate = null,
        ?float $totalEstimate = null,
        bool $isCatalog = false
    ) {
        $this->id = $id;
        $this->prId = $prId;
        $this->itemId = $itemId;
        $this->freeTextName = $freeTextName;
        $this->qty = $qty;
        $this->uomId = $uomId;
        $this->priceEstimate = $priceEstimate;
        $this->totalEstimate = $totalEstimate;
        $this->isCatalog = $isCatalog;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getPrId(): ?string
    {
        return $this->prId;
    }

    public function setPrId(?string $prId): void
    {
        $this->prId = $prId;
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

    public function getQty(): ?float
    {
        return $this->qty;
    }

    public function setQty(?float $qty): void
    {
        $this->qty = $qty;
    }

    public function getUomId(): ?string
    {
        return $this->uomId;
    }

    public function setUomId(?string $uomId): void
    {
        $this->uomId = $uomId;
    }

    public function getPriceEstimate(): ?float
    {
        return $this->priceEstimate;
    }

    public function setPriceEstimate(?float $priceEstimate): void
    {
        $this->priceEstimate = $priceEstimate;
    }

    public function getTotalEstimate(): ?float
    {
        return $this->totalEstimate;
    }

    public function setTotalEstimate(?float $totalEstimate): void
    {
        $this->totalEstimate = $totalEstimate;
    }

    public function getIsCatalog(): bool
    {
        return $this->isCatalog;
    }

    public function setIsCatalog(bool $isCatalog): void
    {
        $this->isCatalog = $isCatalog;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pr_id' => $this->prId,
            'item_id' => $this->itemId,
            'free_text_name' => $this->freeTextName,
            'qty' => $this->qty,
            'uom_id' => $this->uomId,
            'price_estimate' => $this->priceEstimate,
            'total_estimate' => $this->totalEstimate,
            'is_catalog' => $this->isCatalog,
        ];
    }
}

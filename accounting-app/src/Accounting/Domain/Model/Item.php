<?php
namespace Accounting\Domain\Model;

class Item
{
    private string $id;
    private string $code;
    private string $name;
    private string $itemType;
    private string $unit;
    private float $purchasePrice;
    private float $salePrice;
    private float $stockQty;
    private float $minStock;
    private ?string $description;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $code, string $name, string $itemType = 'material',
        string $unit = 'cai', float $purchasePrice = 0, float $salePrice = 0,
        float $stockQty = 0, float $minStock = 0, ?string $description = null
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->itemType = $itemType;
        $this->unit = $unit;
        $this->purchasePrice = $purchasePrice;
        $this->salePrice = $salePrice;
        $this->stockQty = $stockQty;
        $this->minStock = $minStock;
        $this->description = $description;
        $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getItemType(): string { return $this->itemType; }
    public function getUnit(): string { return $this->unit; }
    public function getPurchasePrice(): float { return $this->purchasePrice; }
    public function getSalePrice(): float { return $this->salePrice; }
    public function getStockQty(): float { return $this->stockQty; }
    public function getMinStock(): float { return $this->minStock; }
    public function getDescription(): ?string { return $this->description; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setName(string $name): void { $this->name = $name; }
    public function setItemType(string $itemType): void { $this->itemType = $itemType; }
    public function setUnit(string $unit): void { $this->unit = $unit; }
    public function setPurchasePrice(float $price): void { $this->purchasePrice = $price; }
    public function setSalePrice(float $price): void { $this->salePrice = $price; }
    public function setStockQty(float $qty): void { $this->stockQty = $qty; }
    public function setMinStock(float $qty): void { $this->minStock = $qty; }
    public function setDescription(?string $desc): void { $this->description = $desc; }
    public function setStatus(bool $status): void { $this->status = $status; }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'item_type' => $this->itemType, 'unit' => $this->unit,
            'purchase_price' => $this->purchasePrice, 'sale_price' => $this->salePrice,
            'stock_qty' => $this->stockQty, 'min_stock' => $this->minStock,
            'description' => $this->description, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
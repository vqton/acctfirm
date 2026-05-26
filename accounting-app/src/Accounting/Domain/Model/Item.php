<?php
namespace Accounting\Domain\Model;

/**
 * Vật tư, hàng hóa — Danh mục nguyên vật liệu, công cụ, thành phẩm, hàng hóa.
 *
 * Item là đối tượng quản lý tồn kho, liên kết với:
 * - Warehouse (kho) → số lượng tồn theo kho
 * - ValuationMethod → phương pháp tính giá xuất kho (FIFO/bình quân)
 * - InventoryService → ghi nhận nhập/xuất kho và sinh bút toán liên quan
 *
 * NGHIỆP VỤ:
 * - $itemType: 'material' (152), 'tool' (153), 'product' (155), 'goods' (156)
 * - $purchasePrice: giá mua cuối cùng (tham khảo, không phải giá vốn thực tế)
 * - $valuationMethodId: xác định đơn giá xuất kho tại thời điểm ghi nhận
 * - $allowNegativeStock: cho phép âm kho (rủi ro — chỉ dùng trong nghiệp vụ đặc thù)
 *
 * RỦI RO:
 * - $stockQty là số dư trong bộ nhớ; số dư thực tế phải tính từ inventory_layers
 * - Nhập/xuất kho phải qua InventoryService để đảm bảo đồng bộ với bút toán
 * - $minStock: cảnh báo tồn kho tối thiểu → ảnh hưởng đến kế hoạch mua hàng
 */
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
    private ?string $valuationMethodId;
    private bool $allowNegativeStock;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $code, string $name, string $itemType = 'material',
        string $unit = 'cai', float $purchasePrice = 0, float $salePrice = 0,
        float $stockQty = 0, float $minStock = 0, ?string $description = null,
        ?string $valuationMethodId = null, bool $allowNegativeStock = false
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
        $this->valuationMethodId = $valuationMethodId;
        $this->allowNegativeStock = $allowNegativeStock;
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
    public function getValuationMethodId(): ?string { return $this->valuationMethodId; }
    public function getAllowNegativeStock(): bool { return $this->allowNegativeStock; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $v): void { $this->code = $v; }
    public function setName(string $v): void { $this->name = $v; }
    public function setItemType(string $v): void { $this->itemType = $v; }
    public function setUnit(string $v): void { $this->unit = $v; }
    public function setPurchasePrice(float $v): void { $this->purchasePrice = $v; }
    public function setSalePrice(float $v): void { $this->salePrice = $v; }
    public function setStockQty(float $v): void { $this->stockQty = $v; }
    public function setMinStock(float $v): void { $this->minStock = $v; }
    public function setDescription(?string $v): void { $this->description = $v; }
    public function setValuationMethodId(?string $v): void { $this->valuationMethodId = $v; }
    public function setAllowNegativeStock(bool $v): void { $this->allowNegativeStock = $v; }
    public function setStatus(bool $v): void { $this->status = $v; }

    // Chuyển đổi model thành mảng để response API.
    // 'item_type': 'material' (152), 'tool' (153), 'product' (155), 'goods' (156).
    // 'stock_qty' là số dư trong bộ nhớ; số dư thực tế phải tính từ inventory_layers.
    // 'valuation_method_id' xác định đơn giá xuất kho (FIFO/bình quân).
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'item_type' => $this->itemType, 'unit' => $this->unit,
            'purchase_price' => $this->purchasePrice, 'sale_price' => $this->salePrice,
            'stock_qty' => $this->stockQty, 'min_stock' => $this->minStock,
            'description' => $this->description,
            'valuation_method_id' => $this->valuationMethodId,
            'allow_negative_stock' => $this->allowNegativeStock,
            'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
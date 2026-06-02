<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

class SalesOrderLine
{
    private ?string $id;
    private ?string $salesOrderId;
    private int $lineNo;
    private ?int $itemId;
    private ?string $itemCode;
    private string $itemName;
    private ?string $unit;
    private float $qtyOrdered;
    private float $qtyShipped;
    private float $qtyInvoiced;
    private float $unitPrice;
    private float $discountPct;
    private float $discountAmount;
    private float $taxRate;
    private float $taxAmount;
    private float $lineTotal;
    private bool $isService;
    private int $sortOrder;

    public function __construct(
        ?string $id = null, ?string $salesOrderId = null, int $lineNo = 0,
        ?int $itemId = null, ?string $itemCode = null, string $itemName = '',
        ?string $unit = null, float $qtyOrdered = 0, float $qtyShipped = 0,
        float $qtyInvoiced = 0, float $unitPrice = 0, float $discountPct = 0,
        float $discountAmount = 0, float $taxRate = 10, float $taxAmount = 0,
        float $lineTotal = 0, bool $isService = false, int $sortOrder = 0
    ) {
        $this->id = $id;
        $this->salesOrderId = $salesOrderId;
        $this->lineNo = $lineNo;
        $this->itemId = $itemId;
        $this->itemCode = $itemCode;
        $this->itemName = $itemName;
        $this->unit = $unit;
        $this->qtyOrdered = $qtyOrdered;
        $this->qtyShipped = $qtyShipped;
        $this->qtyInvoiced = $qtyInvoiced;
        $this->unitPrice = $unitPrice;
        $this->discountPct = $discountPct;
        $this->discountAmount = $discountAmount;
        $this->taxRate = $taxRate;
        $this->taxAmount = $taxAmount;
        $this->lineTotal = $lineTotal;
        $this->isService = $isService;
        $this->sortOrder = $sortOrder;
    }

    public function getId(): ?string { return $this->id; }
    public function getSalesOrderId(): ?string { return $this->salesOrderId; }
    public function getLineNo(): int { return $this->lineNo; }
    public function getItemId(): ?int { return $this->itemId; }
    public function getItemCode(): ?string { return $this->itemCode; }
    public function getItemName(): string { return $this->itemName; }
    public function getUnit(): ?string { return $this->unit; }
    public function getQtyOrdered(): float { return $this->qtyOrdered; }
    public function getQtyShipped(): float { return $this->qtyShipped; }
    public function getQtyInvoiced(): float { return $this->qtyInvoiced; }
    public function getUnitPrice(): float { return $this->unitPrice; }
    public function getDiscountPct(): float { return $this->discountPct; }
    public function getDiscountAmount(): float { return $this->discountAmount; }
    public function getTaxRate(): float { return $this->taxRate; }
    public function getTaxAmount(): float { return $this->taxAmount; }
    public function getLineTotal(): float { return $this->lineTotal; }
    public function getIsService(): bool { return $this->isService; }
    public function getSortOrder(): int { return $this->sortOrder; }

    public function setSalesOrderId(string $id): void { $this->salesOrderId = $id; }
    public function setQtyShipped(float $qty): void { $this->qtyShipped = $qty; }
    public function setQtyInvoiced(float $qty): void { $this->qtyInvoiced = $qty; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sales_order_id' => $this->salesOrderId,
            'line_no' => $this->lineNo,
            'item_id' => $this->itemId,
            'item_code' => $this->itemCode,
            'item_name' => $this->itemName,
            'unit' => $this->unit,
            'qty_ordered' => $this->qtyOrdered,
            'qty_shipped' => $this->qtyShipped,
            'qty_invoiced' => $this->qtyInvoiced,
            'unit_price' => $this->unitPrice,
            'discount_pct' => $this->discountPct,
            'discount_amount' => $this->discountAmount,
            'tax_rate' => $this->taxRate,
            'tax_amount' => $this->taxAmount,
            'line_total' => $this->lineTotal,
            'is_service' => $this->isService,
            'sort_order' => $this->sortOrder,
        ];
    }
}

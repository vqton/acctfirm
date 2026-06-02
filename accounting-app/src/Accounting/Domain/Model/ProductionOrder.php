<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

class ProductionOrder
{
    private string $id;
    private string $reference;
    private string $productId;
    private ?string $bomId;
    private float $qty;
    private float $completedQty;
    private ?string $startDate;
    private ?string $endDate;
    private ?string $dueDate;
    private string $status;
    private float $materialCost;
    private float $laborCost;
    private float $overheadCost;
    private float $totalCost;
    private float $unitCost;
    private ?string $notes;
    private ?string $createdBy;

    public function __construct(string $id, string $reference, string $productId, float $qty, ?string $startDate = null, ?string $dueDate = null, ?string $bomId = null)
    {
        $this->id = $id; $this->reference = $reference; $this->productId = $productId;
        $this->bomId = $bomId; $this->qty = $qty; $this->completedQty = 0;
        $this->startDate = $startDate; $this->endDate = null; $this->dueDate = $dueDate;
        $this->status = 'draft'; $this->materialCost = 0; $this->laborCost = 0;
        $this->overheadCost = 0; $this->totalCost = 0; $this->unitCost = 0;
        $this->notes = null; $this->createdBy = null;
    }

    public function getId(): string { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function getProductId(): string { return $this->productId; }
    public function getBomId(): ?string { return $this->bomId; }
    public function getQty(): float { return $this->qty; }
    public function getCompletedQty(): float { return $this->completedQty; }
    public function getStartDate(): ?string { return $this->startDate; }
    public function getDueDate(): ?string { return $this->dueDate; }
    public function getStatus(): string { return $this->status; }
    public function getMaterialCost(): float { return $this->materialCost; }
    public function getLaborCost(): float { return $this->laborCost; }
    public function getOverheadCost(): float { return $this->overheadCost; }
    public function getTotalCost(): float { return $this->totalCost; }
    public function getUnitCost(): float { return $this->unitCost; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedBy(): ?string { return $this->createdBy; }

    public function setStatus(string $v): void { $this->status = $v; }
    public function setCompletedQty(float $v): void { $this->completedQty = $v; }
    public function setEndDate(?string $v): void { $this->endDate = $v; }
    public function setMaterialCost(float $v): void { $this->materialCost = $v; $this->recalc(); }
    public function setLaborCost(float $v): void { $this->laborCost = $v; $this->recalc(); }
    public function setOverheadCost(float $v): void { $this->overheadCost = $v; $this->recalc(); }
    public function setNotes(?string $v): void { $this->notes = $v; }
    public function setCreatedBy(?string $v): void { $this->createdBy = $v; }

    private function recalc(): void
    {
        $this->totalCost = $this->materialCost + $this->laborCost + $this->overheadCost;
        $this->unitCost = $this->completedQty > 0 ? $this->totalCost / $this->completedQty : 0;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'reference' => $this->reference, 'product_id' => $this->productId,
            'bom_id' => $this->bomId, 'qty' => $this->qty, 'completed_qty' => $this->completedQty,
            'start_date' => $this->startDate, 'due_date' => $this->dueDate,
            'status' => $this->status, 'material_cost' => $this->materialCost,
            'labor_cost' => $this->laborCost, 'overhead_cost' => $this->overheadCost,
            'total_cost' => $this->totalCost, 'unit_cost' => $this->unitCost,
            'notes' => $this->notes, 'created_by' => $this->createdBy,
        ];
    }
}

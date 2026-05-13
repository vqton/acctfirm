<?php
namespace Accounting\Domain\Model;

class Ccdc
{
    private string $id;
    private string $code;
    private string $name;
    private string $unit;
    private float $quantity;
    private string $allocationType;
    private float $totalCost;
    private float $allocated;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $code, string $name, string $unit = 'cai',
        float $quantity = 0, string $allocationType = 'direct', float $totalCost = 0, float $allocated = 0
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->unit = $unit;
        $this->quantity = $quantity;
        $this->allocationType = $allocationType;
        $this->totalCost = $totalCost;
        $this->allocated = $allocated;
        $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getUnit(): string { return $this->unit; }
    public function getQuantity(): float { return $this->quantity; }
    public function getAllocationType(): string { return $this->allocationType; }
    public function getTotalCost(): float { return $this->totalCost; }
    public function getAllocated(): float { return $this->allocated; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setName(string $name): void { $this->name = $name; }
    public function setUnit(string $unit): void { $this->unit = $unit; }
    public function setQuantity(float $qty): void { $this->quantity = $qty; }
    public function setAllocationType(string $type): void { $this->allocationType = $type; }
    public function setTotalCost(float $cost): void { $this->totalCost = $cost; }
    public function setAllocated(float $allocated): void { $this->allocated = $allocated; }
    public function setStatus(bool $status): void { $this->status = $status; }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'unit' => $this->unit, 'quantity' => $this->quantity,
            'allocation_type' => $this->allocationType, 'total_cost' => $this->totalCost,
            'allocated' => $this->allocated, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

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
    private int $allocationMonths;
    private string $expenseAccount;
    private ?string $allocationStartDate;
    private float $totalCost;
    private float $allocated;
    private int $remainingMonths;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $code, string $name, string $unit = 'cai',
        float $quantity = 0, string $allocationType = 'direct',
        int $allocationMonths = 0, string $expenseAccount = '642',
        ?string $allocationStartDate = null,
        float $totalCost = 0, float $allocated = 0, int $remainingMonths = 0
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->unit = $unit;
        $this->quantity = $quantity;
        $this->allocationType = $allocationType;
        $this->allocationMonths = $allocationMonths;
        $this->expenseAccount = $expenseAccount;
        $this->allocationStartDate = $allocationStartDate;
        $this->totalCost = $totalCost;
        $this->allocated = $allocated;
        $this->remainingMonths = $remainingMonths;
        $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getUnit(): string { return $this->unit; }
    public function getQuantity(): float { return $this->quantity; }
    public function getAllocationType(): string { return $this->allocationType; }
    public function getAllocationMonths(): int { return $this->allocationMonths; }
    public function getExpenseAccount(): string { return $this->expenseAccount; }
    public function getAllocationStartDate(): ?string { return $this->allocationStartDate; }
    public function getTotalCost(): float { return $this->totalCost; }
    public function getAllocated(): float { return $this->allocated; }
    public function getRemainingMonths(): int { return $this->remainingMonths; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setName(string $name): void { $this->name = $name; }
    public function setUnit(string $unit): void { $this->unit = $unit; }
    public function setQuantity(float $qty): void { $this->quantity = $qty; }
    public function setAllocationType(string $type): void { $this->allocationType = $type; }
    public function setAllocationMonths(int $months): void { $this->allocationMonths = $months; }
    public function setExpenseAccount(string $acct): void { $this->expenseAccount = $acct; }
    public function setAllocationStartDate(?string $date): void { $this->allocationStartDate = $date; }
    public function setTotalCost(float $cost): void { $this->totalCost = $cost; }
    public function setAllocated(float $allocated): void { $this->allocated = $allocated; }
    public function setRemainingMonths(int $months): void { $this->remainingMonths = $months; }
    public function setStatus(bool $status): void { $this->status = $status; }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'unit' => $this->unit, 'quantity' => $this->quantity,
            'allocation_type' => $this->allocationType,
            'allocation_months' => $this->allocationMonths,
            'expense_account' => $this->expenseAccount,
            'allocation_start_date' => $this->allocationStartDate,
            'total_cost' => $this->totalCost, 'allocated' => $this->allocated,
            'remaining_months' => $this->remainingMonths,
            'remaining_value' => $this->totalCost - $this->allocated,
            'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

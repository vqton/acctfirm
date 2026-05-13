<?php
namespace Accounting\Domain\Model;

class FixedAsset
{
    private string $id;
    private string $code;
    private string $name;
    private string $purchaseDate;
    private float $originalCost;
    private string $depreciationMethod;
    private int $usefulLife;
    private float $salvageValue;
    private float $monthlyDepreciation;
    private float $accumulatedDepreciation;
    private float $netBookValue;
    private ?string $departmentId;
    private ?string $employeeId;
    private ?string $location;
    private bool $status;
    private ?string $notes;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $code, string $name, string $purchaseDate,
        float $originalCost, string $depreciationMethod = 'straight_line', int $usefulLife = 0,
        float $salvageValue = 0, float $monthlyDepreciation = 0, float $accumulatedDepreciation = 0,
        float $netBookValue = 0, ?string $departmentId = null, ?string $employeeId = null,
        ?string $location = null, ?string $notes = null
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->purchaseDate = $purchaseDate;
        $this->originalCost = $originalCost;
        $this->depreciationMethod = $depreciationMethod;
        $this->usefulLife = $usefulLife;
        $this->salvageValue = $salvageValue;
        $this->monthlyDepreciation = $monthlyDepreciation;
        $this->accumulatedDepreciation = $accumulatedDepreciation;
        $this->netBookValue = $netBookValue;
        $this->departmentId = $departmentId;
        $this->employeeId = $employeeId;
        $this->location = $location;
        $this->status = true;
        $this->notes = $notes;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getPurchaseDate(): string { return $this->purchaseDate; }
    public function getOriginalCost(): float { return $this->originalCost; }
    public function getDepreciationMethod(): string { return $this->depreciationMethod; }
    public function getUsefulLife(): int { return $this->usefulLife; }
    public function getSalvageValue(): float { return $this->salvageValue; }
    public function getMonthlyDepreciation(): float { return $this->monthlyDepreciation; }
    public function getAccumulatedDepreciation(): float { return $this->accumulatedDepreciation; }
    public function getNetBookValue(): float { return $this->netBookValue; }
    public function getDepartmentId(): ?string { return $this->departmentId; }
    public function getEmployeeId(): ?string { return $this->employeeId; }
    public function getLocation(): ?string { return $this->location; }
    public function isStatus(): bool { return $this->status; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setName(string $name): void { $this->name = $name; }
    public function setPurchaseDate(string $date): void { $this->purchaseDate = $date; }
    public function setOriginalCost(float $cost): void { $this->originalCost = $cost; }
    public function setDepreciationMethod(string $method): void { $this->depreciationMethod = $method; }
    public function setUsefulLife(int $life): void { $this->usefulLife = $life; }
    public function setSalvageValue(float $value): void { $this->salvageValue = $value; }
    public function setMonthlyDepreciation(float $dep): void { $this->monthlyDepreciation = $dep; }
    public function setAccumulatedDepreciation(float $dep): void { $this->accumulatedDepreciation = $dep; }
    public function setNetBookValue(float $value): void { $this->netBookValue = $value; }
    public function setDepartmentId(?string $id): void { $this->departmentId = $id; }
    public function setEmployeeId(?string $id): void { $this->employeeId = $id; }
    public function setLocation(?string $location): void { $this->location = $location; }
    public function setStatus(bool $status): void { $this->status = $status; }
    public function setNotes(?string $notes): void { $this->notes = $notes; }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'purchase_date' => $this->purchaseDate, 'original_cost' => $this->originalCost,
            'depreciation_method' => $this->depreciationMethod, 'useful_life' => $this->usefulLife,
            'salvage_value' => $this->salvageValue, 'monthly_depreciation' => $this->monthlyDepreciation,
            'accumulated_depreciation' => $this->accumulatedDepreciation,
            'net_book_value' => $this->netBookValue, 'department_id' => $this->departmentId,
            'employee_id' => $this->employeeId, 'location' => $this->location,
            'status' => $this->status, 'notes' => $this->notes,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

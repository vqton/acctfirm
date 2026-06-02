<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

class Project
{
    private string $id;
    private string $code;
    private string $name;
    private string $customerId;
    private ?string $managerId;
    private string $startDate;
    private ?string $endDate;
    private float $budget;
    private float $actualCost;
    private float $billedAmount;
    private float $revenueRecognized;
    private float $estimatedCompletionPct;
    private string $status;
    private ?string $notes;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $code, string $name, string $customerId, string $startDate,
        ?string $endDate = null, float $budget = 0, ?string $notes = null,
        ?string $managerId = null
    ) {
        $this->id = $id; $this->code = $code; $this->name = $name;
        $this->customerId = $customerId; $this->managerId = $managerId;
        $this->startDate = $startDate; $this->endDate = $endDate;
        $this->budget = $budget; $this->actualCost = 0; $this->billedAmount = 0;
        $this->revenueRecognized = 0; $this->estimatedCompletionPct = 0;
        $this->status = 'active'; $this->notes = $notes;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getCustomerId(): string { return $this->customerId; }
    public function getManagerId(): ?string { return $this->managerId; }
    public function getStartDate(): string { return $this->startDate; }
    public function getEndDate(): ?string { return $this->endDate; }
    public function getBudget(): float { return $this->budget; }
    public function getActualCost(): float { return $this->actualCost; }
    public function getBilledAmount(): float { return $this->billedAmount; }
    public function getRevenueRecognized(): float { return $this->revenueRecognized; }
    public function getEstimatedCompletionPct(): float { return $this->estimatedCompletionPct; }
    public function getStatus(): string { return $this->status; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $v): void { $this->code = $v; }
    public function setName(string $v): void { $this->name = $v; }
    public function setCustomerId(string $v): void { $this->customerId = $v; }
    public function setManagerId(?string $v): void { $this->managerId = $v; }
    public function setStartDate(string $v): void { $this->startDate = $v; }
    public function setEndDate(?string $v): void { $this->endDate = $v; }
    public function setBudget(float $v): void { $this->budget = $v; }
    public function setActualCost(float $v): void { $this->actualCost = $v; }
    public function setBilledAmount(float $v): void { $this->billedAmount = $v; }
    public function setRevenueRecognized(float $v): void { $this->revenueRecognized = $v; }
    public function setEstimatedCompletionPct(float $v): void { $this->estimatedCompletionPct = $v; }
    public function setStatus(string $v): void { $this->status = $v; }
    public function setNotes(?string $v): void { $this->notes = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'customer_id' => $this->customerId, 'manager_id' => $this->managerId,
            'start_date' => $this->startDate, 'end_date' => $this->endDate,
            'budget' => $this->budget, 'actual_cost' => $this->actualCost,
            'billed_amount' => $this->billedAmount,
            'revenue_recognized' => $this->revenueRecognized,
            'estimated_completion_pct' => $this->estimatedCompletionPct,
            'status' => $this->status, 'notes' => $this->notes,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

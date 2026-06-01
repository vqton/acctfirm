<?php

declare(strict_types=1);

namespace Accounting\Domain\Model;

class PurchaseBudget
{
    private ?string $id;
    private ?string $departmentId;
    private ?string $period;
    private ?float $budgetAmount;
    private ?float $committedAmount;
    private ?float $actualAmount;

    public function __construct(
        ?string $id = null,
        ?string $departmentId = null,
        ?string $period = null,
        ?float $budgetAmount = null,
        ?float $committedAmount = null,
        ?float $actualAmount = null
    ) {
        $this->id = $id;
        $this->departmentId = $departmentId;
        $this->period = $period;
        $this->budgetAmount = $budgetAmount;
        $this->committedAmount = $committedAmount;
        $this->actualAmount = $actualAmount;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getDepartmentId(): ?string
    {
        return $this->departmentId;
    }

    public function setDepartmentId(?string $departmentId): void
    {
        $this->departmentId = $departmentId;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(?string $period): void
    {
        $this->period = $period;
    }

    public function getBudgetAmount(): ?float
    {
        return $this->budgetAmount;
    }

    public function setBudgetAmount(?float $budgetAmount): void
    {
        $this->budgetAmount = $budgetAmount;
    }

    public function getCommittedAmount(): ?float
    {
        return $this->committedAmount;
    }

    public function setCommittedAmount(?float $committedAmount): void
    {
        $this->committedAmount = $committedAmount;
    }

    public function getActualAmount(): ?float
    {
        return $this->actualAmount;
    }

    public function setActualAmount(?float $actualAmount): void
    {
        $this->actualAmount = $actualAmount;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'department_id' => $this->departmentId,
            'period' => $this->period,
            'budget_amount' => $this->budgetAmount,
            'committed_amount' => $this->committedAmount,
            'actual_amount' => $this->actualAmount,
        ];
    }
}

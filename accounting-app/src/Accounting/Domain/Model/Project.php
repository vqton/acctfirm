<?php
namespace Accounting\Domain\Model;

class Project
{
    private string $id;
    private string $code;
    private string $name;
    private string $customerId;
    private string $startDate;
    private ?string $endDate;
    private float $budget;
    private bool $status;
    private ?string $notes;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $code, string $name, string $customerId, string $startDate,
        ?string $endDate = null, float $budget = 0, ?string $notes = null
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->customerId = $customerId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->budget = $budget;
        $this->status = true;
        $this->notes = $notes;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getCustomerId(): string { return $this->customerId; }
    public function getStartDate(): string { return $this->startDate; }
    public function getEndDate(): ?string { return $this->endDate; }
    public function getBudget(): float { return $this->budget; }
    public function isStatus(): bool { return $this->status; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setName(string $name): void { $this->name = $name; }
    public function setCustomerId(string $id): void { $this->customerId = $id; }
    public function setStartDate(string $date): void { $this->startDate = $date; }
    public function setEndDate(?string $date): void { $this->endDate = $date; }
    public function setBudget(float $budget): void { $this->budget = $budget; }
    public function setStatus(bool $status): void { $this->status = $status; }
    public function setNotes(?string $notes): void { $this->notes = $notes; }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'customer_id' => $this->customerId, 'start_date' => $this->startDate,
            'end_date' => $this->endDate, 'budget' => $this->budget,
            'status' => $this->status, 'notes' => $this->notes,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

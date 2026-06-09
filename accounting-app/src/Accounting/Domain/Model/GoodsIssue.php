<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

class GoodsIssue
{
    private ?string $id;
    private ?string $issueNumber;
    private ?string $issueDate;
    private ?string $warehouseId;
    private ?string $receiverName;
    private ?string $receiverDepartment;
    private ?string $issueReason;
    private string $issueType;
    private string $status;
    private ?string $reference;
    private ?string $notes;
    private float $totalAmount;
    private string $createdBy;
    private ?string $createdAt;
    private ?string $updatedAt;
    private array $lines;

    public function __construct(
        ?string $id = null,
        ?string $issueNumber = null,
        ?string $issueDate = null,
        ?string $warehouseId = null,
        ?string $receiverName = null,
        ?string $receiverDepartment = null,
        ?string $issueReason = null,
        string $issueType = 'sale',
        string $status = 'draft',
        ?string $reference = null,
        ?string $notes = null,
        float $totalAmount = 0.0,
        string $createdBy = 'system',
        ?string $createdAt = null,
        ?string $updatedAt = null,
        array $lines = []
    ) {
        $this->id = $id;
        $this->issueNumber = $issueNumber;
        $this->issueDate = $issueDate;
        $this->warehouseId = $warehouseId;
        $this->receiverName = $receiverName;
        $this->receiverDepartment = $receiverDepartment;
        $this->issueReason = $issueReason;
        $this->issueType = $issueType;
        $this->status = $status;
        $this->reference = $reference;
        $this->notes = $notes;
        $this->totalAmount = $totalAmount;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->lines = $lines;
    }

    public function getId(): ?string { return $this->id; }
    public function setId(?string $id): void { $this->id = $id; }
    public function getIssueNumber(): ?string { return $this->issueNumber; }
    public function setIssueNumber(?string $v): void { $this->issueNumber = $v; }
    public function getIssueDate(): ?string { return $this->issueDate; }
    public function setIssueDate(?string $v): void { $this->issueDate = $v; }
    public function getWarehouseId(): ?string { return $this->warehouseId; }
    public function setWarehouseId(?string $v): void { $this->warehouseId = $v; }
    public function getReceiverName(): ?string { return $this->receiverName; }
    public function setReceiverName(?string $v): void { $this->receiverName = $v; }
    public function getReceiverDepartment(): ?string { return $this->receiverDepartment; }
    public function setReceiverDepartment(?string $v): void { $this->receiverDepartment = $v; }
    public function getIssueReason(): ?string { return $this->issueReason; }
    public function setIssueReason(?string $v): void { $this->issueReason = $v; }
    public function getIssueType(): string { return $this->issueType; }
    public function setIssueType(string $v): void { $this->issueType = $v; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): void { $this->status = $v; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $v): void { $this->reference = $v; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): void { $this->notes = $v; }
    public function getTotalAmount(): float { return $this->totalAmount; }
    public function setTotalAmount(float $v): void { $this->totalAmount = $v; }
    public function getCreatedBy(): string { return $this->createdBy; }
    public function setCreatedBy(string $v): void { $this->createdBy = $v; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function setCreatedAt(?string $v): void { $this->createdAt = $v; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    public function setUpdatedAt(?string $v): void { $this->updatedAt = $v; }
    public function getLines(): array { return $this->lines; }
    public function setLines(array $v): void { $this->lines = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'issue_number' => $this->issueNumber,
            'issue_date' => $this->issueDate,
            'warehouse_id' => $this->warehouseId,
            'receiver_name' => $this->receiverName,
            'receiver_department' => $this->receiverDepartment,
            'issue_reason' => $this->issueReason,
            'issue_type' => $this->issueType,
            'status' => $this->status,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'total_amount' => $this->totalAmount,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'lines' => array_map(fn($l) => $l instanceof GoodsIssueItem ? $l->toArray() : $l, $this->lines),
        ];
    }
}

<?php
namespace Accounting\Domain\Model;

// Queue entry — hóa đơn quá hạn trong hàng đợi đòi nợ
class QueueEntry
{
    private ?int $id;
    private int $invoiceId;
    private string $customerId;
    private ?string $assignedTo;
    private string $status;
    private int $priority;
    private ?string $enteredAt;
    private ?string $lastActionDate;
    private ?string $nextActionDate;
    private int $escalationLevel;
    private ?string $holdReason;
    private ?string $holdUntil;
    private int $holdCount;
    private ?string $resolvedAt;
    private ?string $resolution;
    private ?string $resolutionNote;
    private ?string $createdBy;

    public function __construct(
        int $invoiceId,
        string $customerId,
        ?string $assignedTo = null,
        string $status = 'new',
        int $priority = 0,
        int $escalationLevel = 0,
        int $holdCount = 0,
        ?string $createdBy = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->invoiceId = $invoiceId;
        $this->customerId = $customerId;
        $this->assignedTo = $assignedTo;
        $this->status = $status;
        $this->priority = $priority;
        $this->escalationLevel = $escalationLevel;
        $this->holdCount = $holdCount;
        $this->createdBy = $createdBy;
    }

    public function getId(): ?int { return $this->id; }
    public function getInvoiceId(): int { return $this->invoiceId; }
    public function getCustomerId(): string { return $this->customerId; }
    public function getAssignedTo(): ?string { return $this->assignedTo; }
    public function getStatus(): string { return $this->status; }
    public function getPriority(): int { return $this->priority; }
    public function getEnteredAt(): ?string { return $this->enteredAt; }
    public function getLastActionDate(): ?string { return $this->lastActionDate; }
    public function getNextActionDate(): ?string { return $this->nextActionDate; }
    public function getEscalationLevel(): int { return $this->escalationLevel; }
    public function getHoldReason(): ?string { return $this->holdReason; }
    public function getHoldUntil(): ?string { return $this->holdUntil; }
    public function getHoldCount(): int { return $this->holdCount; }
    public function getResolvedAt(): ?string { return $this->resolvedAt; }
    public function getResolution(): ?string { return $this->resolution; }
    public function getResolutionNote(): ?string { return $this->resolutionNote; }
    public function getCreatedBy(): ?string { return $this->createdBy; }

    public function setAssignedTo(?string $v): void { $this->assignedTo = $v; }
    public function setStatus(string $v): void { $this->status = $v; }
    public function setPriority(int $v): void { $this->priority = $v; }
    public function setLastActionDate(?string $v): void { $this->lastActionDate = $v; }
    public function setNextActionDate(?string $v): void { $this->nextActionDate = $v; }
    public function setEscalationLevel(int $v): void { $this->escalationLevel = $v; }
    public function setHoldReason(?string $v): void { $this->holdReason = $v; }
    public function setHoldUntil(?string $v): void { $this->holdUntil = $v; }
    public function setHoldCount(int $v): void { $this->holdCount = $v; }
    public function setResolvedAt(?string $v): void { $this->resolvedAt = $v; }
    public function setResolution(?string $v): void { $this->resolution = $v; }
    public function setResolutionNote(?string $v): void { $this->resolutionNote = $v; }
    public function setEnteredAt(?string $v): void { $this->enteredAt = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoiceId,
            'customer_id' => $this->customerId,
            'assigned_to' => $this->assignedTo,
            'status' => $this->status,
            'priority' => $this->priority,
            'entered_at' => $this->enteredAt,
            'last_action_date' => $this->lastActionDate,
            'next_action_date' => $this->nextActionDate,
            'escalation_level' => $this->escalationLevel,
            'hold_reason' => $this->holdReason,
            'hold_until' => $this->holdUntil,
            'hold_count' => $this->holdCount,
            'resolved_at' => $this->resolvedAt,
            'resolution' => $this->resolution,
            'resolution_note' => $this->resolutionNote,
            'created_by' => $this->createdBy,
        ];
    }

    public static function fromRow(array $row): self
    {
        $e = new self(
            (int)$row['invoice_id'],
            $row['customer_id'],
            $row['assigned_to'] ?? null,
            $row['status'] ?? 'new',
            (int)($row['priority'] ?? 0),
            (int)($row['escalation_level'] ?? 0),
            (int)($row['hold_count'] ?? 0),
            $row['created_by'] ?? null,
            isset($row['id']) ? (int)$row['id'] : null
        );
        $e->enteredAt = $row['entered_at'] ?? null;
        $e->lastActionDate = $row['last_action_date'] ?? null;
        $e->nextActionDate = $row['next_action_date'] ?? null;
        $e->holdReason = $row['hold_reason'] ?? null;
        $e->holdUntil = $row['hold_until'] ?? null;
        $e->resolvedAt = $row['resolved_at'] ?? null;
        $e->resolution = $row['resolution'] ?? null;
        $e->resolutionNote = $row['resolution_note'] ?? null;
        return $e;
    }
}

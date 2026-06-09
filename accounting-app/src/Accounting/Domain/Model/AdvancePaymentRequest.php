<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

class AdvancePaymentRequest
{
    private ?string $id;
    private ?string $requestNumber;
    private ?string $requestDate;
    private ?string $requesterName;
    private ?string $requesterDepartment;
    private float $amount;
    private ?string $amountInWords;
    private ?string $reason;
    private ?string $repaymentTerm;
    private string $status;
    private ?string $notes;
    private int $entityId;
    private string $createdBy;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?string $id = null,
        ?string $requestNumber = null,
        ?string $requestDate = null,
        ?string $requesterName = null,
        ?string $requesterDepartment = null,
        float $amount = 0.0,
        ?string $amountInWords = null,
        ?string $reason = null,
        ?string $repaymentTerm = null,
        string $status = 'draft',
        ?string $notes = null,
        int $entityId = 1,
        string $createdBy = 'system',
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->requestNumber = $requestNumber;
        $this->requestDate = $requestDate;
        $this->requesterName = $requesterName;
        $this->requesterDepartment = $requesterDepartment;
        $this->amount = $amount;
        $this->amountInWords = $amountInWords;
        $this->reason = $reason;
        $this->repaymentTerm = $repaymentTerm;
        $this->status = $status;
        $this->notes = $notes;
        $this->entityId = $entityId;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?string { return $this->id; }
    public function setId(?string $v): void { $this->id = $v; }
    public function getRequestNumber(): ?string { return $this->requestNumber; }
    public function setRequestNumber(?string $v): void { $this->requestNumber = $v; }
    public function getRequestDate(): ?string { return $this->requestDate; }
    public function setRequestDate(?string $v): void { $this->requestDate = $v; }
    public function getRequesterName(): ?string { return $this->requesterName; }
    public function setRequesterName(?string $v): void { $this->requesterName = $v; }
    public function getRequesterDepartment(): ?string { return $this->requesterDepartment; }
    public function setRequesterDepartment(?string $v): void { $this->requesterDepartment = $v; }
    public function getAmount(): float { return $this->amount; }
    public function setAmount(float $v): void { $this->amount = $v; }
    public function getAmountInWords(): ?string { return $this->amountInWords; }
    public function setAmountInWords(?string $v): void { $this->amountInWords = $v; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $v): void { $this->reason = $v; }
    public function getRepaymentTerm(): ?string { return $this->repaymentTerm; }
    public function setRepaymentTerm(?string $v): void { $this->repaymentTerm = $v; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): void { $this->status = $v; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): void { $this->notes = $v; }
    public function getEntityId(): int { return $this->entityId; }
    public function setEntityId(int $v): void { $this->entityId = $v; }
    public function getCreatedBy(): string { return $this->createdBy; }
    public function setCreatedBy(string $v): void { $this->createdBy = $v; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function setCreatedAt(?string $v): void { $this->createdAt = $v; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    public function setUpdatedAt(?string $v): void { $this->updatedAt = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->requestNumber,
            'request_date' => $this->requestDate,
            'requester_name' => $this->requesterName,
            'requester_department' => $this->requesterDepartment,
            'amount' => $this->amount,
            'amount_in_words' => $this->amountInWords,
            'reason' => $this->reason,
            'repayment_term' => $this->repaymentTerm,
            'status' => $this->status,
            'notes' => $this->notes,
            'entity_id' => $this->entityId,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

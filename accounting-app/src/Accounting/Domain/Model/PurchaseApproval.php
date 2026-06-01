<?php

declare(strict_types=1);

namespace Accounting\Domain\Model;

class PurchaseApproval
{
    private ?string $id;
    private ?string $docType;
    private ?string $docId;
    private int $step;
    private ?string $approverId;
    private string $status;
    private ?string $note;
    private ?string $actedAt;
    private ?string $createdAt;

    public function __construct(
        ?string $id = null,
        ?string $docType = null,
        ?string $docId = null,
        int $step = 0,
        ?string $approverId = null,
        string $status = 'draft',
        ?string $note = null,
        ?string $actedAt = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->docType = $docType;
        $this->docId = $docId;
        $this->step = $step;
        $this->approverId = $approverId;
        $this->status = $status;
        $this->note = $note;
        $this->actedAt = $actedAt;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getDocType(): ?string
    {
        return $this->docType;
    }

    public function setDocType(?string $docType): void
    {
        $this->docType = $docType;
    }

    public function getDocId(): ?string
    {
        return $this->docId;
    }

    public function setDocId(?string $docId): void
    {
        $this->docId = $docId;
    }

    public function getStep(): int
    {
        return $this->step;
    }

    public function setStep(int $step): void
    {
        $this->step = $step;
    }

    public function getApproverId(): ?string
    {
        return $this->approverId;
    }

    public function setApproverId(?string $approverId): void
    {
        $this->approverId = $approverId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getActedAt(): ?string
    {
        return $this->actedAt;
    }

    public function setActedAt(?string $actedAt): void
    {
        $this->actedAt = $actedAt;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'doc_type' => $this->docType,
            'doc_id' => $this->docId,
            'step' => $this->step,
            'approver_id' => $this->approverId,
            'status' => $this->status,
            'note' => $this->note,
            'acted_at' => $this->actedAt,
            'created_at' => $this->createdAt,
        ];
    }
}

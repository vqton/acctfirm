<?php

declare(strict_types=1);

namespace Accounting\Domain\Model;

class PurchaseRequisition
{
    private ?string $id;
    private ?string $prNumber;
    private string $status;
    private ?string $requesterId;
    private ?string $departmentId;
    private ?string $projectId;
    private ?float $totalEstimated;
    private ?string $deliveryDate;
    private ?string $note;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?string $id = null,
        ?string $prNumber = null,
        string $status = 'draft',
        ?string $requesterId = null,
        ?string $departmentId = null,
        ?string $projectId = null,
        ?float $totalEstimated = null,
        ?string $deliveryDate = null,
        ?string $note = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->prNumber = $prNumber;
        $this->status = $status;
        $this->requesterId = $requesterId;
        $this->departmentId = $departmentId;
        $this->projectId = $projectId;
        $this->totalEstimated = $totalEstimated;
        $this->deliveryDate = $deliveryDate;
        $this->note = $note;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getPrNumber(): ?string
    {
        return $this->prNumber;
    }

    public function setPrNumber(?string $prNumber): void
    {
        $this->prNumber = $prNumber;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getRequesterId(): ?string
    {
        return $this->requesterId;
    }

    public function setRequesterId(?string $requesterId): void
    {
        $this->requesterId = $requesterId;
    }

    public function getDepartmentId(): ?string
    {
        return $this->departmentId;
    }

    public function setDepartmentId(?string $departmentId): void
    {
        $this->departmentId = $departmentId;
    }

    public function getProjectId(): ?string
    {
        return $this->projectId;
    }

    public function setProjectId(?string $projectId): void
    {
        $this->projectId = $projectId;
    }

    public function getTotalEstimated(): ?float
    {
        return $this->totalEstimated;
    }

    public function setTotalEstimated(?float $totalEstimated): void
    {
        $this->totalEstimated = $totalEstimated;
    }

    public function getDeliveryDate(): ?string
    {
        return $this->deliveryDate;
    }

    public function setDeliveryDate(?string $deliveryDate): void
    {
        $this->deliveryDate = $deliveryDate;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?string $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pr_number' => $this->prNumber,
            'status' => $this->status,
            'requester_id' => $this->requesterId,
            'department_id' => $this->departmentId,
            'project_id' => $this->projectId,
            'total_estimated' => $this->totalEstimated,
            'delivery_date' => $this->deliveryDate,
            'note' => $this->note,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

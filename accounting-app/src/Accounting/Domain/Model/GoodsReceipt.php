<?php

declare(strict_types=1);

namespace Accounting\Domain\Model;

class GoodsReceipt
{
    private ?string $id;
    private ?string $grNumber;
    private ?string $poId;
    private string $status;
    private ?string $warehouseId;
    private ?string $receivedDate;
    private ?string $note;
    private ?string $createdAt;

    public function __construct(
        ?string $id = null,
        ?string $grNumber = null,
        ?string $poId = null,
        string $status = 'draft',
        ?string $warehouseId = null,
        ?string $receivedDate = null,
        ?string $note = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->grNumber = $grNumber;
        $this->poId = $poId;
        $this->status = $status;
        $this->warehouseId = $warehouseId;
        $this->receivedDate = $receivedDate;
        $this->note = $note;
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

    public function getGrNumber(): ?string
    {
        return $this->grNumber;
    }

    public function setGrNumber(?string $grNumber): void
    {
        $this->grNumber = $grNumber;
    }

    public function getPoId(): ?string
    {
        return $this->poId;
    }

    public function setPoId(?string $poId): void
    {
        $this->poId = $poId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getWarehouseId(): ?string
    {
        return $this->warehouseId;
    }

    public function setWarehouseId(?string $warehouseId): void
    {
        $this->warehouseId = $warehouseId;
    }

    public function getReceivedDate(): ?string
    {
        return $this->receivedDate;
    }

    public function setReceivedDate(?string $receivedDate): void
    {
        $this->receivedDate = $receivedDate;
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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gr_number' => $this->grNumber,
            'po_id' => $this->poId,
            'status' => $this->status,
            'warehouse_id' => $this->warehouseId,
            'received_date' => $this->receivedDate,
            'note' => $this->note,
            'created_at' => $this->createdAt,
        ];
    }
}

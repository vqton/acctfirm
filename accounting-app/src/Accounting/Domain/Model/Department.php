<?php
namespace Accounting\Domain\Model;

class Department
{
    private string $id;
    private string $code;
    private string $name;
    private ?string $parentId;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $code, string $name, ?string $parentId = null)
    {
        $this->id = $id; $this->code = $code; $this->name = $name;
        $this->parentId = $parentId; $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getParentId(): ?string { return $this->parentId; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $v): void { $this->code = $v; }
    public function setName(string $v): void { $this->name = $v; }
    public function setParentId(?string $v): void { $this->parentId = $v; }
    public function setStatus(bool $v): void { $this->status = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'parent_id' => $this->parentId, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
<?php
namespace Accounting\Domain\Model;

class Warehouse
{
    private string $id;
    private string $code;
    private string $name;
    private ?string $address;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $code, string $name, ?string $address = null)
    {
        $this->id = $id; $this->code = $code; $this->name = $name;
        $this->address = $address; $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getAddress(): ?string { return $this->address; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $v): void { $this->code = $v; }
    public function setName(string $v): void { $this->name = $v; }
    public function setAddress(?string $v): void { $this->address = $v; }
    public function setStatus(bool $v): void { $this->status = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'address' => $this->address, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
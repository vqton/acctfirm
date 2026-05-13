<?php
namespace Accounting\Domain\Model;

class Employee
{
    private string $id;
    private string $code;
    private string $name;
    private ?string $departmentId;
    private ?string $position;
    private ?string $phone;
    private ?string $email;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $code, string $name, ?string $departmentId = null,
        ?string $position = null, ?string $phone = null, ?string $email = null)
    {
        $this->id = $id; $this->code = $code; $this->name = $name;
        $this->departmentId = $departmentId; $this->position = $position;
        $this->phone = $phone; $this->email = $email;
        $this->status = true; $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getDepartmentId(): ?string { return $this->departmentId; }
    public function getPosition(): ?string { return $this->position; }
    public function getPhone(): ?string { return $this->phone; }
    public function getEmail(): ?string { return $this->email; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $v): void { $this->code = $v; }
    public function setName(string $v): void { $this->name = $v; }
    public function setDepartmentId(?string $v): void { $this->departmentId = $v; }
    public function setPosition(?string $v): void { $this->position = $v; }
    public function setPhone(?string $v): void { $this->phone = $v; }
    public function setEmail(?string $v): void { $this->email = $v; }
    public function setStatus(bool $v): void { $this->status = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'department_id' => $this->departmentId, 'position' => $this->position,
            'phone' => $this->phone, 'email' => $this->email,
            'status' => $this->status, 'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
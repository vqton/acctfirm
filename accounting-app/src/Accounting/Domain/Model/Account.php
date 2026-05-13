<?php
namespace Accounting\Domain\Model;

class Account
{
    private string $id;
    private string $code;
    private string $name;
    private string $type;
    private ?string $parentId;
    private string $normalBalance;
    private ?string $accountClass;
    private float $balance;
    private ?string $description;
    private bool $status;
    private bool $isControl;
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $code, string $name, string $type,
        ?string $parentId = null, string $normalBalance = 'D', ?string $accountClass = null,
        ?string $description = null)
    {
        $this->id = $id; $this->code = $code; $this->name = $name;
        $this->type = $type; $this->parentId = $parentId;
        $this->normalBalance = $normalBalance; $this->accountClass = $accountClass;
        $this->balance = 0.0; $this->description = $description;
        $this->status = true; $this->isControl = false;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getType(): string { return $this->type; }
    public function getParentId(): ?string { return $this->parentId; }
    public function getNormalBalance(): string { return $this->normalBalance; }
    public function getAccountClass(): ?string { return $this->accountClass; }
    public function getBalance(): float { return $this->balance; }
    public function getDescription(): ?string { return $this->description; }
    public function isStatus(): bool { return $this->status; }
    public function isControl(): bool { return $this->isControl; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $v): void { $this->code = $v; }
    public function setName(string $v): void { $this->name = $v; }
    public function setType(string $v): void { $this->type = $v; }
    public function setParentId(?string $v): void { $this->parentId = $v; }
    public function setNormalBalance(string $v): void { $this->normalBalance = $v; }
    public function setAccountClass(?string $v): void { $this->accountClass = $v; }
    public function setDescription(?string $v): void { $this->description = $v; }
    public function setStatus(bool $v): void { $this->status = $v; }
    public function setControl(bool $v): void { $this->isControl = $v; }

    public function credit(float $amount): void { $this->balance += $amount; }
    public function debit(float $amount): void { $this->balance -= $amount; }
    public function setBalance(float $v): void { $this->balance = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'type' => $this->type, 'parent_id' => $this->parentId,
            'normal_balance' => $this->normalBalance, 'account_class' => $this->accountClass,
            'balance' => $this->balance, 'description' => $this->description,
            'status' => $this->status, 'is_control' => $this->isControl,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
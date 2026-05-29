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
    private ?string $fsMappingCode;
    private ?string $fsMappingType;
    private bool $isLocked;
    private ?string $lockedBy;
    private ?string $lockedReason;
    private ?string $lockedAt;
    private bool $isSystem;
    private ?string $alternativeCode;
    private ?string $detailBy;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $code,
        string $name,
        string $type,
        ?string $parentId = null,
        string $normalBalance = 'D',
        ?string $accountClass = null,
        ?string $description = null,
        ?string $fsMappingCode = null,
        ?string $fsMappingType = null,
        ?string $alternativeCode = null,
        ?string $detailBy = null
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->type = $type;
        $this->parentId = $parentId;
        $this->normalBalance = $normalBalance;
        $this->accountClass = $accountClass;
        $this->balance = 0.0;
        $this->description = $description;
        $this->status = true;
        $this->isControl = false;
        $this->fsMappingCode = $fsMappingCode;
        $this->fsMappingType = $fsMappingType;
        $this->isLocked = false;
        $this->lockedBy = null;
        $this->lockedReason = null;
        $this->lockedAt = null;
        $this->isSystem = false;
        $this->alternativeCode = $alternativeCode;
        $this->detailBy = $detailBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    // ─── Getters ───

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
    public function getFsMappingCode(): ?string { return $this->fsMappingCode; }
    public function getFsMappingType(): ?string { return $this->fsMappingType; }
    public function isLocked(): bool { return $this->isLocked; }
    public function getLockedBy(): ?string { return $this->lockedBy; }
    public function getLockedReason(): ?string { return $this->lockedReason; }
    public function getLockedAt(): ?string { return $this->lockedAt; }
    public function isSystem(): bool { return $this->isSystem; }
    public function getAlternativeCode(): ?string { return $this->alternativeCode; }
    public function getDetailBy(): ?string { return $this->detailBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    // ─── Setters ───

    public function setCode(string $v): void { $this->code = $v; }
    public function setName(string $v): void { $this->name = $v; }
    public function setType(string $v): void { $this->type = $v; }
    public function setParentId(?string $v): void { $this->parentId = $v; }
    public function setNormalBalance(string $v): void { $this->normalBalance = $v; }
    public function setAccountClass(?string $v): void { $this->accountClass = $v; }
    public function setDescription(?string $v): void { $this->description = $v; }
    public function setStatus(bool $v): void { $this->status = $v; }
    public function setControl(bool $v): void { $this->isControl = $v; }
    public function setFsMappingCode(?string $v): void { $this->fsMappingCode = $v; }
    public function setFsMappingType(?string $v): void { $this->fsMappingType = $v; }
    public function setIsLocked(bool $v): void { $this->isLocked = $v; }
    public function setLockedBy(?string $v): void { $this->lockedBy = $v; }
    public function setLockedReason(?string $v): void { $this->lockedReason = $v; }
    public function setLockedAt(?string $v): void { $this->lockedAt = $v; }
    public function setIsSystem(bool $v): void { $this->isSystem = $v; }
    public function setAlternativeCode(?string $v): void { $this->alternativeCode = $v; }
    public function setDetailBy(?string $v): void { $this->detailBy = $v; }

    public function credit(float $amount): void { $this->balance += $amount; }
    public function debit(float $amount): void { $this->balance -= $amount; }
    public function setBalance(float $v): void { $this->balance = $v; }

    // Lock / unlock
    public function lock(string $by, string $reason): void
    {
        $this->isLocked = true;
        $this->lockedBy = $by;
        $this->lockedReason = $reason;
        $this->lockedAt = date('Y-m-d H:i:s');
    }

    public function unlock(): void
    {
        $this->isLocked = false;
        $this->lockedBy = null;
        $this->lockedReason = null;
        $this->lockedAt = null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'parent_id' => $this->parentId,
            'normal_balance' => $this->normalBalance,
            'account_class' => $this->accountClass,
            'balance' => $this->balance,
            'description' => $this->description,
            'status' => $this->status,
            'is_control' => $this->isControl,
            'fs_mapping_code' => $this->fsMappingCode,
            'fs_mapping_type' => $this->fsMappingType,
            'is_locked' => $this->isLocked,
            'locked_by' => $this->lockedBy,
            'locked_reason' => $this->lockedReason,
            'locked_at' => $this->lockedAt,
            'is_system' => $this->isSystem,
            'alternative_code' => $this->alternativeCode,
            'detail_by' => $this->detailBy,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

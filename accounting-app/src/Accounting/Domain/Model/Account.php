<?php
namespace Accounting\Domain\Model;

/**
 * Tài khoản kế toán — Hệ thống tài khoản theo Thông tư 99/2025/TT-BTC.
 *
 * Đây là cốt lõi của hệ thống: mọi bút toán đều ghi Nợ/Có vào một tài khoản.
 * Mỗi tài khoản thuộc một loại (asset/liability/equity/revenue/expense) và có
 * normal balance (Dư Nợ hoặc Dư Có) để xác định số dư tăng bên nào.
 *
 * RỦI RO:
 * - Tài khoản tổng hợp (control account: 111, 112, 131, 331...) không được
 *   post trực tiếp — chỉ post vào tài khoản con. Posting engine phải kiểm tra
 *   $isControl trước khi ghi nhận.
 * - $balance là số dư tạm thời trong bộ nhớ; số dư chính xác luôn được tính
 *   từ ledger_entries để tránh sai lệch do concurrent update.
 *
 * LIÊN KẾT:
 * - AccountRepository → định nghĩa interface truy xuất
 * - LedgerEntry → mỗi dòng bút toán tham chiếu đến một account
 * - PostingRuleService → kiểm tra cặp Dr-Cr có hợp lệ không
 * - FsService → đọc số dư tài khoản để lập BC01/02/03
 */
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

    // Ghi tăng (credit) số dư tài khoản: dùng cho dòng Có trong bút toán.
    // Lưu ý: Đây chỉ là cập nhật trong bộ nhớ. Số dư thực tế luôn tính từ ledger_entries.
    // Đối với tài khoản nguồn vốn (liability/equity), credit làm tăng số dư.
    public function credit(float $amount): void { $this->balance += $amount; }

    // Ghi giảm (debit) số dư tài khoản: dùng cho dòng Nợ trong bút toán.
    // Đối với tài khoản tài sản (asset), debit làm tăng số dư.
    // RỦI RO: Chỉ gọi qua JournalService — không gọi trực tiếp từ controller.
    public function debit(float $amount): void { $this->balance -= $amount; }
    public function setBalance(float $v): void { $this->balance = $v; }

    // Chuyển đổi model thành mảng để response API.
    // 'type': asset/liability/equity/revenue/expense — xác định vị trí trên BC01/02.
    // 'normal_balance': 'D' (dư Nợ) — tài sản, chi phí; 'C' (dư Có) — nguồn vốn, doanh thu.
    // 'is_control': true = tài khoản tổng hợp — không được post trực tiếp.
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
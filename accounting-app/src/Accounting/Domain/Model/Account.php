<?php
namespace Accounting\Domain\Model;

/**
 * Tài khoản kế toán — Mỗi tài khoản trong Hệ thống tài khoản Circular 99.
 *
 * Account là đối tượng trung tâm của hệ thống — mọi bút toán đều ghi Nợ/Có
 * vào một tài khoản. Số dư tài khoản phản ánh giá trị tài sản, nợ phải trả,
 * vốn chủ sở hữu, doanh thu và chi phí tại một thời điểm.
 *
 * NGHIỆP VỤ:
 * - $code: mã số tài khoản (VD: "1111", "131", "331") — duy nhất trong hệ thống
 * - $type: 'asset' (tài sản), 'liability' (nợ phải trả), 'equity' (VCSH),
 *          'revenue' (doanh thu), 'expense' (chi phí)
 * - $normalBalance: 'D' (dư Nợ) cho asset/expense, 'C' (dư Có) cho liability/equity/revenue
 * - $isControl: true nếu là tài khoản tổng hợp (có tài khoản con)
 *   — không được post trực tiếp vào tài khoản tổng hợp
 * - $balance: số dư hiện tại của tài khoản
 * - $isLocked: khóa tài khoản — không cho phép ghi sổ (thường khi đang kiểm tra)
 * - $isSystem: tài khoản hệ thống — không được xóa hoặc vô hiệu hóa
 *
 * RỦI RO:
 * - Post vào tài khoản tổng hợp (isControl = true) sẽ sai số dư chi tiết
 * - Xóa tài khoản đã phát sinh số dư → mất audit trail
 * - Sai mã số trên báo cáo tài chính → sai chỉ tiêu BC01/02/03
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

    /**
     * Khởi tạo tài khoản kế toán.
     *
     * @param string $id Định danh duy nhất của tài khoản
     * @param string $code Mã số tài khoản (VD: "1111", "131")
     * @param string $name Tên tài khoản (VD: "Tiền mặt Việt Nam")
     * @param string $type Loại tài khoản: 'asset', 'liability', 'equity', 'revenue', 'expense'
     * @param string|null $parentId ID tài khoản cha (nếu là tài khoản con)
     * @param string $normalBalance 'D' (dư Nợ) hoặc 'C' (dư Có)
     * @param string|null $accountClass Phân loại tài khoản (VD: 'current_asset', 'fixed_asset')
     * @param string|null $description Mô tả tài khoản
     * @param string|null $fsMappingCode Mã chỉ tiêu BCTC (VD: "111", "131")
     * @param string|null $fsMappingType Loại chỉ tiêu BCTC
     * @param string|null $alternativeCode Mã tài khoản thay thế (cho tích hợp)
     * @param string|null $detailBy Trường chi tiết: 'customer', 'supplier', 'employee', 'department'
     */
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

    /** @return string Định danh duy nhất của tài khoản */
    public function getId(): string { return $this->id; }

    /** @return string Mã số tài khoản (VD: "1111", "131") */
    public function getCode(): string { return $this->code; }

    /** @return string Tên tài khoản */
    public function getName(): string { return $this->name; }

    /** @return string Loại tài khoản: 'asset', 'liability', 'equity', 'revenue', 'expense' */
    public function getType(): string { return $this->type; }

    /** @return string|null ID tài khoản cha (nếu là tài khoản con) */
    public function getParentId(): ?string { return $this->parentId; }

    /** @return string 'D' (dư Nợ) hoặc 'C' (dư Có) */
    public function getNormalBalance(): string { return $this->normalBalance; }

    /** @return string|null Phân loại tài khoản */
    public function getAccountClass(): ?string { return $this->accountClass; }

    /** @return float Số dư hiện tại của tài khoản */
    public function getBalance(): float { return $this->balance; }

    /** @return string|null Mô tả tài khoản */
    public function getDescription(): ?string { return $this->description; }

    /** @return bool Trạng thái hoạt động (true = đang sử dụng) */
    public function isStatus(): bool { return $this->status; }

    /** @return bool true nếu là tài khoản tổng hợp (có tài khoản con) */
    public function isControl(): bool { return $this->isControl; }

    /** @return string|null Mã chỉ tiêu BCTC */
    public function getFsMappingCode(): ?string { return $this->fsMappingCode; }

    /** @return string|null Loại chỉ tiêu BCTC */
    public function getFsMappingType(): ?string { return $this->fsMappingType; }

    /** @return bool true nếu tài khoản đang bị khóa */
    public function isLocked(): bool { return $this->isLocked; }

    /** @return string|null Người khóa tài khoản */
    public function getLockedBy(): ?string { return $this->lockedBy; }

    /** @return string|null Lý do khóa tài khoản */
    public function getLockedReason(): ?string { return $this->lockedReason; }

    /** @return string|null Thời điểm khóa tài khoản */
    public function getLockedAt(): ?string { return $this->lockedAt; }

    /** @return bool true nếu là tài khoản hệ thống (không được xóa) */
    public function isSystem(): bool { return $this->isSystem; }

    /** @return string|null Mã tài khoản thay thế */
    public function getAlternativeCode(): ?string { return $this->alternativeCode; }

    /** @return string|null Trường chi tiết (customer, supplier, employee, department) */
    public function getDetailBy(): ?string { return $this->detailBy; }

    /** @return \DateTimeImmutable Thời điểm tạo tài khoản */
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    // ─── Setters ───

    /** @param string $v Mã số tài khoản mới */
    public function setCode(string $v): void { $this->code = $v; }

    /** @param string $v Tên tài khoản mới */
    public function setName(string $v): void { $this->name = $v; }

    /** @param string $v Loại tài khoản mới */
    public function setType(string $v): void { $this->type = $v; }

    /** @param string|null $v ID tài khoản cha mới */
    public function setParentId(?string $v): void { $this->parentId = $v; }

    /** @param string $v 'D' (dư Nợ) hoặc 'C' (dư Có) */
    public function setNormalBalance(string $v): void { $this->normalBalance = $v; }

    /** @param string|null $v Phân loại tài khoản mới */
    public function setAccountClass(?string $v): void { $this->accountClass = $v; }

    /** @param string|null $v Mô tả tài khoản mới */
    public function setDescription(?string $v): void { $this->description = $v; }

    /** @param bool $v Trạng thái hoạt động */
    public function setStatus(bool $v): void { $this->status = $v; }

    /** @param bool $v true nếu là tài khoản tổng hợp */
    public function setControl(bool $v): void { $this->isControl = $v; }

    /** @param string|null $v Mã chỉ tiêu BCTC mới */
    public function setFsMappingCode(?string $v): void { $this->fsMappingCode = $v; }

    /** @param string|null $v Loại chỉ tiêu BCTC mới */
    public function setFsMappingType(?string $v): void { $this->fsMappingType = $v; }

    /** @param bool $v true để khóa tài khoản */
    public function setIsLocked(bool $v): void { $this->isLocked = $v; }

    /** @param string|null $v Người khóa tài khoản */
    public function setLockedBy(?string $v): void { $this->lockedBy = $v; }

    /** @param string|null $v Lý do khóa */
    public function setLockedReason(?string $v): void { $this->lockedReason = $v; }

    /** @param string|null $v Thời điểm khóa */
    public function setLockedAt(?string $v): void { $this->lockedAt = $v; }

    /** @param bool $v true nếu là tài khoản hệ thống */
    public function setIsSystem(bool $v): void { $this->isSystem = $v; }

    /** @param string|null $v Mã tài khoản thay thế */
    public function setAlternativeCode(?string $v): void { $this->alternativeCode = $v; }

    /** @param string|null $v Trường chi tiết */
    public function setDetailBy(?string $v): void { $this->detailBy = $v; }

    /**
     * Ghi tăng số dư (bên Có).
     *
     * @param float $amount Số tiền ghi tăng
     */
    public function credit(float $amount): void { $this->balance += $amount; }

    /**
     * Ghi giảm số dư (bên Nợ).
     *
     * @param float $amount Số tiền ghi giảm
     */
    public function debit(float $amount): void { $this->balance -= $amount; }

    /**
     * Thiết lập số dư tài khoản.
     *
     * @param float $v Số dư mới
     */
    public function setBalance(float $v): void { $this->balance = $v; }

    // Lock / unlock

    /**
     * Khóa tài khoản — không cho phép ghi sổ.
     *
     * @param string $by Người thực hiện khóa
     * @param string $reason Lý do khóa
     */
    public function lock(string $by, string $reason): void
    {
        $this->isLocked = true;
        $this->lockedBy = $by;
        $this->lockedReason = $reason;
        $this->lockedAt = date('Y-m-d H:i:s');
    }

    /** Mở khóa tài khoản — cho phép ghi sổ trở lại. */
    public function unlock(): void
    {
        $this->isLocked = false;
        $this->lockedBy = null;
        $this->lockedReason = null;
        $this->lockedAt = null;
    }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu tài khoản dạng mảng
     */
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

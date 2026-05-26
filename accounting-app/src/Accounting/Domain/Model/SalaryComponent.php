<?php
namespace Accounting\Domain\Model;

/**
 * Khoan luong / phu cap / BH / thue — dinh nghia cac thanh phan trong bang luong.
 *
 * NGHIEP VU:
 * - type: earning (luong), allowance (phu cap), deduction (khau tru),
 *         insurance_ee (BH nld), insurance_er (BH dn), tax (thue TNCN), overtime (tang ca)
 * - calculation_type: fixed (so co dinh), percent_gross (% tren tong luong),
 *                     percent_basic (% tren luong co ban), formula (cong thuc)
 * - account_code_debit/credit: tai khoan ke toan cho but toan luong
 *   VD: luong co ban: debit=642, credit=334; BHXH: debit=334, credit=3383
 *
 * QUAN HE:
 * - Duoc tham chieu boi payroll_detail_lines de tinh chi tiet tung khoan
 */
class SalaryComponent
{
    private string $id;
    private string $code;
    private string $name;
    private string $type;
    private string $calculationType;
    private float $value;
    private ?string $accountCodeDebit;
    private ?string $accountCodeCredit;
    private int $priority;
    private bool $isMandatory;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $code,
        string $name,
        string $type = 'earning',
        string $calculationType = 'fixed',
        float $value = 0.0,
        ?string $accountCodeDebit = null,
        ?string $accountCodeCredit = null,
        int $priority = 0,
        bool $isMandatory = false
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->type = $type;
        $this->calculationType = $calculationType;
        $this->value = $value;
        $this->accountCodeDebit = $accountCodeDebit;
        $this->accountCodeCredit = $accountCodeCredit;
        $this->priority = $priority;
        $this->isMandatory = $isMandatory;
        $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getType(): string { return $this->type; }
    public function getCalculationType(): string { return $this->calculationType; }
    public function getValue(): float { return $this->value; }
    public function getAccountCodeDebit(): ?string { return $this->accountCodeDebit; }
    public function getAccountCodeCredit(): ?string { return $this->accountCodeCredit; }
    public function getPriority(): int { return $this->priority; }
    public function isMandatory(): bool { return $this->isMandatory; }
    public function isStatus(): bool { return $this->status; }

    public function setCode(string $v): void { $this->code = $v; }
    public function setName(string $v): void { $this->name = $v; }
    public function setType(string $v): void { $this->type = $v; }
    public function setCalculationType(string $v): void { $this->calculationType = $v; }
    public function setValue(float $v): void { $this->value = $v; }
    public function setAccountCodeDebit(?string $v): void { $this->accountCodeDebit = $v; }
    public function setAccountCodeCredit(?string $v): void { $this->accountCodeCredit = $v; }
    public function setPriority(int $v): void { $this->priority = $v; }
    public function setIsMandatory(bool $v): void { $this->isMandatory = $v; }
    public function setStatus(bool $v): void { $this->status = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'calculation_type' => $this->calculationType,
            'value' => $this->value,
            'account_code_debit' => $this->accountCodeDebit,
            'account_code_credit' => $this->accountCodeCredit,
            'priority' => $this->priority,
            'is_mandatory' => $this->isMandatory,
            'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

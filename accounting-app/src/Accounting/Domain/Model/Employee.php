<?php
namespace Accounting\Domain\Model;

/**
 * Nhan vien — Danh sach nguoi lao dong trong doanh nghiep.
 *
 * NGHIEP VU:
 * - $departmentId: phong ban — xac dinh noi tap hop chi phi luong
 * - $position: chuc vu — anh huong den he so luong, phu cap
 * - $insuranceSalary: muc luong tham gia BHXH (co the khac luong co ban)
 * - $bankAccount: so tai khoan nhan luong
 * - $taxCode: ma so thue TNCN
 * - $dependentCount: so nguoi phu thuoc — giam tru gia canh khi tinh thue TNCN
 * - $region: vung luong toi thieu (I/II/III/IV) — theo Nghi dinh 293/2025/ND-CP
 * - $contractType: loai hop dong lao dong
 *
 * LIEN KET:
 * - Payroll module → tinh luong, BHXH, thue TNCN
 * - CashService → chi luong, tam ung, thanh toan tam ung
 * - FixedAsset → nguoi duoc giao quan ly TSCD
 */
class Employee
{
    private string $id;
    private string $code;
    private string $name;
    private ?string $departmentId;
    private ?string $position;
    private ?string $phone;
    private ?string $email;
    private ?float $insuranceSalary;
    private ?string $bankAccount;
    private ?string $bankName;
    private ?string $taxCode;
    private int $dependentCount;
    private ?string $region;
    private string $contractType;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $code,
        string $name,
        ?string $departmentId = null,
        ?string $position = null,
        ?string $phone = null,
        ?string $email = null,
        ?float $insuranceSalary = null,
        ?string $bankAccount = null,
        ?string $bankName = null,
        ?string $taxCode = null,
        int $dependentCount = 0,
        ?string $region = null,
        string $contractType = 'indefinite'
    ) {
        $this->id = $id; $this->code = $code; $this->name = $name;
        $this->departmentId = $departmentId; $this->position = $position;
        $this->phone = $phone; $this->email = $email;
        $this->insuranceSalary = $insuranceSalary; $this->bankAccount = $bankAccount;
        $this->bankName = $bankName; $this->taxCode = $taxCode;
        $this->dependentCount = $dependentCount; $this->region = $region;
        $this->contractType = $contractType;
        $this->status = true; $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getDepartmentId(): ?string { return $this->departmentId; }
    public function getPosition(): ?string { return $this->position; }
    public function getPhone(): ?string { return $this->phone; }
    public function getEmail(): ?string { return $this->email; }
    public function getInsuranceSalary(): ?float { return $this->insuranceSalary; }
    public function getBankAccount(): ?string { return $this->bankAccount; }
    public function getBankName(): ?string { return $this->bankName; }
    public function getTaxCode(): ?string { return $this->taxCode; }
    public function getDependentCount(): int { return $this->dependentCount; }
    public function getRegion(): ?string { return $this->region; }
    public function getContractType(): string { return $this->contractType; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $v): void { $this->code = $v; }
    public function setName(string $v): void { $this->name = $v; }
    public function setDepartmentId(?string $v): void { $this->departmentId = $v; }
    public function setPosition(?string $v): void { $this->position = $v; }
    public function setPhone(?string $v): void { $this->phone = $v; }
    public function setEmail(?string $v): void { $this->email = $v; }
    public function setInsuranceSalary(?float $v): void { $this->insuranceSalary = $v; }
    public function setBankAccount(?string $v): void { $this->bankAccount = $v; }
    public function setBankName(?string $v): void { $this->bankName = $v; }
    public function setTaxCode(?string $v): void { $this->taxCode = $v; }
    public function setDependentCount(int $v): void { $this->dependentCount = $v; }
    public function setRegion(?string $v): void { $this->region = $v; }
    public function setContractType(string $v): void { $this->contractType = $v; }
    public function setStatus(bool $v): void { $this->status = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'department_id' => $this->departmentId, 'position' => $this->position,
            'phone' => $this->phone, 'email' => $this->email,
            'insurance_salary' => $this->insuranceSalary,
            'bank_account' => $this->bankAccount, 'bank_name' => $this->bankName,
            'tax_code' => $this->taxCode, 'dependent_count' => $this->dependentCount,
            'region' => $this->region, 'contract_type' => $this->contractType,
            'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

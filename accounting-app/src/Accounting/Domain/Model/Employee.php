<?php
namespace Accounting\Domain\Model;

/**
 * Nhân viên — Danh sách người lao động trong doanh nghiệp.
 *
 * NGHIỆP VỤ:
 * - $departmentId: phòng ban — xác định nơi tập hợp chi phí lương
 * - $position: chức vụ — ảnh hưởng đến hệ số lương, phụ cấp
 * - $insuranceSalary: mức lương tham gia BHXH (có thể khác lương cơ bản)
 * - $bankAccount: số tài khoản nhận lương
 * - $taxCode: mã số thuế TNCN
 * - $dependentCount: số người phụ thuộc — giảm trừ gia cảnh khi tính thuế TNCN
 * - $region: vùng lương tối thiểu (I/II/III/IV) — theo Nghị định 293/2025/NĐ-CP
 * - $contractType: loại hợp đồng lao động
 *
 * LIÊN KẾT:
 * - Payroll module → tính lương, BHXH, thuế TNCN
 * - CashService → chi lương, tạm ứng, thanh toán tạm ứng
 * - FixedAsset → người được giao quản lý TSCĐ
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

    /**
     * Khởi tạo nhân viên.
     *
     * @param string $id Định danh nhân viên
     * @param string $code Mã nhân viên
     * @param string $name Tên nhân viên
     * @param string|null $departmentId ID phòng ban
     * @param string|null $position Chức vụ
     * @param string|null $phone Số điện thoại
     * @param string|null $email Email
     * @param float|null $insuranceSalary Mức lương tham gia BHXH
     * @param string|null $bankAccount Số tài khoản nhận lương
     * @param string|null $bankName Tên ngân hàng
     * @param string|null $taxCode Mã số thuế TNCN
     * @param int $dependentCount Số người phụ thuộc
     * @param string|null $region Vùng lương (I/II/III/IV)
     * @param string $contractType Loại hợp đồng: 'indefinite', 'definite', 'seasonal', 'probation'
     */
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

    /** @return string Định danh nhân viên */
    public function getId(): string { return $this->id; }

    /** @return string Mã nhân viên */
    public function getCode(): string { return $this->code; }

    /** @return string Tên nhân viên */
    public function getName(): string { return $this->name; }

    /** @return string|null ID phòng ban */
    public function getDepartmentId(): ?string { return $this->departmentId; }

    /** @return string|null Chức vụ */
    public function getPosition(): ?string { return $this->position; }

    /** @return string|null Số điện thoại */
    public function getPhone(): ?string { return $this->phone; }

    /** @return string|null Email */
    public function getEmail(): ?string { return $this->email; }

    /** @return float|null Mức lương tham gia BHXH */
    public function getInsuranceSalary(): ?float { return $this->insuranceSalary; }

    /** @return string|null Số tài khoản nhận lương */
    public function getBankAccount(): ?string { return $this->bankAccount; }

    /** @return string|null Tên ngân hàng */
    public function getBankName(): ?string { return $this->bankName; }

    /** @return string|null Mã số thuế TNCN */
    public function getTaxCode(): ?string { return $this->taxCode; }

    /** @return int Số người phụ thuộc */
    public function getDependentCount(): int { return $this->dependentCount; }

    /** @return string|null Vùng lương */
    public function getRegion(): ?string { return $this->region; }

    /** @return string Loại hợp đồng lao động */
    public function getContractType(): string { return $this->contractType; }

    /** @return bool Trạng thái hoạt động */
    public function isStatus(): bool { return $this->status; }

    /** @return \DateTimeImmutable Thời điểm tạo */
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @param string $v Mã nhân viên mới */
    public function setCode(string $v): void { $this->code = $v; }

    /** @param string $v Tên nhân viên mới */
    public function setName(string $v): void { $this->name = $v; }

    /** @param string|null $v ID phòng ban mới */
    public function setDepartmentId(?string $v): void { $this->departmentId = $v; }

    /** @param string|null $v Chức vụ mới */
    public function setPosition(?string $v): void { $this->position = $v; }

    /** @param string|null $v Số điện thoại mới */
    public function setPhone(?string $v): void { $this->phone = $v; }

    /** @param string|null $v Email mới */
    public function setEmail(?string $v): void { $this->email = $v; }

    /** @param float|null $v Mức lương BHXH mới */
    public function setInsuranceSalary(?float $v): void { $this->insuranceSalary = $v; }

    /** @param string|null $v Số tài khoản mới */
    public function setBankAccount(?string $v): void { $this->bankAccount = $v; }

    /** @param string|null $v Tên ngân hàng mới */
    public function setBankName(?string $v): void { $this->bankName = $v; }

    /** @param string|null $v Mã số thuế mới */
    public function setTaxCode(?string $v): void { $this->taxCode = $v; }

    /** @param int $v Số người phụ thuộc mới */
    public function setDependentCount(int $v): void { $this->dependentCount = $v; }

    /** @param string|null $v Vùng lương mới */
    public function setRegion(?string $v): void { $this->region = $v; }

    /** @param string $v Loại hợp đồng mới */
    public function setContractType(string $v): void { $this->contractType = $v; }

    /** @param bool $v Trạng thái mới */
    public function setStatus(bool $v): void { $this->status = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu nhân viên dạng mảng
     */
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

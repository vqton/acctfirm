<?php
namespace Accounting\Domain\Model;

/**
 * Chi tiết bảng lương của một nhân viên trong một kỳ.
 *
 * NGHIỆP VỤ:
 * - Mỗi dòng là chi tiết lương của 1 nhân viên trong 1 bảng lương
 * - gross_salary: lương cơ bản
 * - total_allowances: tổng phụ cấp
 * - total_deductions: tổng khấu trừ (không bao gồm BH)
 * - insurance_ee: BH người lao động (BHXH+BHYT+BHTN)
 * - insurance_er: BH doanh nghiệp (BHXH+BHYT+BHTN)
 * - tax_amount: thuế TNCN
 * - net_pay = gross_salary + allowances - deductions - insurance_ee - tax
 * - total_cost = gross_salary + allowances + insurance_er (chi phí dn)
 * - working_days: số công làm việc thực tế
 *
 * QUAN HỆ:
 * - N->1 payroll_entries (bảng lương)
 * - N->1 employees (nhân viên)
 * - 1->N payroll_detail_lines (chi tiết từng khoản)
 */
class PayrollDetail
{
    private string $id;
    private string $payrollEntryId;
    private string $employeeId;
    private float $grossSalary;
    private float $totalAllowances;
    private float $totalDeductions;
    private float $insuranceEe;
    private float $insuranceEr;
    private float $taxAmount;
    private float $netPay;
    private float $overtimeAmount;
    private float $totalCost;
    private float $workingDays;
    private string $status;
    private ?string $notes;
    private \DateTimeImmutable $createdAt;

    /**
     * Khởi tạo chi tiết bảng lương.
     *
     * @param string $id Định danh
     * @param string $payrollEntryId ID bảng lương
     * @param string $employeeId ID nhân viên
     * @param float $grossSalary Lương cơ bản
     * @param float $totalAllowances Tổng phụ cấp
     * @param float $totalDeductions Tổng khấu trừ
     * @param float $insuranceEe BH người lao động
     * @param float $insuranceEr BH doanh nghiệp
     * @param float $taxAmount Thuế TNCN
     * @param float $netPay Lương thực nhận
     * @param float $overtimeAmount Tiền tăng ca
     * @param float $totalCost Tổng chi phí doanh nghiệp
     * @param float $workingDays Số công thực tế
     * @param string $status Trạng thái
     * @param string|null $notes Ghi chú
     */
    public function __construct(
        string $id,
        string $payrollEntryId,
        string $employeeId,
        float $grossSalary = 0,
        float $totalAllowances = 0,
        float $totalDeductions = 0,
        float $insuranceEe = 0,
        float $insuranceEr = 0,
        float $taxAmount = 0,
        float $netPay = 0,
        float $overtimeAmount = 0,
        float $totalCost = 0,
        float $workingDays = 0,
        string $status = 'active',
        ?string $notes = null
    ) {
        $this->id = $id;
        $this->payrollEntryId = $payrollEntryId;
        $this->employeeId = $employeeId;
        $this->grossSalary = $grossSalary;
        $this->totalAllowances = $totalAllowances;
        $this->totalDeductions = $totalDeductions;
        $this->insuranceEe = $insuranceEe;
        $this->insuranceEr = $insuranceEr;
        $this->taxAmount = $taxAmount;
        $this->netPay = $netPay;
        $this->overtimeAmount = $overtimeAmount;
        $this->totalCost = $totalCost;
        $this->workingDays = $workingDays;
        $this->status = $status;
        $this->notes = $notes;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @return string Định danh */
    public function getId(): string { return $this->id; }

    /** @return string ID bảng lương */
    public function getPayrollEntryId(): string { return $this->payrollEntryId; }

    /** @return string ID nhân viên */
    public function getEmployeeId(): string { return $this->employeeId; }

    /** @return float Lương cơ bản */
    public function getGrossSalary(): float { return $this->grossSalary; }

    /** @return float Tổng phụ cấp */
    public function getTotalAllowances(): float { return $this->totalAllowances; }

    /** @return float Tổng khấu trừ */
    public function getTotalDeductions(): float { return $this->totalDeductions; }

    /** @return float BH người lao động */
    public function getInsuranceEe(): float { return $this->insuranceEe; }

    /** @return float BH doanh nghiệp */
    public function getInsuranceEr(): float { return $this->insuranceEr; }

    /** @return float Thuế TNCN */
    public function getTaxAmount(): float { return $this->taxAmount; }

    /** @return float Lương thực nhận */
    public function getNetPay(): float { return $this->netPay; }

    /** @return float Tiền tăng ca */
    public function getOvertimeAmount(): float { return $this->overtimeAmount; }

    /** @return float Tổng chi phí doanh nghiệp */
    public function getTotalCost(): float { return $this->totalCost; }

    /** @return float Số công thực tế */
    public function getWorkingDays(): float { return $this->workingDays; }

    /** @return string Trạng thái */
    public function getStatus(): string { return $this->status; }

    /** @return string|null Ghi chú */
    public function getNotes(): ?string { return $this->notes; }

    /** @param float $v Lương cơ bản mới */
    public function setGrossSalary(float $v): void { $this->grossSalary = $v; }

    /** @param float $v Tổng phụ cấp mới */
    public function setTotalAllowances(float $v): void { $this->totalAllowances = $v; }

    /** @param float $v Tổng khấu trừ mới */
    public function setTotalDeductions(float $v): void { $this->totalDeductions = $v; }

    /** @param float $v BH người lao động mới */
    public function setInsuranceEe(float $v): void { $this->insuranceEe = $v; }

    /** @param float $v BH doanh nghiệp mới */
    public function setInsuranceEr(float $v): void { $this->insuranceEr = $v; }

    /** @param float $v Thuế TNCN mới */
    public function setTaxAmount(float $v): void { $this->taxAmount = $v; }

    /** @param float $v Lương thực nhận mới */
    public function setNetPay(float $v): void { $this->netPay = $v; }

    /** @param float $v Tiền tăng ca mới */
    public function setOvertimeAmount(float $v): void { $this->overtimeAmount = $v; }

    /** @param float $v Tổng chi phí mới */
    public function setTotalCost(float $v): void { $this->totalCost = $v; }

    /** @param float $v Số công mới */
    public function setWorkingDays(float $v): void { $this->workingDays = $v; }

    /** @param string $v Trạng thái mới */
    public function setStatus(string $v): void { $this->status = $v; }

    /** @param string|null $v Ghi chú mới */
    public function setNotes(?string $v): void { $this->notes = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu chi tiết lương dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'payroll_entry_id' => $this->payrollEntryId,
            'employee_id' => $this->employeeId,
            'gross_salary' => $this->grossSalary,
            'total_allowances' => $this->totalAllowances,
            'total_deductions' => $this->totalDeductions,
            'insurance_ee' => $this->insuranceEe,
            'insurance_er' => $this->insuranceEr,
            'tax_amount' => $this->taxAmount,
            'net_pay' => $this->netPay,
            'overtime_amount' => $this->overtimeAmount,
            'total_cost' => $this->totalCost,
            'working_days' => $this->workingDays,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

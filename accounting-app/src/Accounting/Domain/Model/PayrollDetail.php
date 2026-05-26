<?php
namespace Accounting\Domain\Model;

/**
 * Chi tiet bang luong cua mot nhan vien trong mot ky.
 *
 * NGHIEP VU:
 * - Moi dong la chi tiet luong cua 1 nhan vien trong 1 bang luong
 * - gross_salary: luong co ban
 * - total_allowances: tong phu cap
 * - total_deductions: tong khau tru (khong bao gom BH)
 * - insurance_ee: BH nguoi lao dong (BHXH+BHYT+BHTN)
 * - insurance_er: BH doanh nghiep (BHXH+BHYT+BHTN)
 * - tax_amount: thue TNCN
 * - net_pay = gross_salary + allowances - deductions - insurance_ee - tax
 * - total_cost = gross_salary + allowances + insurance_er (chi phi dn)
 * - working_days: so cong lam viec thuc te
 *
 * QUAN HE:
 * - N->1 payroll_entries (bang luong)
 * - N->1 employees (nhan vien)
 * - 1->N payroll_detail_lines (chi tiet tung khoan)
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

    public function getId(): string { return $this->id; }
    public function getPayrollEntryId(): string { return $this->payrollEntryId; }
    public function getEmployeeId(): string { return $this->employeeId; }
    public function getGrossSalary(): float { return $this->grossSalary; }
    public function getTotalAllowances(): float { return $this->totalAllowances; }
    public function getTotalDeductions(): float { return $this->totalDeductions; }
    public function getInsuranceEe(): float { return $this->insuranceEe; }
    public function getInsuranceEr(): float { return $this->insuranceEr; }
    public function getTaxAmount(): float { return $this->taxAmount; }
    public function getNetPay(): float { return $this->netPay; }
    public function getOvertimeAmount(): float { return $this->overtimeAmount; }
    public function getTotalCost(): float { return $this->totalCost; }
    public function getWorkingDays(): float { return $this->workingDays; }
    public function getStatus(): string { return $this->status; }
    public function getNotes(): ?string { return $this->notes; }

    public function setGrossSalary(float $v): void { $this->grossSalary = $v; }
    public function setTotalAllowances(float $v): void { $this->totalAllowances = $v; }
    public function setTotalDeductions(float $v): void { $this->totalDeductions = $v; }
    public function setInsuranceEe(float $v): void { $this->insuranceEe = $v; }
    public function setInsuranceEr(float $v): void { $this->insuranceEr = $v; }
    public function setTaxAmount(float $v): void { $this->taxAmount = $v; }
    public function setNetPay(float $v): void { $this->netPay = $v; }
    public function setOvertimeAmount(float $v): void { $this->overtimeAmount = $v; }
    public function setTotalCost(float $v): void { $this->totalCost = $v; }
    public function setWorkingDays(float $v): void { $this->workingDays = $v; }
    public function setStatus(string $v): void { $this->status = $v; }
    public function setNotes(?string $v): void { $this->notes = $v; }

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

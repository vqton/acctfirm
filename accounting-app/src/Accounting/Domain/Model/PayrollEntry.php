<?php
namespace Accounting\Domain\Model;

/**
 * Bang luong tong hop — tong hop toan bo bang luong cua mot ky.
 *
 * NGHIEP VU:
 * - status: draft (nhap), approved (da duyet), posted (da ghi so)
 * - total_employees: tong so nhan vien trong bang luong
 * - total_gross: tong luong gross (truoc BH va thue)
 * - total_net: tong luong net (sau BH va thue) = tien thuc nhan
 * - total_cost: tong chi phi luong doanh nghiep (gross + BH er)
 * - Khi posted, he thong tu dong ghi but toan vao JournalService
 *
 * QUAN HE: 1 bang luong -> N chi tiet (payroll_details)
 */
class PayrollEntry
{
    private string $id;
    private string $periodId;
    private string $status;
    private int $totalEmployees;
    private float $totalGross;
    private float $totalAllowances;
    private float $totalDeductions;
    private float $totalInsuranceEe;
    private float $totalInsuranceEr;
    private float $totalTax;
    private float $totalNet;
    private float $totalCost;
    private ?\DateTimeImmutable $postedAt;
    private ?string $createdBy;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $periodId,
        string $status = 'draft',
        int $totalEmployees = 0,
        float $totalGross = 0,
        float $totalAllowances = 0,
        float $totalDeductions = 0,
        float $totalInsuranceEe = 0,
        float $totalInsuranceEr = 0,
        float $totalTax = 0,
        float $totalNet = 0,
        float $totalCost = 0,
        ?\DateTimeImmutable $postedAt = null,
        ?string $createdBy = null
    ) {
        $this->id = $id;
        $this->periodId = $periodId;
        $this->status = $status;
        $this->totalEmployees = $totalEmployees;
        $this->totalGross = $totalGross;
        $this->totalAllowances = $totalAllowances;
        $this->totalDeductions = $totalDeductions;
        $this->totalInsuranceEe = $totalInsuranceEe;
        $this->totalInsuranceEr = $totalInsuranceEr;
        $this->totalTax = $totalTax;
        $this->totalNet = $totalNet;
        $this->totalCost = $totalCost;
        $this->postedAt = $postedAt;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getPeriodId(): string { return $this->periodId; }
    public function getStatus(): string { return $this->status; }
    public function getTotalEmployees(): int { return $this->totalEmployees; }
    public function getTotalGross(): float { return $this->totalGross; }
    public function getTotalAllowances(): float { return $this->totalAllowances; }
    public function getTotalDeductions(): float { return $this->totalDeductions; }
    public function getTotalInsuranceEe(): float { return $this->totalInsuranceEe; }
    public function getTotalInsuranceEr(): float { return $this->totalInsuranceEr; }
    public function getTotalTax(): float { return $this->totalTax; }
    public function getTotalNet(): float { return $this->totalNet; }
    public function getTotalCost(): float { return $this->totalCost; }
    public function getPostedAt(): ?\DateTimeImmutable { return $this->postedAt; }
    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setStatus(string $v): void { $this->status = $v; }
    public function setPostedAt(?\DateTimeImmutable $v): void { $this->postedAt = $v; }
    public function setTotalEmployees(int $v): void { $this->totalEmployees = $v; }
    public function setTotalGross(float $v): void { $this->totalGross = $v; }
    public function setTotalAllowances(float $v): void { $this->totalAllowances = $v; }
    public function setTotalDeductions(float $v): void { $this->totalDeductions = $v; }
    public function setTotalInsuranceEe(float $v): void { $this->totalInsuranceEe = $v; }
    public function setTotalInsuranceEr(float $v): void { $this->totalInsuranceEr = $v; }
    public function setTotalTax(float $v): void { $this->totalTax = $v; }
    public function setTotalNet(float $v): void { $this->totalNet = $v; }
    public function setTotalCost(float $v): void { $this->totalCost = $v; }
    public function setCreatedBy(?string $v): void { $this->createdBy = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'period_id' => $this->periodId,
            'status' => $this->status,
            'total_employees' => $this->totalEmployees,
            'total_gross' => $this->totalGross,
            'total_allowances' => $this->totalAllowances,
            'total_deductions' => $this->totalDeductions,
            'total_insurance_ee' => $this->totalInsuranceEe,
            'total_insurance_er' => $this->totalInsuranceEr,
            'total_tax' => $this->totalTax,
            'total_net' => $this->totalNet,
            'total_cost' => $this->totalCost,
            'posted_at' => $this->postedAt?->format('Y-m-d H:i:s'),
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

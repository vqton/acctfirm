<?php
namespace Accounting\Domain\Model;

/**
 * Bảng lương tổng hợp — Tổng hợp toàn bộ bảng lương của một kỳ.
 *
 * NGHIỆP VỤ:
 * - status: draft (nháp), approved (đã duyệt), posted (đã ghi sổ)
 * - total_employees: tổng số nhân viên trong bảng lương
 * - total_gross: tổng lương gross (trước BH và thuế)
 * - total_net: tổng lương net (sau BH và thuế) = tiền thực nhận
 * - total_cost: tổng chi phí lương doanh nghiệp (gross + BH er)
 * - Khi posted, hệ thống tự động ghi bút toán vào JournalService
 *
 * QUAN HỆ: 1 bảng lương -> N chi tiết (payroll_details)
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

    /**
     * Khởi tạo bảng lương.
     *
     * @param string $id Định danh
     * @param string $periodId ID kỳ lương
     * @param string $status Trạng thái: 'draft', 'approved', 'posted'
     * @param int $totalEmployees Tổng số nhân viên
     * @param float $totalGross Tổng lương gross
     * @param float $totalAllowances Tổng phụ cấp
     * @param float $totalDeductions Tổng khấu trừ
     * @param float $totalInsuranceEe Tổng BH người lao động
     * @param float $totalInsuranceEr Tổng BH doanh nghiệp
     * @param float $totalTax Tổng thuế TNCN
     * @param float $totalNet Tổng lương thực nhận
     * @param float $totalCost Tổng chi phí doanh nghiệp
     * @param \DateTimeImmutable|null $postedAt Thời điểm ghi sổ
     * @param string|null $createdBy Người tạo
     */
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

    /** @return string Định danh */
    public function getId(): string { return $this->id; }

    /** @return string ID kỳ lương */
    public function getPeriodId(): string { return $this->periodId; }

    /** @return string Trạng thái */
    public function getStatus(): string { return $this->status; }

    /** @return int Tổng số nhân viên */
    public function getTotalEmployees(): int { return $this->totalEmployees; }

    /** @return float Tổng lương gross */
    public function getTotalGross(): float { return $this->totalGross; }

    /** @return float Tổng phụ cấp */
    public function getTotalAllowances(): float { return $this->totalAllowances; }

    /** @return float Tổng khấu trừ */
    public function getTotalDeductions(): float { return $this->totalDeductions; }

    /** @return float Tổng BH người lao động */
    public function getTotalInsuranceEe(): float { return $this->totalInsuranceEe; }

    /** @return float Tổng BH doanh nghiệp */
    public function getTotalInsuranceEr(): float { return $this->totalInsuranceEr; }

    /** @return float Tổng thuế TNCN */
    public function getTotalTax(): float { return $this->totalTax; }

    /** @return float Tổng lương thực nhận */
    public function getTotalNet(): float { return $this->totalNet; }

    /** @return float Tổng chi phí doanh nghiệp */
    public function getTotalCost(): float { return $this->totalCost; }

    /** @return \DateTimeImmutable|null Thời điểm ghi sổ */
    public function getPostedAt(): ?\DateTimeImmutable { return $this->postedAt; }

    /** @return string|null Người tạo */
    public function getCreatedBy(): ?string { return $this->createdBy; }

    /** @return \DateTimeImmutable Thời điểm tạo */
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @param string $v Trạng thái mới */
    public function setStatus(string $v): void { $this->status = $v; }

    /** @param \DateTimeImmutable|null $v Thời điểm ghi sổ mới */
    public function setPostedAt(?\DateTimeImmutable $v): void { $this->postedAt = $v; }

    /** @param int $v Tổng số nhân viên mới */
    public function setTotalEmployees(int $v): void { $this->totalEmployees = $v; }

    /** @param float $v Tổng lương gross mới */
    public function setTotalGross(float $v): void { $this->totalGross = $v; }

    /** @param float $v Tổng phụ cấp mới */
    public function setTotalAllowances(float $v): void { $this->totalAllowances = $v; }

    /** @param float $v Tổng khấu trừ mới */
    public function setTotalDeductions(float $v): void { $this->totalDeductions = $v; }

    /** @param float $v Tổng BH người lao động mới */
    public function setTotalInsuranceEe(float $v): void { $this->totalInsuranceEe = $v; }

    /** @param float $v Tổng BH doanh nghiệp mới */
    public function setTotalInsuranceEr(float $v): void { $this->totalInsuranceEr = $v; }

    /** @param float $v Tổng thuế mới */
    public function setTotalTax(float $v): void { $this->totalTax = $v; }

    /** @param float $v Tổng lương thực nhận mới */
    public function setTotalNet(float $v): void { $this->totalNet = $v; }

    /** @param float $v Tổng chi phí mới */
    public function setTotalCost(float $v): void { $this->totalCost = $v; }

    /** @param string|null $v Người tạo mới */
    public function setCreatedBy(?string $v): void { $this->createdBy = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu bảng lương dạng mảng
     */
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

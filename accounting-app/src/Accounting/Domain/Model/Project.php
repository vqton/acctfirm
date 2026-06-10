<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

/**
 * Dự án — Đơn vị tập hợp chi phí và doanh thu cho các dự án cụ thể.
 *
 * Dự án cho phép theo dõi chi phí và doanh thu riêng cho từng dự án,
 * phục vụ cho quản trị và báo cáo kết quả theo dự án.
 *
 * NGHIỆP VỤ:
 * - $budget: ngân sách dự án
 * - $actualCost: chi phí thực tế đã phát sinh
 * - $billedAmount: doanh thu đã xuất hóa đơn
 * - $revenueRecognized: doanh thu đã ghi nhận (theo % hoàn thành)
 * - $estimatedCompletionPct: % hoàn thành ước tính
 * - $status: 'active', 'completed', 'cancelled', 'on_hold'
 */
class Project
{
    private string $id;
    private string $code;
    private string $name;
    private string $customerId;
    private ?string $managerId;
    private string $startDate;
    private ?string $endDate;
    private float $budget;
    private float $actualCost;
    private float $billedAmount;
    private float $revenueRecognized;
    private float $estimatedCompletionPct;
    private string $status;
    private ?string $notes;
    private \DateTimeImmutable $createdAt;

    /**
     * Khởi tạo dự án.
     *
     * @param string $id Định danh
     * @param string $code Mã dự án
     * @param string $name Tên dự án
     * @param string $customerId ID khách hàng
     * @param string $startDate Ngày bắt đầu
     * @param string|null $endDate Ngày kết thúc
     * @param float $budget Ngân sách
     * @param string|null $notes Ghi chú
     * @param string|null $managerId ID người quản lý
     */
    public function __construct(
        string $id, string $code, string $name, string $customerId, string $startDate,
        ?string $endDate = null, float $budget = 0, ?string $notes = null,
        ?string $managerId = null
    ) {
        $this->id = $id; $this->code = $code; $this->name = $name;
        $this->customerId = $customerId; $this->managerId = $managerId;
        $this->startDate = $startDate; $this->endDate = $endDate;
        $this->budget = $budget; $this->actualCost = 0; $this->billedAmount = 0;
        $this->revenueRecognized = 0; $this->estimatedCompletionPct = 0;
        $this->status = 'active'; $this->notes = $notes;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @return string Định danh dự án */
    public function getId(): string { return $this->id; }

    /** @return string Mã dự án */
    public function getCode(): string { return $this->code; }

    /** @return string Tên dự án */
    public function getName(): string { return $this->name; }

    /** @return string ID khách hàng */
    public function getCustomerId(): string { return $this->customerId; }

    /** @return string|null ID người quản lý */
    public function getManagerId(): ?string { return $this->managerId; }

    /** @return string Ngày bắt đầu */
    public function getStartDate(): string { return $this->startDate; }

    /** @return string|null Ngày kết thúc */
    public function getEndDate(): ?string { return $this->endDate; }

    /** @return float Ngân sách */
    public function getBudget(): float { return $this->budget; }

    /** @return float Chi phí thực tế */
    public function getActualCost(): float { return $this->actualCost; }

    /** @return float Doanh thu đã xuất hóa đơn */
    public function getBilledAmount(): float { return $this->billedAmount; }

    /** @return float Doanh thu đã ghi nhận */
    public function getRevenueRecognized(): float { return $this->revenueRecognized; }

    /** @return float % hoàn thành ước tính */
    public function getEstimatedCompletionPct(): float { return $this->estimatedCompletionPct; }

    /** @return string Trạng thái */
    public function getStatus(): string { return $this->status; }

    /** @return string|null Ghi chú */
    public function getNotes(): ?string { return $this->notes; }

    /** @return \DateTimeImmutable Thời điểm tạo */
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @param string $v Mã dự án mới */
    public function setCode(string $v): void { $this->code = $v; }

    /** @param string $v Tên dự án mới */
    public function setName(string $v): void { $this->name = $v; }

    /** @param string $v ID khách hàng mới */
    public function setCustomerId(string $v): void { $this->customerId = $v; }

    /** @param string|null $v ID người quản lý mới */
    public function setManagerId(?string $v): void { $this->managerId = $v; }

    /** @param string $v Ngày bắt đầu mới */
    public function setStartDate(string $v): void { $this->startDate = $v; }

    /** @param string|null $v Ngày kết thúc mới */
    public function setEndDate(?string $v): void { $this->endDate = $v; }

    /** @param float $v Ngân sách mới */
    public function setBudget(float $v): void { $this->budget = $v; }

    /** @param float $v Chi phí thực tế mới */
    public function setActualCost(float $v): void { $this->actualCost = $v; }

    /** @param float $v Doanh thu đã xuất hóa đơn mới */
    public function setBilledAmount(float $v): void { $this->billedAmount = $v; }

    /** @param float $v Doanh thu đã ghi nhận mới */
    public function setRevenueRecognized(float $v): void { $this->revenueRecognized = $v; }

    /** @param float $v % hoàn thành mới */
    public function setEstimatedCompletionPct(float $v): void { $this->estimatedCompletionPct = $v; }

    /** @param string $v Trạng thái mới */
    public function setStatus(string $v): void { $this->status = $v; }

    /** @param string|null $v Ghi chú mới */
    public function setNotes(?string $v): void { $this->notes = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu dự án dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'customer_id' => $this->customerId, 'manager_id' => $this->managerId,
            'start_date' => $this->startDate, 'end_date' => $this->endDate,
            'budget' => $this->budget, 'actual_cost' => $this->actualCost,
            'billed_amount' => $this->billedAmount,
            'revenue_recognized' => $this->revenueRecognized,
            'estimated_completion_pct' => $this->estimatedCompletionPct,
            'status' => $this->status, 'notes' => $this->notes,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

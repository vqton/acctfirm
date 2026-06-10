<?php
namespace Accounting\Domain\Model;

/**
 * Công cụ dụng cụ (CCDC) — Tài sản ngắn hạn phân bổ dần vào chi phí.
 *
 * CCDC khác TSCĐ ở thời gian sử dụng (< 1 năm) hoặc giá trị thấp.
 * Chi phí CCDC được phân bổ dần vào chi phí sản xuất kinh doanh.
 *
 * NGHIỆP VỤ:
 * - $allocationType: 'direct' (phân bổ 1 lần), 'monthly' (phân bổ hàng tháng)
 * - $allocationMonths: số tháng phân bổ (nếu monthly)
 * - $expenseAccount: TK chi phí (mặc định 642 — chi phí QLDN)
 * - $totalCost: tổng giá trị CCDC
 * - $allocated: giá trị đã phân bổ
 * - $remainingMonths: số tháng còn lại (theo dõi tự động)
 */
class Ccdc
{
    private string $id;
    private string $code;
    private string $name;
    private string $unit;
    private float $quantity;
    private string $allocationType;
    private int $allocationMonths;
    private string $expenseAccount;
    private ?string $allocationStartDate;
    private float $totalCost;
    private float $allocated;
    private int $remainingMonths;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    /**
     * Khởi tạo CCDC.
     *
     * @param string $id Định danh
     * @param string $code Mã CCDC
     * @param string $name Tên CCDC
     * @param string $unit Đơn vị tính
     * @param float $quantity Số lượng
     * @param string $allocationType Phương pháp phân bổ: 'direct', 'monthly'
     * @param int $allocationMonths Số tháng phân bổ
     * @param string $expenseAccount TK chi phí
     * @param string|null $allocationStartDate Ngày bắt đầu phân bổ
     * @param float $totalCost Tổng giá trị
     * @param float $allocated Giá trị đã phân bổ
     * @param int $remainingMonths Số tháng còn lại
     */
    public function __construct(
        string $id, string $code, string $name, string $unit = 'cai',
        float $quantity = 0, string $allocationType = 'direct',
        int $allocationMonths = 0, string $expenseAccount = '642',
        ?string $allocationStartDate = null,
        float $totalCost = 0, float $allocated = 0, int $remainingMonths = 0
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->unit = $unit;
        $this->quantity = $quantity;
        $this->allocationType = $allocationType;
        $this->allocationMonths = $allocationMonths;
        $this->expenseAccount = $expenseAccount;
        $this->allocationStartDate = $allocationStartDate;
        $this->totalCost = $totalCost;
        $this->allocated = $allocated;
        $this->remainingMonths = $remainingMonths;
        $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @return string Định danh CCDC */
    public function getId(): string { return $this->id; }

    /** @return string Mã CCDC */
    public function getCode(): string { return $this->code; }

    /** @return string Tên CCDC */
    public function getName(): string { return $this->name; }

    /** @return string Đơn vị tính */
    public function getUnit(): string { return $this->unit; }

    /** @return float Số lượng */
    public function getQuantity(): float { return $this->quantity; }

    /** @return string Phương pháp phân bổ */
    public function getAllocationType(): string { return $this->allocationType; }

    /** @return int Số tháng phân bổ */
    public function getAllocationMonths(): int { return $this->allocationMonths; }

    /** @return string TK chi phí */
    public function getExpenseAccount(): string { return $this->expenseAccount; }

    /** @return string|null Ngày bắt đầu phân bổ */
    public function getAllocationStartDate(): ?string { return $this->allocationStartDate; }

    /** @return float Tổng giá trị */
    public function getTotalCost(): float { return $this->totalCost; }

    /** @return float Giá trị đã phân bổ */
    public function getAllocated(): float { return $this->allocated; }

    /** @return int Số tháng còn lại */
    public function getRemainingMonths(): int { return $this->remainingMonths; }

    /** @return bool Trạng thái hoạt động */
    public function isStatus(): bool { return $this->status; }

    /** @return \DateTimeImmutable Thời điểm tạo */
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @param string $code Mã CCDC mới */
    public function setCode(string $code): void { $this->code = $code; }

    /** @param string $name Tên CCDC mới */
    public function setName(string $name): void { $this->name = $name; }

    /** @param string $unit Đơn vị tính mới */
    public function setUnit(string $unit): void { $this->unit = $unit; }

    /** @param float $qty Số lượng mới */
    public function setQuantity(float $qty): void { $this->quantity = $qty; }

    /** @param string $type Phương pháp phân bổ mới */
    public function setAllocationType(string $type): void { $this->allocationType = $type; }

    /** @param int $months Số tháng phân bổ mới */
    public function setAllocationMonths(int $months): void { $this->allocationMonths = $months; }

    /** @param string $acct TK chi phí mới */
    public function setExpenseAccount(string $acct): void { $this->expenseAccount = $acct; }

    /** @param string|null $date Ngày bắt đầu phân bổ mới */
    public function setAllocationStartDate(?string $date): void { $this->allocationStartDate = $date; }

    /** @param float $cost Tổng giá trị mới */
    public function setTotalCost(float $cost): void { $this->totalCost = $cost; }

    /** @param float $allocated Giá trị đã phân bổ mới */
    public function setAllocated(float $allocated): void { $this->allocated = $allocated; }

    /** @param int $months Số tháng còn lại mới */
    public function setRemainingMonths(int $months): void { $this->remainingMonths = $months; }

    /** @param bool $status Trạng thái mới */
    public function setStatus(bool $status): void { $this->status = $status; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu CCDC dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'unit' => $this->unit, 'quantity' => $this->quantity,
            'allocation_type' => $this->allocationType,
            'allocation_months' => $this->allocationMonths,
            'expense_account' => $this->expenseAccount,
            'allocation_start_date' => $this->allocationStartDate,
            'total_cost' => $this->totalCost, 'allocated' => $this->allocated,
            'remaining_months' => $this->remainingMonths,
            'remaining_value' => $this->totalCost - $this->allocated,
            'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

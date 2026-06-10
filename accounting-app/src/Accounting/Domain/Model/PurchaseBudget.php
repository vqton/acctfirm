<?php
declare(strict_types=1);
namespace Accounting\Domain\Model;

/**
 * Ngân sách mua hàng — Dự toán chi phí mua hàng theo phòng ban và kỳ.
 *
 * Theo dõi ngân sách đã phê duyệt, giá trị đã cam kết (PO) và
 * giá trị thực tế đã phát sinh.
 *
 * NGHIỆP VỤ:
 * - $budgetAmount: ngân sách được phê duyệt
 * - $committedAmount: giá trị đã cam kết (PO đã ký)
 * - $actualAmount: giá trị thực tế đã phát sinh
 */
class PurchaseBudget
{
    private ?string $id;
    private ?string $departmentId;
    private ?string $period;
    private ?float $budgetAmount;
    private ?float $committedAmount;
    private ?float $actualAmount;

    /**
     * Khởi tạo ngân sách mua hàng.
     *
     * @param string|null $id Định danh
     * @param string|null $departmentId ID phòng ban
     * @param string|null $period Kỳ ngân sách
     * @param float|null $budgetAmount Ngân sách phê duyệt
     * @param float|null $committedAmount Giá trị đã cam kết
     * @param float|null $actualAmount Giá trị thực tế
     */
    public function __construct(
        ?string $id = null,
        ?string $departmentId = null,
        ?string $period = null,
        ?float $budgetAmount = null,
        ?float $committedAmount = null,
        ?float $actualAmount = null
    ) {
        $this->id = $id;
        $this->departmentId = $departmentId;
        $this->period = $period;
        $this->budgetAmount = $budgetAmount;
        $this->committedAmount = $committedAmount;
        $this->actualAmount = $actualAmount;
    }

    /** @return string|null Định danh */
    public function getId(): ?string
    {
        return $this->id;
    }

    /** @param string|null $id Định danh mới */
    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    /** @return string|null ID phòng ban */
    public function getDepartmentId(): ?string
    {
        return $this->departmentId;
    }

    /** @param string|null $departmentId ID phòng ban mới */
    public function setDepartmentId(?string $departmentId): void
    {
        $this->departmentId = $departmentId;
    }

    /** @return string|null Kỳ ngân sách */
    public function getPeriod(): ?string
    {
        return $this->period;
    }

    /** @param string|null $period Kỳ ngân sách mới */
    public function setPeriod(?string $period): void
    {
        $this->period = $period;
    }

    /** @return float|null Ngân sách phê duyệt */
    public function getBudgetAmount(): ?float
    {
        return $this->budgetAmount;
    }

    /** @param float|null $budgetAmount Ngân sách phê duyệt mới */
    public function setBudgetAmount(?float $budgetAmount): void
    {
        $this->budgetAmount = $budgetAmount;
    }

    /** @return float|null Giá trị đã cam kết */
    public function getCommittedAmount(): ?float
    {
        return $this->committedAmount;
    }

    /** @param float|null $committedAmount Giá trị đã cam kết mới */
    public function setCommittedAmount(?float $committedAmount): void
    {
        $this->committedAmount = $committedAmount;
    }

    /** @return float|null Giá trị thực tế */
    public function getActualAmount(): ?float
    {
        return $this->actualAmount;
    }

    /** @param float|null $actualAmount Giá trị thực tế mới */
    public function setActualAmount(?float $actualAmount): void
    {
        $this->actualAmount = $actualAmount;
    }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu ngân sách dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'department_id' => $this->departmentId,
            'period' => $this->period,
            'budget_amount' => $this->budgetAmount,
            'committed_amount' => $this->committedAmount,
            'actual_amount' => $this->actualAmount,
        ];
    }
}

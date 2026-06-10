<?php
namespace Accounting\Domain\Model;

/**
 * Tài sản cố định (TSCĐ) — Quản lý tài sản dài hạn của doanh nghiệp.
 *
 * TSCĐ được ghi nhận theo nguyên giá (originalCost) và phân bổ dần qua
 * khấu hao. Giá trị còn lại (netBookValue = originalCost - accumulatedDepreciation)
 * được trình bày trên BC01 chỉ tiêu "Tài sản cố định" (TK 211/213/214).
 *
 * NGHIỆP VỤ:
 * - $originalCost: nguyên giá — căn cứ để tính khấu hao và giá trị còn lại
 * - $depreciationMethod: 'straight_line' (đều), 'declining_balance' (giảm dần),
 *   'unit_of_production' (theo sản lượng)
 * - $usefulLife: thời gian sử dụng hữu ích (tháng), theo khung của Circular 45/2013/TT-BTC
 * - $salvageValue: giá trị thu hồi ước tính khi thanh lý
 * - $monthlyDepreciation: mức khấu hao hàng tháng = (originalCost - salvageValue) / usefulLife
 * - $faCategory: 'tangible' (TSCĐ hữu hình - TK 211), 'intangible' (vô hình - TK 213),
 *   'finance_lease' (thuê tài chính - TK 212)
 *
 * LIÊN KẾT:
 * - DepartmentId → phòng ban sử dụng TSCĐ (để phân bổ chi phí khấu hao vào TK 627/641/642)
 * - FixedAssetService → tính khấu hao và sinh bút toán kết chuyển
 * - DepreciationPolicy → chính sách khấu hao mặc định
 *
 * RỦI RO:
 * - Không được thay đổi depreciationMethod sau khi đã tính khấu hao
 * - Thanh lý TSCĐ phải ghi nhận chênh lệch thanh lý (lãi/lỗ)
 * - Đánh giá lại TSCĐ phải có quyết định của Hội đồng quản trị
 */
class FixedAsset
{
    private string $id;
    private string $code;
    private string $name;
    private string $purchaseDate;
    private float $originalCost;
    private float $purchaseCost;
    private string $depreciationMethod;
    private int $usefulLife;
    private float $salvageValue;
    private ?float $totalEstimatedUnits;
    private float $monthlyDepreciation;
    private float $accumulatedDepreciation;
    private float $netBookValue;
    private string $faCategory;
    private ?string $faType;
    private ?string $departmentId;
    private ?string $employeeId;
    private ?string $location;
    private string $status;
    private ?string $lastDepreciationDate;
    private ?string $notes;
    private \DateTimeImmutable $createdAt;

    /**
     * Khởi tạo TSCĐ.
     *
     * @param string $id Định danh TSCĐ
     * @param string $code Mã TSCĐ
     * @param string $name Tên TSCĐ
     * @param string $purchaseDate Ngày mua
     * @param float $originalCost Nguyên giá
     * @param string $depreciationMethod Phương pháp khấu hao
     * @param int $usefulLife Thời gian sử dụng (tháng)
     * @param float $salvageValue Giá trị thu hồi
     * @param float $monthlyDepreciation Mức khấu hao tháng
     * @param float $accumulatedDepreciation Khấu hao lũy kế
     * @param float $netBookValue Giá trị còn lại
     * @param string $faCategory Loại TSCĐ: 'tangible', 'intangible', 'finance_lease'
     * @param string|null $faType Phân loại chi tiết
     * @param float|null $totalEstimatedUnits Tổng sản lượng ước tính
     * @param float $purchaseCost Chi phí mua
     * @param string|null $departmentId ID phòng ban quản lý
     * @param string|null $employeeId ID nhân viên quản lý
     * @param string|null $location Địa điểm
     * @param string $status Trạng thái: 'in_use', 'idle', 'liquidated'
     * @param string|null $lastDepreciationDate Lần khấu hao cuối
     * @param string|null $notes Ghi chú
     */
    public function __construct(
        string $id, string $code, string $name, string $purchaseDate,
        float $originalCost, string $depreciationMethod = 'straight_line', int $usefulLife = 0,
        float $salvageValue = 0, float $monthlyDepreciation = 0, float $accumulatedDepreciation = 0,
        float $netBookValue = 0, string $faCategory = 'tangible', ?string $faType = null,
        ?float $totalEstimatedUnits = null, float $purchaseCost = 0,
        ?string $departmentId = null, ?string $employeeId = null,
        ?string $location = null, string $status = 'in_use', ?string $lastDepreciationDate = null,
        ?string $notes = null
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->purchaseDate = $purchaseDate;
        $this->originalCost = $originalCost;
        $this->purchaseCost = $purchaseCost;
        $this->depreciationMethod = $depreciationMethod;
        $this->usefulLife = $usefulLife;
        $this->salvageValue = $salvageValue;
        $this->totalEstimatedUnits = $totalEstimatedUnits;
        $this->monthlyDepreciation = $monthlyDepreciation;
        $this->accumulatedDepreciation = $accumulatedDepreciation;
        $this->netBookValue = $netBookValue;
        $this->faCategory = $faCategory;
        $this->faType = $faType;
        $this->departmentId = $departmentId;
        $this->employeeId = $employeeId;
        $this->location = $location;
        $this->status = $status;
        $this->lastDepreciationDate = $lastDepreciationDate;
        $this->notes = $notes;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @return string Định danh TSCĐ */
    public function getId(): string { return $this->id; }

    /** @return string Mã TSCĐ */
    public function getCode(): string { return $this->code; }

    /** @return string Tên TSCĐ */
    public function getName(): string { return $this->name; }

    /** @return string Ngày mua */
    public function getPurchaseDate(): string { return $this->purchaseDate; }

    /** @return float Nguyên giá */
    public function getOriginalCost(): float { return $this->originalCost; }

    /** @return float Chi phí mua */
    public function getPurchaseCost(): float { return $this->purchaseCost; }

    /** @return string Phương pháp khấu hao */
    public function getDepreciationMethod(): string { return $this->depreciationMethod; }

    /** @return int Thời gian sử dụng (tháng) */
    public function getUsefulLife(): int { return $this->usefulLife; }

    /** @return float Giá trị thu hồi */
    public function getSalvageValue(): float { return $this->salvageValue; }

    /** @return float|null Tổng sản lượng ước tính */
    public function getTotalEstimatedUnits(): ?float { return $this->totalEstimatedUnits; }

    /** @return float Mức khấu hao tháng */
    public function getMonthlyDepreciation(): float { return $this->monthlyDepreciation; }

    /** @return float Khấu hao lũy kế (TK 214) */
    public function getAccumulatedDepreciation(): float { return $this->accumulatedDepreciation; }

    /** @return float Giá trị còn lại */
    public function getNetBookValue(): float { return $this->netBookValue; }

    /** @return string Loại TSCĐ */
    public function getFaCategory(): string { return $this->faCategory; }

    /** @return string|null Phân loại chi tiết */
    public function getFaType(): ?string { return $this->faType; }

    /** @return string|null ID phòng ban quản lý */
    public function getDepartmentId(): ?string { return $this->departmentId; }

    /** @return string|null ID nhân viên quản lý */
    public function getEmployeeId(): ?string { return $this->employeeId; }

    /** @return string|null Địa điểm */
    public function getLocation(): ?string { return $this->location; }

    /** @return string Trạng thái: 'in_use', 'idle', 'liquidated' */
    public function getStatus(): string { return $this->status; }

    /** @return string|null Lần khấu hao cuối */
    public function getLastDepreciationDate(): ?string { return $this->lastDepreciationDate; }

    /** @return string|null Ghi chú */
    public function getNotes(): ?string { return $this->notes; }

    /** @return \DateTimeImmutable Thời điểm tạo */
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @param string $code Mã TSCĐ mới */
    public function setCode(string $code): void { $this->code = $code; }

    /** @param string $name Tên TSCĐ mới */
    public function setName(string $name): void { $this->name = $name; }

    /** @param string $date Ngày mua mới */
    public function setPurchaseDate(string $date): void { $this->purchaseDate = $date; }

    /** @param float $cost Nguyên giá mới */
    public function setOriginalCost(float $cost): void { $this->originalCost = $cost; }

    /** @param float $cost Chi phí mua mới */
    public function setPurchaseCost(float $cost): void { $this->purchaseCost = $cost; }

    /** @param string $method Phương pháp khấu hao mới */
    public function setDepreciationMethod(string $method): void { $this->depreciationMethod = $method; }

    /** @param int $life Thời gian sử dụng mới */
    public function setUsefulLife(int $life): void { $this->usefulLife = $life; }

    /** @param float $value Giá trị thu hồi mới */
    public function setSalvageValue(float $value): void { $this->salvageValue = $value; }

    /** @param float|null $units Tổng sản lượng ước tính mới */
    public function setTotalEstimatedUnits(?float $units): void { $this->totalEstimatedUnits = $units; }

    /** @param float $dep Mức khấu hao tháng mới */
    public function setMonthlyDepreciation(float $dep): void { $this->monthlyDepreciation = $dep; }

    /** @param float $dep Khấu hao lũy kế mới */
    public function setAccumulatedDepreciation(float $dep): void { $this->accumulatedDepreciation = $dep; }

    /** @param float $value Giá trị còn lại mới */
    public function setNetBookValue(float $value): void { $this->netBookValue = $value; }

    /** @param string $category Loại TSCĐ mới */
    public function setFaCategory(string $category): void { $this->faCategory = $category; }

    /** @param string|null $type Phân loại chi tiết mới */
    public function setFaType(?string $type): void { $this->faType = $type; }

    /** @param string|null $id ID phòng ban mới */
    public function setDepartmentId(?string $id): void { $this->departmentId = $id; }

    /** @param string|null $id ID nhân viên mới */
    public function setEmployeeId(?string $id): void { $this->employeeId = $id; }

    /** @param string|null $location Địa điểm mới */
    public function setLocation(?string $location): void { $this->location = $location; }

    /** @param string $status Trạng thái mới */
    public function setStatus(string $status): void { $this->status = $status; }

    /** @param string|null $date Lần khấu hao cuối mới */
    public function setLastDepreciationDate(?string $date): void { $this->lastDepreciationDate = $date; }

    /** @param string|null $notes Ghi chú mới */
    public function setNotes(?string $notes): void { $this->notes = $notes; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu TSCĐ dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'purchase_date' => $this->purchaseDate, 'original_cost' => $this->originalCost,
            'purchase_cost' => $this->purchaseCost,
            'depreciation_method' => $this->depreciationMethod, 'useful_life' => $this->usefulLife,
            'salvage_value' => $this->salvageValue, 'total_estimated_units' => $this->totalEstimatedUnits,
            'monthly_depreciation' => $this->monthlyDepreciation,
            'accumulated_depreciation' => $this->accumulatedDepreciation,
            'net_book_value' => $this->netBookValue,
            'fa_category' => $this->faCategory, 'fa_type' => $this->faType,
            'department_id' => $this->departmentId, 'employee_id' => $this->employeeId,
            'location' => $this->location, 'status' => $this->status,
            'last_depreciation_date' => $this->lastDepreciationDate,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

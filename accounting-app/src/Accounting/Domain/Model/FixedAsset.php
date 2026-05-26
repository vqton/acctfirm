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

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getPurchaseDate(): string { return $this->purchaseDate; }
    public function getOriginalCost(): float { return $this->originalCost; }
    public function getPurchaseCost(): float { return $this->purchaseCost; }
    public function getDepreciationMethod(): string { return $this->depreciationMethod; }
    public function getUsefulLife(): int { return $this->usefulLife; }
    public function getSalvageValue(): float { return $this->salvageValue; }
    public function getTotalEstimatedUnits(): ?float { return $this->totalEstimatedUnits; }
    public function getMonthlyDepreciation(): float { return $this->monthlyDepreciation; }
    public function getAccumulatedDepreciation(): float { return $this->accumulatedDepreciation; }
    public function getNetBookValue(): float { return $this->netBookValue; }
    public function getFaCategory(): string { return $this->faCategory; }
    public function getFaType(): ?string { return $this->faType; }
    public function getDepartmentId(): ?string { return $this->departmentId; }
    public function getEmployeeId(): ?string { return $this->employeeId; }
    public function getLocation(): ?string { return $this->location; }
    public function getStatus(): string { return $this->status; }
    public function getLastDepreciationDate(): ?string { return $this->lastDepreciationDate; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setName(string $name): void { $this->name = $name; }
    public function setPurchaseDate(string $date): void { $this->purchaseDate = $date; }
    public function setOriginalCost(float $cost): void { $this->originalCost = $cost; }
    public function setPurchaseCost(float $cost): void { $this->purchaseCost = $cost; }
    public function setDepreciationMethod(string $method): void { $this->depreciationMethod = $method; }
    public function setUsefulLife(int $life): void { $this->usefulLife = $life; }
    public function setSalvageValue(float $value): void { $this->salvageValue = $value; }
    public function setTotalEstimatedUnits(?float $units): void { $this->totalEstimatedUnits = $units; }
    public function setMonthlyDepreciation(float $dep): void { $this->monthlyDepreciation = $dep; }
    public function setAccumulatedDepreciation(float $dep): void { $this->accumulatedDepreciation = $dep; }
    public function setNetBookValue(float $value): void { $this->netBookValue = $value; }
    public function setFaCategory(string $category): void { $this->faCategory = $category; }
    public function setFaType(?string $type): void { $this->faType = $type; }
    public function setDepartmentId(?string $id): void { $this->departmentId = $id; }
    public function setEmployeeId(?string $id): void { $this->employeeId = $id; }
    public function setLocation(?string $location): void { $this->location = $location; }
    public function setStatus(string $status): void { $this->status = $status; }
    public function setLastDepreciationDate(?string $date): void { $this->lastDepreciationDate = $date; }
    public function setNotes(?string $notes): void { $this->notes = $notes; }

    // Chuyển đổi model thành mảng để response API.
    // 'original_cost': nguyên giá — trình bày trên BC01 chỉ tiêu "Nguyên giá TSCĐ".
    // 'accumulated_depreciation': hao mòn lũy kế (TK 214) — giảm trừ trên BC01.
    // 'net_book_value' = original_cost - accumulated_depreciation: giá trị còn lại.
    // 'monthly_depreciation': mức khấu hao tháng — ảnh hưởng BC02 chỉ tiêu chi phí.
    // 'status': 'in_use' (đang sử dụng), 'idle' (chờ thanh lý), 'liquidated' (đã thanh lý).
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

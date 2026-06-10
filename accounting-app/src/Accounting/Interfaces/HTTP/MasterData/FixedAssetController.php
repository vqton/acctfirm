<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\FixedAsset;
use Accounting\Domain\Repository\FixedAssetRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý TSCĐ (Master Data)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách tài sản cố định
 *   - Ghi nhận nguyên giá, khấu hao, giá trị còn lại
 *
 * API endpoints:
 *   GET    /api/fixed-assets — Danh sách
 *   POST   /api/fixed-assets — Tạo mới
 *   GET    /api/fixed-assets/{id} — Chi tiết
 *   PUT    /api/fixed-assets/{id} — Cập nhật
 *   DELETE /api/fixed-assets/{id} — Xoá
 *
 * Tích hợp:
 *   - FixedAsset/LifecycleController xử lý mua/sang nhượng/thanh lý
 *   - FixedAsset/DepreciationReportController
 */
class FixedAssetController
{
    use CrudControllerTrait;

    /**
     * @param FixedAssetRepositoryInterface $repository
     */
    public function __construct(FixedAssetRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'fixed_assets';
    }

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'fa_';
    }

    protected function createEntity(array $data): object
    {
        return new FixedAsset(
            id: $data['id'] ?? uniqid('fa_'),
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            purchaseDate: $data['purchase_date'] ?? date('Y-m-d'),
            originalCost: (float)($data['original_cost'] ?? 0),
            depreciationMethod: $data['depreciation_method'] ?? 'straight_line',
            usefulLife: (int)($data['useful_life'] ?? 0),
            salvageValue: (float)($data['salvage_value'] ?? 0),
            monthlyDepreciation: (float)($data['monthly_depreciation'] ?? 0),
            accumulatedDepreciation: (float)($data['accumulated_depreciation'] ?? 0),
            netBookValue: (float)($data['net_book_value'] ?? 0),
            faCategory: $data['fa_category'] ?? 'tangible',
            faType: $data['fa_type'] ?? null,
            totalEstimatedUnits: isset($data['total_estimated_units']) ? (float)$data['total_estimated_units'] : null,
            purchaseCost: (float)($data['purchase_cost'] ?? 0),
            departmentId: $data['department_id'] ?? null,
            employeeId: $data['employee_id'] ?? null,
            location: $data['location'] ?? null,
            status: $data['status'] ?? 'in_use',
            lastDepreciationDate: $data['last_depreciation_date'] ?? null,
            notes: $data['notes'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['purchase_date'])) $entity->setPurchaseDate($data['purchase_date']);
        if (isset($data['original_cost'])) $entity->setOriginalCost((float)$data['original_cost']);
        if (isset($data['purchase_cost'])) $entity->setPurchaseCost((float)$data['purchase_cost']);
        if (isset($data['depreciation_method'])) $entity->setDepreciationMethod($data['depreciation_method']);
        if (isset($data['useful_life'])) $entity->setUsefulLife((int)$data['useful_life']);
        if (isset($data['salvage_value'])) $entity->setSalvageValue((float)$data['salvage_value']);
        if (isset($data['total_estimated_units'])) $entity->setTotalEstimatedUnits((float)$data['total_estimated_units']);
        if (isset($data['monthly_depreciation'])) $entity->setMonthlyDepreciation((float)$data['monthly_depreciation']);
        if (isset($data['accumulated_depreciation'])) $entity->setAccumulatedDepreciation((float)$data['accumulated_depreciation']);
        if (isset($data['net_book_value'])) $entity->setNetBookValue((float)$data['net_book_value']);
        if (isset($data['fa_category'])) $entity->setFaCategory($data['fa_category']);
        if (isset($data['fa_type'])) $entity->setFaType($data['fa_type']);
        if (isset($data['department_id'])) $entity->setDepartmentId($data['department_id']);
        if (isset($data['employee_id'])) $entity->setEmployeeId($data['employee_id']);
        if (isset($data['location'])) $entity->setLocation($data['location']);
        if (isset($data['status'])) $entity->setStatus($data['status']);
        if (isset($data['last_depreciation_date'])) $entity->setLastDepreciationDate($data['last_depreciation_date']);
        if (isset($data['notes'])) $entity->setNotes($data['notes']);
    }
}

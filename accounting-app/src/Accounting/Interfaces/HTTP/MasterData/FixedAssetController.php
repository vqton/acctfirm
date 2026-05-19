<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\FixedAsset;
use Accounting\Domain\Repository\FixedAssetRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

class FixedAssetController
{
    use CrudControllerTrait;

    private FixedAssetRepositoryInterface $repo;
    public function __construct(FixedAssetRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'fa_'; }
    protected function requiredFields(): array { return ['code', 'name', 'purchase_date', 'original_cost']; }

    protected function createEntity(array $data): object
    {
        return new FixedAsset(
            $data['id'], $data['code'], $data['name'], $data['purchase_date'],
            (float)$data['original_cost'],
            $data['depreciation_method'] ?? 'straight_line',
            (int)($data['useful_life'] ?? 0),
            (float)($data['salvage_value'] ?? 0),
            (float)($data['monthly_depreciation'] ?? 0),
            (float)($data['accumulated_depreciation'] ?? 0),
            (float)($data['net_book_value'] ?? 0),
            $data['fa_category'] ?? 'tangible',
            $data['fa_type'] ?? null,
            isset($data['total_estimated_units']) ? (float)$data['total_estimated_units'] : null,
            (float)($data['purchase_cost'] ?? 0),
            $data['department_id'] ?? null,
            $data['employee_id'] ?? null,
            $data['location'] ?? null,
            $data['status'] ?? 'in_use',
            $data['last_depreciation_date'] ?? null,
            $data['notes'] ?? null
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
        if (array_key_exists('total_estimated_units', $data)) $entity->setTotalEstimatedUnits($data['total_estimated_units'] !== null ? (float)$data['total_estimated_units'] : null);
        if (isset($data['fa_category'])) $entity->setFaCategory($data['fa_category']);
        if (isset($data['fa_type'])) $entity->setFaType($data['fa_type']);
        if (isset($data['monthly_depreciation'])) $entity->setMonthlyDepreciation((float)$data['monthly_depreciation']);
        if (isset($data['accumulated_depreciation'])) $entity->setAccumulatedDepreciation((float)$data['accumulated_depreciation']);
        if (isset($data['net_book_value'])) $entity->setNetBookValue((float)$data['net_book_value']);
        if (isset($data['department_id'])) $entity->setDepartmentId($data['department_id']);
        if (isset($data['employee_id'])) $entity->setEmployeeId($data['employee_id']);
        if (isset($data['location'])) $entity->setLocation($data['location']);
        if (isset($data['status'])) $entity->setStatus($data['status']);
        if (isset($data['last_depreciation_date'])) $entity->setLastDepreciationDate($data['last_depreciation_date']);
        if (isset($data['notes'])) $entity->setNotes($data['notes']);
    }
}

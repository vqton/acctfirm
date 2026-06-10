<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\Ccdc;
use Accounting\Domain\Repository\CcdcRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý CCDC (Công cụ dụng cụ)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách công cụ dụng cụ
 *   - Nhập kho CCDC (TK 153)
 *   - Xuất kho + phân bổ CCDC
 *
 * API endpoints:
 *   GET    /api/ccdc — Danh sách
 *   POST   /api/ccdc — Tạo mới
 *   GET    /api/ccdc/{id} — Chi tiết
 *   PUT    /api/ccdc/{id} — Cập nhật
 *   DELETE /api/ccdc/{id} — Xoá
 *
 * Tích hợp:
 *   - InventoryService xử lý tồn kho CCDC
 *   - CcdcAllocationController xử lý phân bổ
 */
class CcdcController
{
    use CrudControllerTrait;

    /**
     * @param CcdcRepositoryInterface $repository
     */
    public function __construct(CcdcRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'ccdc';
    }

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'ccdc_';
    }

    protected function createEntity(array $data): object
    {
        return new Ccdc(
            id: $data['id'] ?? uniqid('ccdc_'),
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            unit: $data['unit'] ?? 'cai',
            quantity: (float)($data['quantity'] ?? 0),
            allocationType: $data['allocation_type'] ?? 'direct',
            allocationMonths: (int)($data['allocation_months'] ?? 0),
            expenseAccount: $data['expense_account'] ?? '642',
            allocationStartDate: $data['allocation_start_date'] ?? null,
            totalCost: (float)($data['total_cost'] ?? 0),
            allocated: (float)($data['allocated'] ?? 0),
            remainingMonths: (int)($data['remaining_months'] ?? 0)
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['unit'])) $entity->setUnit($data['unit']);
        if (isset($data['quantity'])) $entity->setQuantity((float)$data['quantity']);
        if (isset($data['allocation_type'])) $entity->setAllocationType($data['allocation_type']);
        if (isset($data['allocation_months'])) $entity->setAllocationMonths((int)$data['allocation_months']);
        if (isset($data['expense_account'])) $entity->setExpenseAccount($data['expense_account']);
        if (isset($data['allocation_start_date'])) $entity->setAllocationStartDate($data['allocation_start_date']);
        if (isset($data['total_cost'])) $entity->setTotalCost((float)$data['total_cost']);
        if (isset($data['allocated'])) $entity->setAllocated((float)$data['allocated']);
        if (isset($data['remaining_months'])) $entity->setRemainingMonths((int)$data['remaining_months']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

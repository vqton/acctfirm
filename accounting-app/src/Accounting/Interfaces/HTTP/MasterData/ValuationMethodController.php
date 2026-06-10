<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\ValuationMethod;
use Accounting\Domain\Repository\ValuationMethodRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Phương pháp tính giá
 *
 * Mục đích nghiệp vụ:
 *   - CRUD phương pháp tính giá hàng tồn kho
 *   - FIFO, Weighted Average, Specific Identification
 *
 * API endpoints:
 *   GET    /api/valuation-methods — Danh sách
 *   POST   /api/valuation-methods — Tạo mới
 *   GET    /api/valuation-methods/{id} — Chi tiết
 *   PUT    /api/valuation-methods/{id} — Cập nhật
 *   DELETE /api/valuation-methods/{id} — Xoá
 *
 * Tích hợp:
 *   - InventoryService dùng để tính giá xuất kho
 */
class ValuationMethodController
{
    use CrudControllerTrait;

    /**
     * @param ValuationMethodRepositoryInterface $repository
     */
    public function __construct(ValuationMethodRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'valuation_methods';
    }

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'val_';
    }

    protected function createEntity(array $data): object
    {
        return new ValuationMethod(
            id: $data['id'] ?? uniqid('val_'),
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            description: $data['description'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['description'])) $entity->setDescription($data['description']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

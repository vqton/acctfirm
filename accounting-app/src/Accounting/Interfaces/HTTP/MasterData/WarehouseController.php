<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\Warehouse;
use Accounting\Domain\Repository\WarehouseRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Kho
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách kho
 *   - Mỗi kho có địa chỉ, người phụ trách
 *
 * API endpoints:
 *   GET    /api/warehouses — Danh sách
 *   POST   /api/warehouses — Tạo mới
 *   GET    /api/warehouses/{id} — Chi tiết
 *   PUT    /api/warehouses/{id} — Cập nhật
 *   DELETE /api/warehouses/{id} — Xoá
 *
 * Tích hợp:
 *   - Mọi nghiệp vụ nhập/xuất/chuyển kho
 */
class WarehouseController
{
    use CrudControllerTrait;

    /**
     * @param WarehouseRepositoryInterface $repository
     */
    public function __construct(WarehouseRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'warehouses';
    }

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'wh_';
    }

    protected function createEntity(array $data): object
    {
        return new Warehouse(
            id: $data['id'] ?? uniqid('wh_'),
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            address: $data['address'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['address'])) $entity->setAddress($data['address']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

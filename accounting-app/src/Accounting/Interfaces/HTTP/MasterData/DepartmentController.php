<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\Department;
use Accounting\Domain\Repository\DepartmentRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Phòng ban
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách phòng ban
 *   - Phân bổ chi phí, lương theo phòng ban
 *
 * API endpoints:
 *   GET    /api/departments — Danh sách
 *   POST   /api/departments — Tạo mới
 *   GET    /api/departments/{id} — Chi tiết
 *   PUT    /api/departments/{id} — Cập nhật
 *   DELETE /api/departments/{id} — Xoá
 *
 * Tích hợp:
 *   - EmployeeController quản lý nhân viên theo phòng ban
 */
class DepartmentController
{
    use CrudControllerTrait;

    /**
     * @param DepartmentRepositoryInterface $repository
     */
    public function __construct(DepartmentRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'departments';
    }

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'dept_';
    }

    protected function createEntity(array $data): object
    {
        return new Department(
            id: $data['id'] ?? uniqid('dept_'),
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            parentId: $data['parent_id'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['parent_id'])) $entity->setParentId($data['parent_id']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
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
}

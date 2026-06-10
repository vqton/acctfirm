<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Repository\EmployeeRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Nhân viên
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách nhân viên
 *   - Liên kết với phòng ban, chức vụ, lương
 *
 * API endpoints:
 *   GET    /api/employees — Danh sách
 *   POST   /api/employees — Tạo mới
 *   GET    /api/employees/{id} — Chi tiết
 *   PUT    /api/employees/{id} — Cập nhật
 *   DELETE /api/employees/{id} — Xoá
 *
 * Tích hợp:
 *   - PayrollController tính lương
 *   - DepartmentController quản lý phòng ban
 */
class EmployeeController
{
    use CrudControllerTrait;

    /**
     * @param EmployeeRepositoryInterface $repository
     */
    public function __construct(EmployeeRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'employees';
    }
}

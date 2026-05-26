<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\Department;
use Accounting\Domain\Repository\DepartmentRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Danh mục Phòng ban (Department Master)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD phòng ban trong doanh nghiệp
 *   - Quản lý cấu trúc phân cấp (parent_id) — phòng ban cha/con
 *   - Cơ sở để phân bổ chi phí và theo dõi ngân sách
 *
 * API endpoints:
 *   (Sử dụng CrudControllerTrait — CRUD chuẩn)
 *
 * Rủi ro:
 *   - Xóa phòng ban đang có nhân viên → mất thông tin tổ chức
 *   - Sai cấu trúc phân cấp → sai báo cáo chi phí theo phòng ban
 *
 * Tích hợp:
 *   - EmployeeController gán department_id cho nhân viên
 *   - Phân bổ chi phí lương (334) và chi phí SXC (627) theo phòng ban
 *   - FxController và báo cáo chi phí theo phòng ban
 */
class DepartmentController
{
    use CrudControllerTrait;

    private DepartmentRepositoryInterface $repo;
    public function __construct(DepartmentRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'dept_'; }

    protected function createEntity(array $data): object
    {
        return new Department($data['id'], $data['code'], $data['name'], $data['parent_id'] ?? null);
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['parent_id'])) $entity->setParentId($data['parent_id']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

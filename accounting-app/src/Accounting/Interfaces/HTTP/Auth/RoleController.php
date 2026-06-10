<?php
namespace Accounting\Interfaces\HTTP\Auth;

use Accounting\Domain\Model\Role;
use Accounting\Domain\Repository\RoleRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Vai trò (Roles) — RBAC
 *
 * Mục đích nghiệp vụ:
 *   - CRUD vai trò người dùng
 *   - Gán quyền (permissions) cho vai trò
 *   - Quản lý role_permissions mapping
 *
 * API endpoints:
 *   (Sử dụng CrudControllerTrait — CRUD chuẩn)
 *   GET    /api/roles        — Danh sách vai trò
 *   GET    /api/roles/{id}   — Chi tiết vai trò + permissions
 *   POST   /api/roles        — Tạo vai trò mới
 *   PUT    /api/roles/{id}   — Cập nhật vai trò
 *   DELETE /api/roles/{id}   — Xoá vai trò
 *
 * Rủi ro:
 *   - Xoá role đang gán cho user -> mất quyền
 *   - Cấp quyền sai -> lỗ hổng bảo mật
 *
 * Tích hợp:
 *   - UserController tham chiếu role_id
 *   - Auth kiểm tra permission dựa trên role
 */
class RoleController
{
    use CrudControllerTrait;

    private RoleRepositoryInterface $repo;
    public function __construct(RoleRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'role_'; }
    protected function requiredFields(): array { return ['name']; }

    /**
     * Tạo entity Role từ dữ liệu đầu vào
     *
     * @param array $data Dữ liệu đầu vào
     * @return object Role instance
     */
    protected function createEntity(array $data): object
    {
        return new Role($data['id'], $data['name'], $data['description'] ?? '');
    }

    /**
     * Cập nhật entity Role từ dữ liệu đầu vào
     *
     * @param object $entity Role cần cập nhật
     * @param array $data Dữ liệu cập nhật
     * @return void
     */
    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['description'])) $entity->setDescription($data['description']);
    }
}

<?php
namespace Accounting\Interfaces\HTTP\Auth;

use Accounting\Domain\Model\User;
use Accounting\Domain\Repository\UserRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Quản lý Người dùng (Users)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD người dùng hệ thống
 *   - Quản lý thông tin: tên đăng nhập, email, trạng thái
 *   - Đặt lại mật khẩu
 *   - Gán vai trò (roles) cho người dùng
 *
 * API endpoints:
 *   GET    /api/users        — Danh sách người dùng
 *   GET    /api/users/{id}   — Chi tiết người dùng
 *   POST   /api/users        — Tạo người dùng mới
 *   PUT    /api/users/{id}   — Cập nhật người dùng
 *   DELETE /api/users/{id}   — Xoá người dùng
 *   POST   /api/users/{id}/reset-password — Đặt lại mật khẩu
 *
 * Rủi ro:
 *   - Xoá user đang hoạt động -> mất phiên làm việc
 *   - Reset password không báo -> user không biết
 *   - Cấp quyền admin sai -> lỗ hổng bảo mật
 *
 * Tích hợp:
 *   - UserRepository quản lý dữ liệu users
 *   - RoleController quản lý vai trò
 *   - AuthController xác thực qua users
 */
class UserController
{
    private UserRepositoryInterface $repo;

    public function __construct(UserRepositoryInterface $repo) { $this->repo = $repo; }

    /**
     * Danh sách người dùng
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('admin', 'read');
        JsonResponse::ok(array_map(fn($x) => $x->toArray(), $this->repo->findAll()));
    }

    /**
     * Chi tiết người dùng
     *
     * @param string $id ID người dùng
     * @return void
     */
    public function get(string $id): void
    {
        Auth::requirePermission('admin', 'read');
        $entity = $this->repo->findById($id);
        if (!$entity) { JsonResponse::error('Không tìm thấy người dùng', 404); return; }
        JsonResponse::ok($entity->toArray());
    }

    /**
     * Tạo người dùng mới
     *
     * @return void
     */
    public function create(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('admin', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['username'], $data['password'])) {
            JsonResponse::error('Vui lòng nhập tên đăng nhập và mật khẩu', 400);
            return;
        }
        $data['id'] = $data['id'] ?? uniqid('usr_');
        $entity = new User(
            $data['id'], $data['username'], password_hash($data['password'], PASSWORD_DEFAULT),
            $data['full_name'] ?? '', $data['email'] ?? '', true
        );
        $this->repo->save($entity);
        $this->repo->syncRoles($data['id'], $data['roles'] ?? []);
        JsonResponse::ok($entity->toArray(), 201);
    }

    /**
     * Cập nhật người dùng
     *
     * @param string $id ID người dùng
     * @return void
     */
    public function update(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('admin', 'update');
        $entity = $this->repo->findById($id);
        if (!$entity) { JsonResponse::error('Không tìm thấy người dùng', 404); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { JsonResponse::error('Dữ liệu không hợp lệ', 400); return; }
        if (isset($data['full_name'])) $entity->setFullName($data['full_name']);
        if (isset($data['email'])) $entity->setEmail($data['email']);
        if (isset($data['status'])) $entity->setActive((bool)$data['status']);
        $this->repo->save($entity);
        if (isset($data['roles'])) $this->repo->syncRoles($id, $data['roles']);
        JsonResponse::ok($entity->toArray());
    }

    /**
     * Xoá người dùng
     *
     * @param string $id ID người dùng
     * @return void
     */
    public function delete(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('admin', 'delete');
        if (!$this->repo->findById($id)) {
            JsonResponse::error('Không tìm thấy người dùng', 404); return;
        }
        $this->repo->delete($id);
        JsonResponse::ok(['message' => 'Đã xoá người dùng']);
    }

    /**
     * Đặt lại mật khẩu
     *
     * @param string $id ID người dùng
     * @return void
     */
    public function resetPassword(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('admin', 'update');
        $entity = $this->repo->findById($id);
        if (!$entity) { JsonResponse::error('Không tìm thấy người dùng', 404); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['password'])) {
            JsonResponse::error('Vui lòng nhập mật khẩu mới', 400);
            return;
        }
        $entity->setPasswordHash(password_hash($data['password'], PASSWORD_DEFAULT));
        $this->repo->save($entity);
        JsonResponse::ok(['message' => 'Đã đặt lại mật khẩu']);
    }
}

<?php
namespace Accounting\Interfaces\HTTP\Auth;

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Quản lý Người dùng
 *
 * Mục đích nghiệp vụ:
 *   - CRUD người dùng hệ thống kế toán
 *   - Quản lý mật khẩu (hash bằng password_hash)
 *   - Gán vai trò cho người dùng (liên kết user_roles)
 *   - Theo dõi thời gian đăng nhập cuối
 *
 * API endpoints:
 *   GET    /api/users          — Danh sách người dùng (kèm vai trò)
 *   POST   /api/users          — Tạo người dùng mới
 *   PUT    /api/users/{id}     — Cập nhật thông tin
 *   DELETE /api/users/{id}     — Vô hiệu hóa người dùng
 *   PUT    /api/users/{id}/password — Đổi mật khẩu
 *
 * Rủi ro:
 *   - Lộ mật khẩu: không log password_hash, không trả về trong response
 *   - Người dùng cuối cùng có quyền admin: không cho xóa
 *   - Tài khoản bị vô hiệu hóa nhưng vẫn giữ session cũ (cần kiểm tra status mỗi request)
 *
 * Tích hợp:
 *   - AuthController dùng bảng users để xác thực
 *   - RoleController quản lý role, UserController gán user_roles
 */
class UserController
{
    private \PDO $pdo;
    public function __construct(\PDO $pdo) { $this->pdo = $pdo; }

    public function list(): void
    {
        $stmt = $this->pdo->query('SELECT u.id, u.username, u.full_name, u.email, u.status, u.last_login, u.created_at,
            GROUP_CONCAT(r.name SEPARATOR ", ") as role_names
            FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id GROUP BY u.id ORDER BY u.created_at DESC');
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function listWithRoles(): void
    {
        Auth::requirePermission('system', 'view');
        $this->list();
    }

    // NGHIỆP VỤ: Tạo người dùng mới — hash password + gán vai trò
    // Input: { username, password, full_name, email?, role_ids?: [...] }
    // Output: { id } — 201 Created
    // Permission: system, create
    // Rủi ro: FORBIDDEN — không log password_hash, không trả về trong response
    // Bảo mật: password_hash() với PASSWORD_DEFAULT (bcrypt). username unique check
    // Ràng buộc: User mới mặc định status = 'active'. Gán role_ids nếu có
    public function create(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('system', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['username'], $data['password'], $data['full_name'])) {
            JsonResponse::error('Vui lòng nhập tên đăng nhập, mật khẩu và họ tên'); return;
        }
        $id = uniqid('u_');
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $email = $data['email'] ?? null;
        $this->pdo->prepare('INSERT INTO users (id, username, password_hash, full_name, email) VALUES (?, ?, ?, ?, ?)')
            ->execute([$id, $data['username'], $hash, $data['full_name'], $email]);

        if (!empty($data['role_ids'])) {
            $ins = $this->pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
            foreach ($data['role_ids'] as $rid) $ins->execute([$id, $rid]);
        }
        JsonResponse::ok(['id' => $id], 201);
    }

    // NGHIỆP VỤ: Cập nhật thông tin người dùng — full_name, email, status, password, role_ids
    // Input: { full_name?, email?, status?, password?, role_ids?: [...] }
    // Output: { message: 'Updated' }
    // Permission: system, edit
    // Rủi ro: Chỉ update các field được gửi lên (partial update). Password được hash lại
    // Khi role_ids thay đổi: DELETE + INSERT user_roles (không kiểm tra role tồn tại)
    // Ràng buộc: Không cho đổi username (unique constraint). Status 'inactive' = vô hiệu hóa
    public function update(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('system', 'edit');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { JsonResponse::error('Không có dữ liệu đầu vào'); return; }
        $user = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $user->execute([$id]);
        if (!$user->fetch()) { JsonResponse::error('Không tìm thấy người dùng', 404); return; }

        if (isset($data['full_name']))
            $this->pdo->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$data['full_name'], $id]);
        if (isset($data['email']))
            $this->pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$data['email'], $id]);
        if (isset($data['status']))
            $this->pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$data['status'], $id]);
        if (!empty($data['password']))
            $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($data['password'], PASSWORD_DEFAULT), $id]);

        if (isset($data['role_ids'])) {
            $this->pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$id]);
            $ins = $this->pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
            foreach ($data['role_ids'] as $rid) $ins->execute([$id, $rid]);
        }
        JsonResponse::ok(['message' => 'Đã cập nhật thông tin người dùng']);
    }

    // NGHIỆP VỤ: Vô hiệu hóa người dùng (soft delete) — không xóa khỏi DB
    // Input: id (URL)
    // Output: { message: 'Deactivated' }
    // Permission: system, delete
    // Rủi ro: FORBIDDEN — không DELETE vật lý (soft delete = status='inactive')
    // Ràng buộc: Không cho vô hiệu hóa user 'admin' (last admin protection)
    // Session cũ của user bị vô hiệu hóa vẫn tồn tại — index.php kiểm tra status mỗi request
    public function delete(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('system', 'delete');
        if ($id === 'admin') { JsonResponse::error('Không thể xóa tài khoản quản trị viên mặc định'); return; }
        $this->pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute(['inactive', $id]);
        JsonResponse::ok(['message' => 'Đã vô hiệu hóa tài khoản người dùng']);
    }
}

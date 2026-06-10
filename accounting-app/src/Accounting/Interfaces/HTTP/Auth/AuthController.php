<?php
namespace Accounting\Interfaces\HTTP\Auth;

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\SessionMiddleware;

/**
 * MODULE: Xác thực & Phân quyền
 *
 * Mục đích nghiệp vụ:
 *   - Xử lý đăng nhập/đăng xuất người dùng
 *   - Kiểm tra phiên làm việc (session) và quyền truy cập
 *   - Cung cấp thông tin người dùng hiện tại cho giao diện
 *
 * API endpoints:
 *   POST /api/auth/login       — Đăng nhập
 *   POST /api/auth/logout      — Đăng xuất
 *   GET  /api/auth/me          — Thông tin người dùng hiện tại
 *   GET  /api/auth/permissions — Danh sách quyền
 *
 * Rủi ro:
 *   - Session fixation: phải regenerate_id sau login (đã xử lý)
 *   - Brute force: cần giới hạn số lần đăng nhập sai
 *   - Session timeout: 8 giờ không hoạt động → tự động logout
 *
 * Tích hợp:
 *   - Gọi từ public/index.php trước mọi route
 *   - SessionMiddleware đóng session cho AJAX concurrent
 */
class AuthController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Đăng nhập hệ thống
     *
     * Xác thực username/password, tải quyền và vai trò,
     * regenerate session ID chống fixation.
     *
     * @return void
     */
    public function login(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['username'], $data['password'])) {
            JsonResponse::error('Vui lòng nhập tên đăng nhập và mật khẩu');
            return;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ? AND status = ?');
        $stmt->execute([$data['username'], 'active']);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            JsonResponse::error('Tên đăng nhập hoặc mật khẩu không chính xác. Vui lòng kiểm tra lại.', 401);
            return;
        }

        // Load permissions
        $permStmt = $this->pdo->prepare(
            'SELECT rp.* FROM role_permissions rp
             JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = ?'
        );
        $permStmt->execute([$user['id']]);
        $perms = [];
        while ($p = $permStmt->fetch(\PDO::FETCH_ASSOC)) {
            $perms[$p['module']] = [
                'can_view' => (bool)$p['can_view'],
                'can_create' => (bool)$p['can_create'],
                'can_edit' => (bool)$p['can_edit'],
                'can_delete' => (bool)$p['can_delete'],
                'can_post' => (bool)$p['can_post'],
                'can_print' => (bool)$p['can_print'],
            ];
        }

        // Load roles
        $roleStmt = $this->pdo->prepare(
            'SELECT r.id, r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?'
        );
        $roleStmt->execute([$user['id']]);
        $roles = $roleStmt->fetchAll(\PDO::FETCH_ASSOC);

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
        ];
        $_SESSION['permissions'] = $perms;
        $_SESSION['roles'] = $roles;
        $_SESSION['is_admin'] = in_array('admin', array_column($roles, 'id'));

        // Update last_login
        $this->pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

        JsonResponse::ok([
            'user' => $_SESSION['user'],
            'roles' => $roles,
            'csrf' => Auth::csrfToken(),
        ]);
    }

    /**
     * Đăng xuất — destroy session + xóa file session + redirect
     *
     * @return void
     */
    public function logout(): void
    {
        $oldSid = session_id();
        $savePath = session_save_path() ?: sys_get_temp_dir();
        SessionMiddleware::destroy();
        $oldFile = $savePath . '/sess_' . $oldSid;
        if (file_exists($oldFile)) {
            @unlink($oldFile);
        }
        header('Location: /dang-nhap', true, 302);
        exit;
    }

    /**
     * Lấy thông tin người dùng hiện tại
     *
     * @return void
     */
    public function me(): void
    {
        if (!isset($_SESSION['user'])) {
            JsonResponse::error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.', 401);
            return;
        }
        JsonResponse::ok([
            'user' => $_SESSION['user'],
            'roles' => $_SESSION['roles'] ?? [],
            'permissions' => $_SESSION['permissions'] ?? [],
            'is_admin' => $_SESSION['is_admin'] ?? false,
        ]);
    }
}

<?php
namespace Accounting\Infrastructure;

// Xác thực & phân quyền người dùng — cổng bảo vệ mọi nghiệp vụ kế toán.
// Mọi API endpoint bắt buộc kiểm tra quyền trước khi ghi nhận dữ liệu.
class Auth
{
    // Kiểm tra phiên đăng nhập — nếu chưa đăng nhập, từ chối mọi thao tác
    // Dùng session-based auth, không dùng token/JWT — phù hợp cho ERP nội bộ
    // LƯU Ý: Không gọi session_start() ở đây vì SessionMiddleware đã xử lý trước
    public static function isAuthenticated(): bool
    {
        return isset($_SESSION['user']);
    }

    // Lấy thông tin người dùng hiện tại — dùng để ghi audit trail (ai làm gì)
    // Trả về ['id', 'email', 'name', 'username'] — trường id được dùng trong AuditLogger
    // Nếu chưa đăng nhập trả về null — controller cần kiểm tra trước khi dùng
    public static function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    // R-3: Lấy ID user hiện tại (dùng cho RBAC scope theo created_by)
    // RỦI RO: Nếu trả về null/sai, có thể filter sai → lộ hoặc giấu dữ liệu
    public static function getCurrentUserId(): ?string
    {
        return $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? null;
    }

    // R-3: Lấy role hiện tại (admin/chief_accountant/accountant/viewer)
    // Vai trò dùng cho RBAC scope: admin + chief_accountant thấy tất cả,
    // accountant chỉ thấy data của mình (nếu rbac.scope_by_creator=true)
    public static function getCurrentUserRole(): string
    {
        if (self::isAdmin()) return 'admin';
        return $_SESSION['user']['role'] ?? 'accountant';
    }

    // R-3: Kiểm tra user hiện tại có được xem dữ liệu của user khác không
    // Admin + Chief Accountant (KTT) luôn được; Accountant chỉ được data của mình
    public static function canViewAllData(): bool
    {
        $role = self::getCurrentUserRole();
        return in_array($role, ['admin', 'chief_accountant'], true);
    }

    // Kiểm tra quyền quản trị — admin có toàn quyền trên mọi module kế toán
    // RỦI RO: Nếu isAdmin bị set sai, user có thể post bút toán trái phép
    // Chỉ Kế toán trưởng mới được gán quyền admin qua Role Management
    public static function isAdmin(): bool
    {
        return $_SESSION['is_admin'] ?? false;
    }

    // Kiểm tra quyền truy cập theo module & hành động (RBAC)
    // Ví dụ: module 'cash' + action 'post' -> kiểm tra can_post
    // Action map: view/create/edit/delete/post/print — tương ứng 6 quyền cơ bản
    // Mở rộng: module mới cần thêm action thì cập nhật actionMap
    // RỦI RO: Nếu permission không được set đúng, user có thể xem/gửi báo cáo tài chính trái phép
    public static function hasPermission(string $module, string $action): bool
    {
        if (self::isAdmin()) return true;
        $perms = $_SESSION['permissions'] ?? [];
        if (!isset($perms[$module])) return false;

        $actionMap = [
            'view' => 'can_view',
            'read' => 'can_view',
            'create' => 'can_create',
            'edit' => 'can_edit',
            'update' => 'can_edit',
            'delete' => 'can_delete',
            'post' => 'can_post',
            'print' => 'can_print',
        ];

        $permKey = $actionMap[$action] ?? null;
        if (!$permKey) return false;

        return $perms[$module][$permKey] ?? false;
    }

    // Bảo vệ API — trả về 401/403 nếu không có quyền, không cho phép ghi nhận nghiệp vụ
    // 401: chưa đăng nhập — yêu cầu đăng nhập lại
    // 403: đã đăng nhập nhưng không có quyền — cần cấp quyền từ Kế toán trưởng
    // Mọi controller bắt buộc gọi hàm này trước khi xử lý nghiệp vụ
    public static function requirePermission(string $module, string $action): void
    {
        if (!self::isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Chưa đăng nhập'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!self::hasPermission($module, $action)) {
            http_response_code(403);
            echo json_encode(['error' => 'Không có quyền thực hiện'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // ── CSRF ──

    // Sinh token chống giả mạo — bắt buộc cho mọi POST/PUT/DELETE
    // Bảo vệ giao dịch tài chính khỏi tấn công Cross-Site Request Forgery
    // Token được sinh một lần mỗi session, dùng random_bytes(32) — đủ mạnh
    // Client lấy token qua meta tag trong layout hoặc API /api/auth/csrf
    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    // Xác thực CSRF trước khi ghi nhận bút toán — nếu token sai, từ chối với mã 419
    // Kiểm tra từ header X-CSRF-Token (AJAX) hoặc _csrf (form POST)
    // HTTP 419: Authentication Timeout — chuẩn cho CSRF failure
    // RỦI RO: Nếu bỏ qua checkCsrf, hacker có thể tạo bút toán giả mạo từ link độc hại
    public static function checkCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null;
        if (!$token || $token !== self::csrfToken()) {
            http_response_code(419);
            echo json_encode(['error' => 'Mã bảo vệ CSRF không hợp lệ. Vui lòng tải lại trang và thử lại.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

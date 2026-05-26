<?php
namespace Accounting\Infrastructure;

// Quản lý phiên làm việc — đảm bảo an toàn cho session kế toán
// Mọi request đều qua middleware này trước khi vào controller
class SessionMiddleware
{
    // Thời gian timeout 8h — tương ứng một ca làm việc, hết ca tự động đăng xuất
    // Quy định Kiểm toán: phiên làm việc tối đa 8h, sau đó phải đăng nhập lại
    // RỦI RO: Nếu timeout quá dài, session bị chiếm có thể gây thiệt hại lớn
    // RỦI RO: Nếu timeout quá ngắn, người dùng bị logout giữa chừng khi đang nhập chứng từ
    private const TIMEOUT = 28800; // 8 hours

    // Mở phiên làm việc — tự động đăng xuất nếu hết thời gian timeout
    // Bảo vệ dữ liệu kế toán khỏi truy cập trái phép khi người dùng quên logout
    // Kiểm tra session fixation: nếu user đã login nhưng không có last_activity → destroy
    // Cập nhật last_activity mỗi request — giữ phiên sống khi đang thao tác
    // Chống session fixation: session_start() chỉ gọi một lần, tránh trùng SessionMiddleware::open()
    public static function open(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::configureCookie();
            session_start();
        }

        if (isset($_SESSION['user']) && isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > self::TIMEOUT) {
                self::destroy();
                return;
            }
        }

        if (isset($_SESSION['user'])) {
            $_SESSION['last_activity'] = time();
        }
    }

    // Giải phóng session lock — cho phép nhiều AJAX request chạy đồng thời
    // Quan trọng cho các thao tác nhập liệu kế toán song song
    // PHP mặc định lock session file — nếu không close, các AJAX request khác phải đợi tuần tự
    // VD: Người dùng mở 2 tab cùng nhập chứng từ — cần close để tránh blocking
    // FORBIDDEN: Gọi session_write_close() trước khi ghi session — gây mất dữ liệu session
    public static function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    // Hủy phiên — xóa toàn bộ dữ liệu phiên, yêu cầu đăng nhập lại
    // Bắt buộc gọi khi logout để tránh session fixation
    // Quy trình: xóa $_SESSION → session_destroy() → xóa cookie session
    // RỦI RO: Nếu chỉ xóa $_SESSION mà không session_destroy(), file session vẫn còn trên server
    // Xóa cookie: set thời gian âm (time() - 42000) — trình duyệt tự động xóa
    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
    }

    // Bảo vệ API — kiểm tra đăng nhập trước khi xử lý nghiệp vụ kế toán
    // Gọi open() trước để đảm bảo session đã được khởi tạo và kiểm tra timeout
    // Trả về thông tin user + permissions + roles cho controller
    // Nếu chưa đăng nhập: trả về 401 JSON — AJAX client tự xử lý redirect
    // LƯU Ý: Hàm này dùng exit — không return về controller được nếu chưa đăng nhập
    public static function authGuard(): array
    {
        self::open();
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Chưa đăng nhập', 'code' => 401]);
            exit;
        }
        $user = $_SESSION['user'];
        $permissions = $_SESSION['permissions'] ?? [];
        $roles = $_SESSION['roles'] ?? [];
        $isAdmin = $_SESSION['is_admin'] ?? false;
        return compact('user', 'permissions', 'roles', 'isAdmin');
    }

    // Cấu hình cookie an toàn — httpOnly chống XSS, SameSite=Lax chống CSRF
    // httpOnly: JavaScript không đọc được session cookie — chống XSS
    // SameSite=Lax: Cookie chỉ gửi khi điều hướng cấp cao (GET) — chống CSRF cơ bản
    // secure=true nếu HTTPS — bảo vệ cookie trên đường truyền
    // lifetime=0: session cookie — tự động xóa khi đóng trình duyệt
    private static function configureCookie(): void
    {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

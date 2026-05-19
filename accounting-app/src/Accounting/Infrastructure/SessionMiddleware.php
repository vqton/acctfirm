<?php
namespace Accounting\Infrastructure;

class SessionMiddleware
{
    private const TIMEOUT = 28800; // 8 hours

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

    public static function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

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

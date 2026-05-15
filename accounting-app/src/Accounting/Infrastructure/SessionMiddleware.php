<?php
namespace Accounting\Infrastructure;

class SessionMiddleware
{
    public static function open(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
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
}

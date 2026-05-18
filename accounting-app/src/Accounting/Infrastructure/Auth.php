<?php
namespace Accounting\Infrastructure;

class Auth
{
    public static function isAuthenticated(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return $_SESSION['is_admin'] ?? false;
    }

    public static function hasPermission(string $module, string $action): bool
    {
        if (self::isAdmin()) return true;
        $perms = $_SESSION['permissions'] ?? [];
        if (!isset($perms[$module])) return false;

        $actionMap = [
            'view' => 'can_view',
            'create' => 'can_create',
            'edit' => 'can_edit',
            'delete' => 'can_delete',
            'post' => 'can_post',
            'print' => 'can_print',
        ];

        $permKey = $actionMap[$action] ?? null;
        if (!$permKey) return false;

        return $perms[$module][$permKey] ?? false;
    }

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

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function checkCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null;
        if (!$token || $token !== self::csrfToken()) {
            http_response_code(419);
            echo json_encode(['error' => 'CSRF token mismatch'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

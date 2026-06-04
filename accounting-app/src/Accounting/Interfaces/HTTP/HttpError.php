<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Infrastructure\JsonResponse;

class HttpError
{
    public static int $code;
    public static string $message;

    public static function json(int $code, string $message): void
    {
        JsonResponse::error($message, $code);
        exit;
    }

    private const VIEW_MAP = [
        403 => '403.php',
        404 => '404.php',
        500 => '500.php',
        503 => 'maintenance.php',
    ];

    public static function html(int $code, string $message, string $detail = ''): void
    {
        http_response_code($code);
        self::$code = $code;
        self::$message = $detail ?: $message;
        $view = self::VIEW_MAP[$code] ?? 'error.php';
        $title = match ($code) {
            401 => 'Không có quyền truy cập',
            403 => 'Truy cập bị từ chối',
            404 => 'Không tìm thấy',
            405 => 'Phương thức không được hỗ trợ',
            409 => 'Xung đột dữ liệu',
            500 => 'Lỗi máy chủ nội bộ',
            503 => 'Đang bảo trì',
            default => 'Lỗi',
        };
        require __DIR__ . '/../../../../public/views/' . $view;
        exit;
    }

    public static function badRequest(string $msg = 'Yêu cầu không hợp lệ'): void { self::json(400, $msg); }
    public static function unauthorized(string $msg = 'Không có quyền truy cập'): void { self::json(401, $msg); }
    public static function forbidden(string $msg = 'Truy cập bị từ chối'): void { self::json(403, $msg); }
    public static function notFound(string $msg = 'Không tìm thấy tài nguyên'): void { self::json(404, $msg); }
    public static function conflict(string $msg = 'Xung đột dữ liệu'): void { self::json(409, $msg); }
    public static function internal(string $msg = 'Lỗi máy chủ nội bộ'): void { self::json(500, $msg); }
    public static function serviceUnavailable(string $msg = 'Hệ thống đang bảo trì'): void { self::html(503, $msg); }
}
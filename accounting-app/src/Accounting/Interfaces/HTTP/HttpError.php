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

    public static function html(int $code, string $message, string $detail = ''): void
    {
        http_response_code($code);
        self::$code = $code;
        self::$message = $detail ?: $message;
        $title = match ($code) {
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            500 => 'Internal Server Error',
            default => 'Error',
        };
        require __DIR__ . '/../../../../public/views/error.php';
        exit;
    }

    public static function badRequest(string $msg = 'Bad request'): void { self::json(400, $msg); }
    public static function unauthorized(string $msg = 'Unauthorized'): void { self::json(401, $msg); }
    public static function forbidden(string $msg = 'Forbidden'): void { self::json(403, $msg); }
    public static function notFound(string $msg = 'Resource not found'): void { self::json(404, $msg); }
    public static function conflict(string $msg = 'Conflict'): void { self::json(409, $msg); }
    public static function internal(string $msg = 'Internal server error'): void { self::json(500, $msg); }
}
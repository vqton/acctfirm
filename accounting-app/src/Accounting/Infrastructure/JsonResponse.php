<?php
namespace Accounting\Infrastructure;

class JsonResponse
{
    public static function ok(mixed $data = null, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data === null ? ['ok' => true] : $data, JSON_UNESCAPED_UNICODE);
    }

    public static function error(string $message, int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    }
}

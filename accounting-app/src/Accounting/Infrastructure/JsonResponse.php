<?php
namespace Accounting\Infrastructure;

/**
 * Chuẩn hóa API response cho toàn bộ hệ thống.
 *
 * Mọi controller bắt buộc dùng class này để trả về JSON response.
 * Format chuẩn: {"data": {...}} cho thành công, {"error": "message"} cho lỗi.
 */
class JsonResponse
{
    /**
     * Trả về kết quả thành công.
     *
     * Format chuẩn: {"data": {...}} hoặc {"ok": true} nếu không có data.
     * Content-Type luôn là application/json; charset=utf-8 — hỗ trợ tiếng Việt.
     * JSON_UNESCAPED_UNICODE: giữ nguyên ký tự tiếng Việt, không chuyển thành \uXXXX.
     *
     * @param mixed $data Dữ liệu trả về cho client.
     * @param int $code HTTP status code (mặc định 200, POST tạo mới nên dùng 201).
     * @return void
     */
    public static function ok(mixed $data = null, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data === null ? ['ok' => true] : $data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Trả về lỗi nghiệp vụ.
     *
     * Format chuẩn: {"error": "message"} — mọi controller dùng chung pattern này.
     * Code mặc định 400: lỗi do client gửi sai dữ liệu.
     * Code 422: lỗi nghiệp vụ (Dr != Cr, kỳ đã đóng, control account).
     * Code 500: lỗi hệ thống — message không nên để lộ stack trace.
     *
     * @param string $message Thông báo lỗi giải thích lý do từ chối.
     * @param int $code HTTP status code (mặc định 400).
     * @return void
     */
    public static function error(string $message, int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    }
}

<?php
namespace Accounting\Infrastructure;

// Chuẩn hóa API response cho toàn bộ hệ thống — mọi controller bắt buộc dùng class này
class JsonResponse
{
    // Trả về kết quả thành công — data là nội dung nghiệp vụ trả về cho client
    // Format chuẩn: {"data": {...}} hoặc {"ok": true} nếu không có data
    // Content-Type luôn là application/json; charset=utf-8 — hỗ trợ tiếng Việt
    // HTTP status mặc định 200, riêng POST tạo mới nên dùng 201
    // JSON_UNESCAPED_UNICODE: giữ nguyên ký tự tiếng Việt, không chuyển thành \uXXXX
    public static function ok(mixed $data = null, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data === null ? ['ok' => true] : $data, JSON_UNESCAPED_UNICODE);
    }

    // Trả về lỗi nghiệp vụ — message giải thích lý do từ chối (VD: "Tài khoản không tồn tại")
    // Format chuẩn: {"error": "message"} — mọi controller dùng chung pattern này
    // Code mặc định 400: lỗi do client gửi sai dữ liệu
    // Code 422: lỗi nghiệp vụ (Dr != Cr, kỳ đã đóng, control account)
    // Code 500: lỗi hệ thống — message không nên để lộ stack trace
    public static function error(string $message, int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    }
}

<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Xử lý lỗi HTTP (Error Handling)
 *
 * Cung cấp các method tiện ích trả về lỗi dạng JSON hoặc HTML.
 * Được Router gọi khi không tìm thấy route hoặc xảy ra lỗi xác thực/phân quyền.
 *
 * API endpoints:
 *   (Không phải controller — static helper)
 *
 * Rủi ro:
 *   - Gọi exit() trong json() — không thể continue execution
 *   - VIEW_MAP hardcode đường dẫn view — cần cập nhật khi thêm view lỗi mới
 */
class HttpError
{
    public static int $code;
    public static string $message;

    /**
     * Trả về lỗi dạng JSON và kết thúc request
     *
     * @param int $code Mã HTTP status
     * @param string $message Thông báo lỗi
     * @return void
     */
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

    /**
     * Trả về lỗi dạng HTML view
     *
     * @param int $code Mã HTTP status
     * @param string $message Thông báo lỗi
     * @param string $detail Chi tiết lỗi (tùy chọn)
     * @return void
     */
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

    /**
     * Lỗi 400 — Yêu cầu không hợp lệ
     *
     * @param string $msg Thông báo lỗi
     * @return void
     */
    public static function badRequest(string $msg = 'Yêu cầu không hợp lệ'): void { self::json(400, $msg); }

    /**
     * Lỗi 401 — Không có quyền truy cập
     *
     * @param string $msg Thông báo lỗi
     * @return void
     */
    public static function unauthorized(string $msg = 'Không có quyền truy cập'): void { self::json(401, $msg); }

    /**
     * Lỗi 403 — Truy cập bị từ chối
     *
     * @param string $msg Thông báo lỗi
     * @return void
     */
    public static function forbidden(string $msg = 'Truy cập bị từ chối'): void { self::json(403, $msg); }

    /**
     * Lỗi 404 — Không tìm thấy tài nguyên
     *
     * @param string $msg Thông báo lỗi
     * @return void
     */
    public static function notFound(string $msg = 'Không tìm thấy tài nguyên'): void { self::json(404, $msg); }

    /**
     * Lỗi 409 — Xung đột dữ liệu
     *
     * @param string $msg Thông báo lỗi
     * @return void
     */
    public static function conflict(string $msg = 'Xung đột dữ liệu'): void { self::json(409, $msg); }

    /**
     * Lỗi 500 — Lỗi máy chủ nội bộ
     *
     * @param string $msg Thông báo lỗi
     * @return void
     */
    public static function internal(string $msg = 'Lỗi máy chủ nội bộ'): void { self::json(500, $msg); }

    /**
     * Lỗi 503 — Hệ thống đang bảo trì (HTML view)
     *
     * @param string $msg Thông báo lỗi
     * @return void
     */
    public static function serviceUnavailable(string $msg = 'Hệ thống đang bảo trì'): void { self::html(503, $msg); }
}

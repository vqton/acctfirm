<?php
namespace Accounting\Infrastructure\Logging;

/**
 * Nhật ký bất biến — ghi mọi request HTTP vào file JSON Lines.
 *
 * Yêu cầu từ Kiểm toán: ai làm gì, lúc nào, kết quả ra sao.
 * Không thể sửa/xóa — append-only.
 * Mỗi ngày một file .jsonl riêng, tự động dọn dẹp log cũ (>30 ngày).
 * Tự động sanitize mật khẩu và token trong request body trước khi ghi.
 */
class ActionJournal
{
    private static ?string $logDir = null;
    private static ?string $customAction = null;

    /**
     * Khởi tạo thư mục log.
     *
     * Log được lưu tại logs/actions/ — mỗi ngày một file .jsonl riêng.
     * Tạo thư mục nếu chưa tồn tại với permission 0755.
     *
     * @return void
     */
    public static function init(): void
    {
        self::$logDir = __DIR__ . '/../../../../logs/actions';
        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0755, true);
        }
    }

    /**
     * Ghi đè action name.
     *
     * Dùng để đặt tên nghiệp vụ cụ thể (VD: auth.login, journal.post).
     * Mặc định action được tự động sinh từ URI (api.cash.receipts).
     * Reset về null sau mỗi lần record — tránh ảnh hưởng request sau.
     *
     * @param string $action Tên action tùy chỉnh.
     * @return void
     */
    public static function setAction(string $action): void
    {
        self::$customAction = $action;
    }

    /**
     * Ghi lại một request HTTP.
     *
     * Bao gồm method, URI, status, thời gian, request/response body.
     * Tự động sanitize mật khẩu và token trong request body.
     * Format JSON Lines: mỗi dòng là một JSON object.
     * Entry gồm: ts (ISO8601), action, method, uri, status, ms, user, req_id.
     * Response body: chỉ lưu cho /api/ — truncate nếu > 10000 ký tự.
     * LOCK_EX: chống ghi đồng thời từ nhiều request.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE...).
     * @param string $uri URI của request.
     * @param int $statusCode HTTP status code.
     * @param string|null $requestBody Request body (JSON string).
     * @param string|null $responseBody Response body (JSON string).
     * @param float $durationMs Thời gian xử lý (milliseconds).
     * @param string|null $userId ID người dùng (hoặc 'anon').
     * @param string|null $requestId ID request duy nhất.
     * @return void
     */
    public static function record(
        string $method,
        string $uri,
        int $statusCode,
        ?string $requestBody,
        ?string $responseBody,
        float $durationMs,
        ?string $userId = null,
        ?string $requestId = null
    ): void {
        if (self::$logDir === null) {
            self::init();
        }

        $entry = [
            'ts' => (new \DateTime())->format('Y-m-d\TH:i:s.vP'),
            'action' => self::$customAction ?? self::actionFromUri($method, $uri),
            'method' => $method,
            'uri' => $uri,
            'status' => $statusCode,
            'ms' => round($durationMs, 2),
            'user' => $userId ?? 'anon',
            'req_id' => $requestId ?? '-',
        ];
        self::$customAction = null;

        if ($requestBody !== null && $requestBody !== '') {
            $decoded = json_decode($requestBody, true);
            $entry['req'] = $decoded !== null
                ? self::sanitize($decoded)
                : mb_substr($requestBody, 0, 5000);
        }

        if ($responseBody !== null && $responseBody !== '' && str_starts_with($uri, '/api/')) {
            $truncated = mb_strlen($responseBody) > 10000
                ? mb_substr($responseBody, 0, 10000) . "\n…[truncated]"
                : $responseBody;
            $decoded = json_decode($truncated, true);
            $entry['res'] = $decoded !== null
                ? self::truncateResponse($decoded)
                : $truncated;
        }

        $file = self::$logDir . '/' . date('Y-m-d') . '.jsonl';
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

        if (mt_rand(1, 100) === 1) {
            self::cleanup();
        }
    }

    /**
     * Dọn dẹp log cũ.
     *
     * Giữ tối đa 30 ngày, tránh đầy đĩa.
     * Chạy ngẫu nhiên 1/100 request — không chạy mỗi lần để tránh I/O.
     * Xóa file .jsonl có mtime > 30 ngày.
     *
     * @return void
     */
    private static function cleanup(): void
    {
        $cutoff = strtotime('-30 days');
        foreach (glob(self::$logDir . '/*.jsonl') as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    /**
     * Tạo action name từ URI.
     *
     * VD: /api/cash/receipts -> api.cash.receipts.
     * Tự động thay ID bằng :id để dễ nhóm log.
     * Hỗ trợ cả UUID (32 ký tự hex) và số nguyên.
     * Bỏ query string — không log tham số GET vào action name.
     *
     * @param string $method HTTP method.
     * @param string $uri URI của request.
     * @return string Action name (VD: api.cash.receipts).
     */
    private static function actionFromUri(string $method, string $uri): string
    {
        $uri = strtok($uri, '?') ?: $uri;
        $parts = explode('/', trim($uri, '/'));
        if (isset($parts[0]) && $parts[0] === 'api') {
            array_shift($parts);
        }
        foreach ($parts as &$part) {
            if (preg_match('/^[0-9a-f]{32}$/', $part) || preg_match('/^[0-9]+$/', $part)) {
                $part = ':id';
            }
        }
        unset($part);
        return implode('.', $parts) ?: 'root';
    }

    /**
     * Che dấu thông tin nhạy cảm trước khi ghi log.
     *
     * Fields được mask: password, password_confirm, current_password, token, secret, api_key.
     * Đệ quy vào mảng con — sanitize toàn bộ cấu trúc JSON.
     *
     * @param array $data Dữ liệu request cần sanitize.
     * @return array Dữ liệu đã được che dấu thông tin nhạy cảm.
     */
    private static function sanitize(array $data): array
    {
        $sensitive = ['password', 'password_confirm', 'current_password', 'token', 'secret', 'api_key'];
        foreach ($data as $k => $v) {
            if (in_array(strtolower($k), $sensitive, true)) {
                $data[$k] = '***';
            } elseif (is_array($v)) {
                $data[$k] = self::sanitize($v);
            }
        }
        return $data;
    }

    /**
     * Cắt ngắn response trước khi log.
     *
     * Tránh file log quá lớn.
     * depth <= 3: tránh đệ quy vô hạn cho JSON lồng nhau.
     * Mảng > 50 phần tử: chỉ giữ 50 phần tử đầu + ghi chú số phần tử còn lại.
     *
     * @param array $data Dữ liệu response cần truncate.
     * @param int $depth Độ sâu đệ quy hiện tại.
     * @return array Dữ liệu đã được cắt ngắn.
     */
    private static function truncateResponse(array $data, int $depth = 0): array
    {
        if ($depth > 3) {
            return ['...'];
        }
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                if (isset($v[0]) && count($v) > 50) {
                    $data[$k] = array_slice($v, 0, 50);
                    $data[$k][] = '…' . (count($v) - 50) . ' more';
                } elseif (is_array($v)) {
                    $data[$k] = self::truncateResponse($v, $depth + 1);
                }
            }
        }
        return $data;
    }
}

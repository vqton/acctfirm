<?php
namespace Accounting\Infrastructure\Logging;

/**
 * Ghi log hệ thống — hỗ trợ debug, monitoring, kiểm soát lỗi nghiệp vụ.
 *
 * Cung cấp các mức log: DEBUG, INFO, WARNING, ERROR.
 * Hỗ trợ in request, SQL, request body, error body ra console với màu sắc.
 * Tích hợp với LoggingPDO để ghi lại toàn bộ câu SQL.
 */
class Logger
{
    private static array $queries = [];
    private static int $logLevel = 0;
    private static ?string $logFile = null;

    public const DEBUG = 0;
    public const INFO = 1;
    public const WARNING = 2;
    public const ERROR = 3;

    /**
     * Khởi tạo logger — thiết lập file log đích.
     *
     * Nếu không có logFile, output ra error_log của PHP (thường là /var/log/php_errors.log).
     *
     * @param string|null $logFile Đường dẫn file log (null = dùng error_log).
     * @return void
     */
    public static function init(?string $logFile = null): void
    {
        self::$logFile = $logFile;
    }

    /**
     * Thiết lập mức log.
     *
     * Chỉ log những message có level >= mức này.
     * DEBUG=0 → log tất cả, ERROR=3 → chỉ log lỗi.
     *
     * @param int $level Mức log (0=DEBUG, 1=INFO, 2=WARNING, 3=ERROR).
     * @return void
     */
    public static function setLevel(int $level): void
    {
        self::$logLevel = $level;
    }

    /**
     * Ghi raw text ra log (không format).
     *
     * @param string $text Nội dung cần ghi.
     * @return void
     */
    public static function writeRaw(string $text): void
    {
        self::write($text);
    }

    /**
     * Ghi raw text ra log.
     *
     * Nếu có logFile thì append, không thì dùng error_log.
     * LƯU Ý: Không tự động thêm newline — caller phải tự thêm.
     *
     * @param string $text Nội dung cần ghi.
     * @return void
     */
    private static function write(string $text): void
    {
        if (self::$logFile) {
            file_put_contents(self::$logFile, $text, FILE_APPEND);
        } else {
            error_log(rtrim($text));
        }
    }

    /**
     * Ghi log có level.
     *
     * Format: [datetime] LEVEL message.
     * Các level: DEBUG (0), INFO (1), WARN (2), ERROR (3).
     *
     * @param string $message Thông điệp log.
     * @param int $level Mức log (mặc định INFO).
     * @return void
     */
    public static function log(string $message, int $level = self::INFO): void
    {
        if ($level < self::$logLevel) return;
        $time = date('d/M/Y H:i:s');
        $labels = ['DEBUG', 'INFO', 'WARN', 'ERROR'];
        $label = $labels[$level] ?? 'INFO';
        self::write("[{$time}] {$label} {$message}\n");
    }

    /**
     * In thông tin request ra console.
     *
     * Màu sắc: 2xx=xanh, 3xx=xanh dương, 4xx=vàng, 5xx=đỏ.
     * Duration: <1s hiển thị ms, >=1s hiển thị s (giây).
     * Tag: [Cash] cho module tiền mặt/tài khoản ngân hàng — dễ lọc log.
     *
     * @param string $method HTTP method.
     * @param string $path URI path.
     * @param int $status HTTP status code.
     * @param float $durationMs Thời gian xử lý (milliseconds).
     * @param int $size Kích thước response (bytes).
     * @param string $tag Tag cho module (VD: "[Cash]").
     * @return void
     */
    public static function printRequest(string $method, string $path, int $status, float $durationMs, int $size, string $tag = ''): void
    {
        $time = date('d/M/Y H:i:s');
        $color = match (true) {
            $status >= 500 => "\033[31m",
            $status >= 400 => "\033[33m",
            $status >= 300 => "\033[36m",
            $status >= 200 => "\033[32m",
            default => "\033[0m",
        };
        $reset = "\033[0m";
        $method = str_pad($method, 6);
        $dur = $durationMs >= 1000 ? number_format($durationMs / 1000, 2) . 's' : number_format($durationMs, 0) . 'ms';
        self::write("[{$time}] {$tag}{$color}{$method} {$path} HTTP/1.1\" {$status} {$size} {$dur}{$reset}\n");
    }

    /**
     * In câu SQL ra console.
     *
     * Format: (duration) SQL; [params].
     * Duration màu xám — dễ phân biệt với request line.
     *
     * @param string $sql Câu SQL đã prepare.
     * @param array $params Tham số bound.
     * @param float $durationMs Thời gian chạy (milliseconds).
     * @return void
     */
    public static function printSQL(string $sql, array $params, float $durationMs): void
    {
        $dur = $durationMs >= 1000 ? number_format($durationMs / 1000, 2) . 's' : number_format($durationMs, 0) . 'ms';
        $p = array_map(fn($v) => is_string($v) ? "'{$v}'" : $v, $params);
        $pStr = '[' . implode(', ', $p) . ']';
        self::write("  \033[90m({$dur}) {$sql}; {$pStr}\033[0m\n");
    }

    /**
     * Reset danh sách query cho request mới.
     *
     * Gọi ở đầu mỗi request để đảm bảo không lẫn SQL giữa các request.
     *
     * @return void
     */
    public static function startRequest(): void
    {
        self::$queries = [];
    }

    /**
     * Thêm query vào danh sách.
     *
     * Gọi từ LoggingPDO/LoggingStatement.
     * Lưu SQL + params + duration để sau request in ra console.
     *
     * @param string $sql Câu SQL.
     * @param array $params Tham số bound.
     * @param float $durationMs Thời gian chạy (milliseconds).
     * @return void
     */
    public static function addQuery(string $sql, array $params, float $durationMs): void
    {
        self::$queries[] = ['sql' => $sql, 'params' => $params, 'duration' => $durationMs];
    }

    /**
     * Lấy danh sách query đã chạy trong request hiện tại.
     *
     * Dùng trong shutdown handler để in SQL log cuối request.
     *
     * @return array Mảng các query ['sql', 'params', 'duration'].
     */
    public static function getQueries(): array
    {
        return self::$queries;
    }

    /**
     * In request body ra console.
     *
     * Dùng để debug dữ liệu gửi lên từ client.
     *
     * @param string $body Request body string.
     * @return void
     */
    public static function printRequestBody(string $body): void
    {
        if ($body === '' || $body === null) return;
        self::write("  \033[90mBODY: {$body}\033[0m\n");
    }

    /**
     * In nội dung lỗi response.
     *
     * Giúp tra cứu lỗi nghiệp vụ nhanh.
     * Chỉ in khi status >= 400 — tránh nhiễu với response thành công.
     * Cắt ngắn ở 500 ký tự — tránh log quá dài.
     * Màu vàng — dễ phân biệt với request/query log.
     *
     * @param int $status HTTP status code.
     * @param string $body Response body string.
     * @return void
     */
    public static function printErrorBody(int $status, string $body): void
    {
        if ($status < 400 || $body === '') return;
        $truncated = strlen($body) > 500 ? substr($body, 0, 500) . '...' : $body;
        self::write("  \033[33mERR:  {$truncated}\033[0m\n");
    }
}

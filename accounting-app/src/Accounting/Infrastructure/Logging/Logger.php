<?php
namespace Accounting\Infrastructure\Logging;

// Ghi log hệ thống — hỗ trợ debug, monitoring, kiểm soát lỗi nghiệp vụ
class Logger
{
    private static array $queries = [];
    private static int $logLevel = 0;
    private static ?string $logFile = null;

    public const DEBUG = 0;
    public const INFO = 1;
    public const WARNING = 2;
    public const ERROR = 3;

    // Khởi tạo logger — thiết lập file log đích
    // Nếu không có logFile, output ra error_log của PHP (thường là /var/log/php_errors.log)
    public static function init(?string $logFile = null): void
    {
        self::$logFile = $logFile;
    }

    // Thiết lập mức log — chỉ log những message có level >= mức này
    // DEBUG=0 → log tất cả, ERROR=3 → chỉ log lỗi
    // Dùng trong dev để debug SQL, dùng trong prod để tránh lộ thông tin
    public static function setLevel(int $level): void
    {
        self::$logLevel = $level;
    }

    public static function writeRaw(string $text): void
    {
        self::write($text);
    }

    // Ghi raw text ra log — nếu có logFile thì append, không thì dùng error_log
    // LƯU Ý: Không tự động thêm newline — caller phải tự thêm
    private static function write(string $text): void
    {
        if (self::$logFile) {
            file_put_contents(self::$logFile, $text, FILE_APPEND);
        } else {
            error_log(rtrim($text));
        }
    }

    // Ghi log có level — format: [datetime] LEVEL message
    // Các level: DEBUG (0), INFO (1), WARN (2), ERROR (3)
    // Dùng trong service để ghi nhận lỗi nghiệp vụ (VD: "Post entry failed: Dr != Cr")
    public static function log(string $message, int $level = self::INFO): void
    {
        if ($level < self::$logLevel) return;
        $time = date('d/M/Y H:i:s');
        $labels = ['DEBUG', 'INFO', 'WARN', 'ERROR'];
        $label = $labels[$level] ?? 'INFO';
        self::write("[{$time}] {$label} {$message}\n");
    }

    // In thông tin request ra console — dùng cho theo dõi real-time khi dev
    // Màu sắc: 2xx=xanh, 3xx=xanh dương, 4xx=vàng, 5xx=đỏ
    // Duration: <1s hiển thị ms, >=1s hiển thị s (giây)
    // Tag: [Cash] cho module tiền mặt/tài khoản ngân hàng — dễ lọc log
    // RỦI RO: Không nên log request body trong production — có thể chứa PII (mật khẩu, số CMND)
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

    // In câu SQL ra console — hỗ trợ tối ưu query, phát hiện chậm
    // Format: (duration) SQL; [params]
    // RỦI RO: Params có thể chứa dữ liệu nhạy cảm (password hash, token) — chỉ log trong dev
    // Duration màu xám — dễ phân biệt với request line
    public static function printSQL(string $sql, array $params, float $durationMs): void
    {
        $dur = $durationMs >= 1000 ? number_format($durationMs / 1000, 2) . 's' : number_format($durationMs, 0) . 'ms';
        $p = array_map(fn($v) => is_string($v) ? "'{$v}'" : $v, $params);
        $pStr = '[' . implode(', ', $p) . ']';
        self::write("  \033[90m({$dur}) {$sql}; {$pStr}\033[0m\n");
    }

    // Reset danh sách query cho request mới — gọi ở đầu mỗi request
    // Đảm bảo không lẫn SQL giữa các request
    public static function startRequest(): void
    {
        self::$queries = [];
    }

    // Thêm query vào danh sách — gọi từ LoggingPDO/LoggingStatement
    // Lưu SQL + params + duration để sau request in ra console
    public static function addQuery(string $sql, array $params, float $durationMs): void
    {
        self::$queries[] = ['sql' => $sql, 'params' => $params, 'duration' => $durationMs];
    }

    // Lấy danh sách query đã chạy trong request hiện tại
    // Dùng trong shutdown handler để in SQL log cuối request
    public static function getQueries(): array
    {
        return self::$queries;
    }

    // In request body ra console — dùng để debug dữ liệu gửi lên từ client
    // Chỉ in cho module Cash/Bank khi là POST — log trọng tâm, tránh nhiễu
    // RỦI RO: Body có thể chứa password, token — cần sanitize nếu dùng trong production
    public static function printRequestBody(string $body): void
    {
        if ($body === '' || $body === null) return;
        self::write("  \033[90mBODY: {$body}\033[0m\n");
    }

    // In nội dung lỗi response — giúp tra cứu lỗi nghiệp vụ nhanh
    // Chỉ in khi status >= 400 — tránh nhiễu với response thành công
    // Cắt ngắn ở 500 ký tự — tránh log quá dài (VD: response danh sách hàng ngàn bản ghi)
    // Màu vàng — dễ phân biệt với request/query log
    public static function printErrorBody(int $status, string $body): void
    {
        if ($status < 400 || $body === '') return;
        $truncated = strlen($body) > 500 ? substr($body, 0, 500) . '...' : $body;
        self::write("  \033[33mERR:  {$truncated}\033[0m\n");
    }
}

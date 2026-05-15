<?php
namespace Accounting\Infrastructure\Logging;

class Logger
{
    private static array $queries = [];
    private static int $logLevel = 0;
    private static ?string $logFile = null;

    public const DEBUG = 0;
    public const INFO = 1;
    public const WARNING = 2;
    public const ERROR = 3;

    public static function init(?string $logFile = null): void
    {
        self::$logFile = $logFile;
    }

    public static function setLevel(int $level): void
    {
        self::$logLevel = $level;
    }

    public static function writeRaw(string $text): void
    {
        self::write($text);
    }

    private static function write(string $text): void
    {
        if (self::$logFile) {
            file_put_contents(self::$logFile, $text, FILE_APPEND);
        } else {
            error_log(rtrim($text));
        }
    }

    public static function log(string $message, int $level = self::INFO): void
    {
        if ($level < self::$logLevel) return;
        $time = date('d/M/Y H:i:s');
        $labels = ['DEBUG', 'INFO', 'WARN', 'ERROR'];
        $label = $labels[$level] ?? 'INFO';
        self::write("[{$time}] {$label} {$message}\n");
    }

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

    public static function printSQL(string $sql, array $params, float $durationMs): void
    {
        $dur = $durationMs >= 1000 ? number_format($durationMs / 1000, 2) . 's' : number_format($durationMs, 0) . 'ms';
        $p = array_map(fn($v) => is_string($v) ? "'{$v}'" : $v, $params);
        $pStr = '[' . implode(', ', $p) . ']';
        self::write("  \033[90m({$dur}) {$sql}; {$pStr}\033[0m\n");
    }

    public static function startRequest(): void
    {
        self::$queries = [];
    }

    public static function addQuery(string $sql, array $params, float $durationMs): void
    {
        self::$queries[] = ['sql' => $sql, 'params' => $params, 'duration' => $durationMs];
    }

    public static function getQueries(): array
    {
        return self::$queries;
    }

    public static function printRequestBody(string $body): void
    {
        if ($body === '' || $body === null) return;
        self::write("  \033[90mBODY: {$body}\033[0m\n");
    }

    public static function printErrorBody(int $status, string $body): void
    {
        if ($status < 400 || $body === '') return;
        $truncated = strlen($body) > 500 ? substr($body, 0, 500) . '...' : $body;
        self::write("  \033[33mERR:  {$truncated}\033[0m\n");
    }
}

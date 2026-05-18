<?php
namespace Accounting\Infrastructure\Logging;

class ActionJournal
{
    private static ?string $logDir = null;
    private static ?string $customAction = null;

    public static function init(): void
    {
        self::$logDir = __DIR__ . '/../../../../logs/actions';
        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0755, true);
        }
    }

    public static function setAction(string $action): void
    {
        self::$customAction = $action;
    }

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

    private static function cleanup(): void
    {
        $cutoff = strtotime('-30 days');
        foreach (glob(self::$logDir . '/*.jsonl') as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

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

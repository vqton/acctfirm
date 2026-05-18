<?php
namespace Accounting\Infrastructure;

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Domain\ValueObject\VnWords;

class Helpers
{
    public static function toVnWords(float $amount): string
    {
        return VnWords::toWords($amount);
    }

    public static function fmt(float $amount, int $decimals = 0): string
    {
        return number_format($amount, $decimals, ',', '.');
    }

    public static function e(?string $str): string
    {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }

    public static function jsonOk($data = null, int $code = 200): void
    {
        JsonResponse::ok($data, $code);
    }

    public static function jsonError(string $message, int $code = 400): void
    {
        JsonResponse::error($message, $code);
    }

    public static function isValidAccountCode(string $code): bool
    {
        if (!preg_match('/^\d{3,}$/', $code)) return false;
        $len = strlen($code);
        if ($len < 3 || $len > 7) return false;
        if ($len === 3) return true;
        if ($len === 4) return true;
        return substr($code, 0, 3) === substr($code, 0, 3) && $len <= 7;
    }

    public static function nextVoucherNo(string $prefix): string
    {
        $pdo = $GLOBALS['container']['pdo'] ?? null;
        if (!$pdo) return $prefix . '-' . uniqid();

        $year = date('Y');
        $pdo->prepare(
            'INSERT INTO voucher_sequences (prefix, year, last_no) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE last_no = last_no + 1'
        )->execute([$prefix, $year]);

        $stmt = $pdo->prepare('SELECT last_no FROM voucher_sequences WHERE prefix = ? AND year = ?');
        $stmt->execute([$prefix, $year]);
        $no = (int)$stmt->fetchColumn();

        return sprintf('%s-%s-%05d', $prefix, $year, $no);
    }

    public static function paginate(\PDO $pdo, string $countSql, string $dataSql, array $params, int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $countSql = preg_replace('/\s+limit\s+\d+(\s+offset\s+\d+)?/i', '', $countSql);

        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $dataSql .= " LIMIT {$perPage} OFFSET {$offset}";
        $dataStmt = $pdo->prepare($dataSql);
        $dataStmt->execute($params);
        $data = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    // ── Auth ──

    public static function isAuthenticated(): bool
    {
        return Auth::isAuthenticated();
    }

    public static function currentUser(): ?array
    {
        return Auth::currentUser();
    }

    public static function isAdmin(): bool
    {
        return Auth::isAdmin();
    }

    public static function hasPermission(string $module, string $action): bool
    {
        return Auth::hasPermission($module, $action);
    }

    public static function requirePermission(string $module, string $action): void
    {
        Auth::requirePermission($module, $action);
    }

    // ── CSRF ──

    public static function csrfToken(): string
    {
        return Auth::csrfToken();
    }

    public static function checkCsrf(): void
    {
        Auth::checkCsrf();
    }
}

<?php
namespace Accounting\Infrastructure;

class Helpers
{
    private static array $digits = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    private static array $units = ['', 'nghìn', 'triệu', 'tỷ'];

    public static function toVnWords(float $amount): string
    {
        if ($amount == 0) return 'Không đồng';

        $negative = $amount < 0;
        $amount = abs($amount);
        $integer = (int)floor($amount);
        $fraction = (int)round(($amount - $integer) * 100);

        $result = self::readInteger($integer);
        if ($fraction > 0) {
            $result .= ' phẩy ' . self::readInteger($fraction);
        }
        $result .= ' đồng';

        $result = preg_replace('/\s+/', ' ', trim($result));

        if ($negative) {
            $result = 'Âm ' . lcfirst($result);
        }

        return ucfirst($result);
    }

    private static function readInteger(int $num): string
    {
        if ($num === 0) return 'không';

        $groups = [];
        while ($num > 0) {
            $groups[] = $num % 1000;
            $num = intdiv($num, 1000);
        }

        $parts = [];
        $seenNonZero = false;
        foreach (array_reverse($groups) as $i => $g) {
            $idx = count($groups) - 1 - $i;
            $isUnits = $idx === 0;

            if ($g === 0) {
                if ($seenNonZero && !$isUnits) {
                    foreach (array_slice($groups, 0, $idx) as $lower) {
                        if ($lower > 0) {
                            $parts[] = 'không trăm';
                            break;
                        }
                    }
                }
                continue;
            }

            $str = '';
            if ($seenNonZero && $g < 100) {
                $str .= 'không trăm ';
            }

            $h = intdiv($g, 100);
            $t = intdiv($g % 100, 10);
            $o = $g % 10;

            if ($h > 0) {
                $str .= self::$digits[$h] . ' trăm';
            }
            if ($h > 0 && ($t > 0 || $o > 0)) $str .= ' ';

            if ($t === 0) {
                if ($o > 0 && ($h > 0 || $seenNonZero)) {
                    $str .= 'linh ';
                }
            } elseif ($t === 1) {
                $str .= 'mười';
                if ($o > 0) $str .= ' ';
            } else {
                $str .= self::$digits[$t] . ' mươi';
                if ($o > 0) $str .= ' ';
            }

            if ($o > 0) {
                if ($t === 0) {
                    $str .= self::$digits[$o];
                } elseif ($t === 1) {
                    $str .= ($o === 5) ? 'lăm' : self::$digits[$o];
                } else {
                    if ($o === 1) { $str .= 'mốt'; }
                    elseif ($o === 4) { $str .= 'tư'; }
                    elseif ($o === 5) { $str .= 'lăm'; }
                    else { $str .= self::$digits[$o]; }
                }
            }

            $unit = self::$units[$idx] ?? '';
            $str .= $unit ? ' ' . $unit : '';
            $parts[] = trim($str);
            $seenNonZero = true;
        }

        return implode(' ', $parts);
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
        http_response_code($code);
        echo json_encode($data === null ? ['ok' => true] : $data, JSON_UNESCAPED_UNICODE);
    }

    public static function jsonError(string $message, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
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
        return isset($_SESSION['user']);
    }

    public static function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return $_SESSION['is_admin'] ?? false;
    }

    public static function hasPermission(string $module, string $action): bool
    {
        if (self::isAdmin()) return true;
        $perms = $_SESSION['permissions'] ?? [];
        if (!isset($perms[$module])) return false;

        $actionMap = [
            'view' => 'can_view',
            'create' => 'can_create',
            'edit' => 'can_edit',
            'delete' => 'can_delete',
            'post' => 'can_post',
            'print' => 'can_print',
        ];

        $permKey = $actionMap[$action] ?? null;
        if (!$permKey) return false;

        return $perms[$module][$permKey] ?? false;
    }

    public static function requirePermission(string $module, string $action): void
    {
        if (!self::isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Chưa đăng nhập'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!self::hasPermission($module, $action)) {
            http_response_code(403);
            echo json_encode(['error' => 'Không có quyền thực hiện'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // ── CSRF ──

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function checkCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null;
        if (!$token || $token !== self::csrfToken()) {
            http_response_code(419);
            echo json_encode(['error' => 'CSRF token mismatch'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

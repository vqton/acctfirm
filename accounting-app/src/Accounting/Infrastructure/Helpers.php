<?php
namespace Accounting\Infrastructure;

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Domain\ValueObject\VnWords;

// Hàm tiện ích dùng chung cho toàn bộ hệ thống kế toán
class Helpers
{
    // Chuyển số tiền thành chữ — dùng trên hóa đơn, phiếu thu/chi, chứng từ kế toán
    // Yêu cầu bắt buộc từ Luật Kế toán: số tiền trên chứng từ phải viết bằng chữ
    // Đầu vào float — RỦI RO: mất precision với số > 9.007.199.254.740.991 (53-bit)
    // Với số tiền thực tế (< 99 tỷ) thì float đủ an toàn, nhưng cân nhắc dùng Decimal
    public static function toVnWords(float $amount): string
    {
        return VnWords::toWords($amount);
    }

    // Định dạng số VND (1.000.000) — dùng hiển thị trên báo cáo tài chính, chứng từ
    // Sử dụng dấu chấm phân cách hàng nghìn và dấu phẩy cho thập phân
    // LƯU Ý: Đây là format hiển thị, KHÔNG phải format lưu trữ
    // Khi nhập từ giao diện, cần loại bỏ dấu phân cách trước khi lưu vào DB
    public static function fmt(float $amount, int $decimals = 0): string
    {
        return number_format($amount, $decimals, ',', '.');
    }

    // Chống XSS khi xuất dữ liệu ra HTML — bắt buộc cho mọi output chứa dữ liệu người dùng
    // Sử dụng htmlspecialchars với ENT_QUOTES — encode cả ' và "
    // UTF-8: bắt buộc vì dữ liệu kế toán chứa tiếng Việt
    // RỦI RO: Nếu quên dùng e() khi render dữ liệu từ DB, hacker có thể chèn script độc hại
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

    // Kiểm tra mã tài khoản hợp lệ — 3-7 số, tuân theo Hệ thống Tài khoản Circular 99
    // TK cấp 1: 3 số (VD: 111, 331), TK cấp 2: 4 số (1111, 3311), TK cấp 3+: 5-7 số
    // RỦI RO: Nếu cho phép mã tài khoản không hợp lệ, posting sẽ thất bại ở tầng repository
    // Hạn chế: Chỉ kiểm tra format số, không kiểm tra mã có tồn tại trong COA hay không
    public static function isValidAccountCode(string $code): bool
    {
        if (!preg_match('/^\d{3,}$/', $code)) return false;
        $len = strlen($code);
        if ($len < 3 || $len > 7) return false;
        if ($len === 3) return true;
        if ($len === 4) return true;
        return substr($code, 0, 3) === substr($code, 0, 3) && $len <= 7;
    }

    // Tự động sinh số chứng từ — format: {PREFIX}-{YYYY}-{00000}
    // Dùng INSERT ... ON DUPLICATE KEY UPDATE để đảm bảo uniqueness (không cần FOR UPDATE riêng)
    // Prefix: PC (phiếu chi), PT (phiếu thu), JV (bút toán), PNK (nhập kho), PXK (xuất kho)
    // RỦI RO: Concurrent cao có thể sinh trùng số nếu không có UNIQUE constraint trên (prefix, year)
    // Bảng voucher_sequences phải có UNIQUE KEY (prefix, year) để ON DUPLICATE KEY hoạt động đúng
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

    // Phân trang dữ liệu — dùng cho danh sách chứng từ, báo cáo, sổ sách
    // countSql: truy vấn đếm tổng số bản ghi (SELECT COUNT(*)...)
    // dataSql: truy vấn lấy dữ liệu (tự động thêm LIMIT/OFFSET)
    // RỦI RO: SQL injection nếu $countSql/$dataSql có user input — phải dùng prepared statement
    // Hiệu năng: Với bảng lớn (>1M dòng), COUNT(*) có thể chậm — cần index trên cột WHERE
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

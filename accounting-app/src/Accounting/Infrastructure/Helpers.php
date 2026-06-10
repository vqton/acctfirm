<?php
namespace Accounting\Infrastructure;

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Domain\ValueObject\VnWords;

/**
 * Hàm tiện ích dùng chung cho toàn bộ hệ thống kế toán.
 *
 * Cung cấp các helper tĩnh: định dạng số VND, chống XSS, phân trang, sinh số chứng từ,
 * và các hàm ủy quyền cho Auth (isAuthenticated, hasPermission, CSRF).
 */
class Helpers
{
    /**
     * Chuyển số tiền thành chữ.
     *
     * Dùng trên hóa đơn, phiếu thu/chi, chứng từ kế toán.
     * Yêu cầu bắt buộc từ Luật Kế toán: số tiền trên chứng từ phải viết bằng chữ.
     *
     * @param float $amount Số tiền cần chuyển.
     * @return string Số tiền bằng chữ (VD: "Một trăm triệu đồng").
     */
    public static function toVnWords(float $amount): string
    {
        return VnWords::toWords($amount);
    }

    /**
     * Định dạng số VND (1.000.000).
     *
     * Dùng hiển thị trên báo cáo tài chính, chứng từ.
     * Sử dụng dấu chấm phân cách hàng nghìn và dấu phẩy cho thập phân.
     * LƯU Ý: Đây là format hiển thị, KHÔNG phải format lưu trữ.
     *
     * @param float $amount Số tiền cần định dạng.
     * @param int $decimals Số chữ số thập phân (mặc định 0).
     * @return string Chuỗi đã định dạng (VD: "1.000.000").
     */
    public static function fmt(float $amount, int $decimals = 0): string
    {
        return number_format($amount, $decimals, ',', '.');
    }

    /**
     * Chống XSS khi xuất dữ liệu ra HTML.
     *
     * Bắt buộc cho mọi output chứa dữ liệu người dùng.
     * Sử dụng htmlspecialchars với ENT_QUOTES — encode cả ' và ".
     *
     * @param string|null $str Chuỗi cần escape.
     * @return string Chuỗi đã escape an toàn để xuất HTML.
     */
    public static function e(?string $str): string
    {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Trả về JSON response thành công.
     *
     * @param mixed $data Dữ liệu trả về.
     * @param int $code HTTP status code (mặc định 200).
     * @return void
     */
    public static function jsonOk($data = null, int $code = 200): void
    {
        JsonResponse::ok($data, $code);
    }

    /**
     * Trả về JSON response lỗi.
     *
     * @param string $message Thông báo lỗi.
     * @param int $code HTTP status code (mặc định 400).
     * @return void
     */
    public static function jsonError(string $message, int $code = 400): void
    {
        JsonResponse::error($message, $code);
    }

    /**
     * Kiểm tra mã tài khoản hợp lệ.
     *
     * Format: 3-7 số, tuân theo Hệ thống Tài khoản Circular 99.
     * TK cấp 1: 3 số (VD: 111, 331), TK cấp 2: 4 số (1111, 3311), TK cấp 3+: 5-7 số.
     * Hạn chế: Chỉ kiểm tra format số, không kiểm tra mã có tồn tại trong COA hay không.
     *
     * @param string $code Mã tài khoản cần kiểm tra.
     * @return bool True nếu mã hợp lệ, false nếu không.
     */
    public static function isValidAccountCode(string $code): bool
    {
        if (!preg_match('/^\d{3,}$/', $code)) return false;
        $len = strlen($code);
        if ($len < 3 || $len > 7) return false;
        if ($len === 3) return true;
        if ($len === 4) return true;
        return substr($code, 0, 3) === substr($code, 0, 3) && $len <= 7;
    }

    /**
     * Tự động sinh số chứng từ.
     *
     * Format: {PREFIX}-{YYYY}-{00000}.
     * Dùng INSERT ... ON DUPLICATE KEY UPDATE để đảm bảo uniqueness.
     * Prefix: PC (phiếu chi), PT (phiếu thu), JV (bút toán), PNK (nhập kho), PXK (xuất kho).
     *
     * @param string $prefix Tiền tố chứng từ (PC, PT, JV, PNK, PXK...).
     * @return string Số chứng từ đã sinh (VD: "PC-2025-00001").
     */
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

    /**
     * Phân trang dữ liệu.
     *
     * Dùng cho danh sách chứng từ, báo cáo, sổ sách.
     * countSql: truy vấn đếm tổng số bản ghi (SELECT COUNT(*)...).
     * dataSql: truy vấn lấy dữ liệu (tự động thêm LIMIT/OFFSET).
     *
     * @param \PDO $pdo Kết nối PDO.
     * @param string $countSql Truy vấn đếm tổng số bản ghi.
     * @param string $dataSql Truy vấn lấy dữ liệu (không có LIMIT/OFFSET).
     * @param array $params Tham số bound cho cả hai truy vấn.
     * @param int $page Trang hiện tại (mặc định 1).
     * @param int $perPage Số bản ghi mỗi trang (mặc định 50).
     * @return array Mảng gồm: data, total, page, per_page, total_pages.
     */
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

    /**
     * Kiểm tra phiên đăng nhập (ủy quyền cho Auth).
     *
     * @return bool True nếu đã đăng nhập.
     */
    public static function isAuthenticated(): bool
    {
        return Auth::isAuthenticated();
    }

    /**
     * Lấy thông tin người dùng hiện tại (ủy quyền cho Auth).
     *
     * @return array|null Thông tin user hoặc null.
     */
    public static function currentUser(): ?array
    {
        return Auth::currentUser();
    }

    /**
     * Kiểm tra quyền quản trị (ủy quyền cho Auth).
     *
     * @return bool True nếu là admin.
     */
    public static function isAdmin(): bool
    {
        return Auth::isAdmin();
    }

    /**
     * Kiểm tra quyền truy cập RBAC (ủy quyền cho Auth).
     *
     * @param string $module Tên module.
     * @param string $action Hành động.
     * @return bool True nếu có quyền.
     */
    public static function hasPermission(string $module, string $action): bool
    {
        return Auth::hasPermission($module, $action);
    }

    /**
     * Bảo vệ API với RBAC (ủy quyền cho Auth).
     *
     * @param string $module Tên module.
     * @param string $action Hành động.
     * @return void
     */
    public static function requirePermission(string $module, string $action): void
    {
        Auth::requirePermission($module, $action);
    }

    /**
     * Sinh CSRF token (ủy quyền cho Auth).
     *
     * @return string CSRF token.
     */
    public static function csrfToken(): string
    {
        return Auth::csrfToken();
    }

    /**
     * Kiểm tra CSRF token (ủy quyền cho Auth).
     *
     * @return void
     */
    public static function checkCsrf(): void
    {
        Auth::checkCsrf();
    }
}

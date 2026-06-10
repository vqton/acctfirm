<?php
namespace Accounting\Infrastructure\Database;

use Accounting\Domain\Contract\AuditLoggerInterface;

/**
 * Ghi nhật ký kiểm toán — lưu mọi thay đổi dữ liệu kế toán.
 *
 * Yêu cầu bắt buộc từ Kiểm toán độc lập và Kế toán trưởng.
 * Dữ liệu không được phép sửa/xóa (bất biến).
 * Lưu vào bảng audit_log với thông tin: action, resource, actor, old/new values, IP, request_id.
 */
class AuditLogger implements AuditLoggerInterface
{
    private ?\PDO $pdo;

    /**
     * @param \PDO|null $pdo Kết nối PDO. Nếu null, lấy từ $GLOBALS['container']['pdo'].
     */
    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? $GLOBALS['container']['pdo'] ?? null;
    }

    /**
     * Ghi lại một thay đổi dữ liệu.
     *
     * Bao gồm ai làm, làm gì, giá trị cũ/mới.
     * action format: {module}.{operation} (VD: journal.post, cash.receipt).
     * Yêu cầu Kiểm toán: mọi thay đổi số dư tài khoản, tạo/xóa bút toán, đóng kỳ phải có audit trail.
     * Dữ liệu audit KHÔNG được phép sửa/xóa — chỉ INSERT, không UPDATE/DELETE.
     * Nếu PDO null → skip log (tránh crash khi DB tạm thời offline).
     *
     * @param string $action Hành động (VD: journal.post, cash.receipt).
     * @param string $resourceType Loại tài nguyên (VD: transaction, account).
     * @param string|null $resourceId ID tài nguyên.
     * @param array|null $oldValues Giá trị cũ (dạng JSON).
     * @param array|null $newValues Giá trị mới (dạng JSON).
     * @param string|null $actorId ID người thực hiện.
     * @param string|null $actorEmail Email người thực hiện.
     * @return void
     */
    public function log(
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $actorId = null,
        ?string $actorEmail = null
    ): void {
        $pdo = $this->pdo;
        if (!$pdo) return;

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $requestId = $GLOBALS['request_id'] ?? null;

        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (action, resource_type, resource_id, actor_id, actor_email, old_values, new_values, ip_address, request_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $action,
            $resourceType,
            $resourceId,
            $actorId,
            $actorEmail,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $ip,
            $requestId,
        ]);
    }
}

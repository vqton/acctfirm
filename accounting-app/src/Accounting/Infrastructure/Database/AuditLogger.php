<?php
namespace Accounting\Infrastructure\Database;

use Accounting\Domain\Contract\AuditLoggerInterface;

// Ghi nhật ký kiểm toán — lưu mọi thay đổi dữ liệu kế toán
// Yêu cầu bắt buộc từ Kiểm toán độc lập và Kế toán trưởng
// Dữ liệu không được phép sửa/xóa (bất biến)
class AuditLogger implements AuditLoggerInterface
{
    private ?\PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? $GLOBALS['container']['pdo'] ?? null;
    }

    // Ghi lại một thay đổi dữ liệu — bao gồm ai làm, làm gì, giá trị cũ/mới
    // action format: {module}.{operation} (VD: journal.post, cash.receipt)
    // Yêu cầu Kiểm toán: mọi thay đổi số dư tài khoản, tạo/xóa bút toán, đóng kỳ phải có audit trail
    // Dữ liệu audit KHÔNG được phép sửa/xóa — chỉ INSERT, không UPDATE/DELETE
    // oldValues/newValues lưu dạng JSON — có thể truy vấn bằng JSON_CONTAINS trong MySQL
    // Nếu PDO null → skip log (tránh crash khi DB tạm thời offline)
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

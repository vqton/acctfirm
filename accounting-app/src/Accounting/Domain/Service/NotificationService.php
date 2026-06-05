<?php
//
// NGHIỆP VỤ: Service quản lý thông báo trong ứng dụng (R-12)
//
// Workflow:
//   1. Service khác (Journal, Period, Import) gọi notify() khi có sự kiện
//   2. notify() tạo row trong bảng notifications (id, user_id, type, severity, ...)
//   3. User xem qua API /api/notifications
//   4. markRead / markAllRead cập nhật is_read = 1
//
// Quy ước:
//   - type: {module}.{action} (vd: journal.pending_approval, period.deadline_soon)
//   - severity: info (thường), warn (cần chú ý), critical (cần hành động ngay)
//   - resource_id: optional — link tới resource cụ thể
//   - link: optional URL
//
namespace Accounting\Domain\Service;

class NotificationService
{
    private \PDO $pdo;
    private ?object $auditLogger;

    public function __construct(\PDO $pdo, ?object $auditLogger = null)
    {
        $this->pdo = $pdo;
        $this->auditLogger = $auditLogger;
    }

    //
    // Tạo thông báo mới (idempotent với unique_type_resource_user: tránh spam)
    //
    // Input:
    //   type:        'journal.pending_approval'
    //   severity:    'info'|'warn'|'critical'
    //   title:       'Bút toán mới cần duyệt'
    //   message:     'Kế toán A vừa submit PC-2025-001'
    //   userId:      null = broadcast, string = user cụ thể
    //   resource:    { type: 'transaction', id: 'jrn_abc' }
    //   link:        '/journal/edit/jrn_abc'
    //   createdBy:   'system' | user
    //
    public function notify(
        string $type,
        string $title,
        string $message,
        ?string $userId = null,
        string $severity = 'info',
        ?array $resource = null,
        ?string $link = null,
        ?string $createdBy = null
    ): string {
        // Idempotency check: không tạo duplicate nếu đã có notification cùng
        // type + resource_id + user_id trong 5 phút gần nhất
        if ($resource) {
            $checkSql = "SELECT id FROM notifications
                         WHERE type = ? AND resource_id = ? AND (user_id = ? OR (user_id IS NULL AND ? IS NULL))
                         AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) LIMIT 1";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$type, $resource['id'] ?? '', $userId, $userId]);
            if ($checkStmt->fetchColumn()) {
                return ''; // Skip duplicate
            }
        }

        $id = 'ntf_' . substr(str_replace('.', '', uniqid('', true)), 0, 15);
        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications (id, user_id, type, severity, title, message,
                resource_type, resource_id, link, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $id, $userId, $type, $severity, $title, $message,
            $resource['type'] ?? null, $resource['id'] ?? null,
            $link, $createdBy,
        ]);
        return $id;
    }

    //
    // Lấy danh sách thông báo của user (gồm cả broadcast user_id IS NULL)
    // Mặc định 50 thông báo mới nhất
    //
    public function listForUser(string $userId, int $limit = 50, bool $unreadOnly = false): array
    {
        $sql = "SELECT * FROM notifications
                WHERE (user_id = ? OR user_id IS NULL)";
        $params = [$userId];
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    //
    // Đếm số thông báo chưa đọc
    //
    public function countUnread(string $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM notifications
             WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0"
        );
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    //
    // Đánh dấu 1 thông báo đã đọc
    //
    public function markRead(string $notificationId, string $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE id = ? AND (user_id = ? OR user_id IS NULL) AND is_read = 0"
        );
        $stmt->execute([$notificationId, $userId]);
        return $stmt->rowCount() > 0;
    }

    //
    // Đánh dấu tất cả đã đọc
    //
    public function markAllRead(string $userId): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0"
        );
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }

    //
    // Helper: thông báo bút toán cần duyệt (gọi từ JournalService::submitEntry)
    // Broadcast cho tất cả KTT/admin
    //
    public function notifyPendingApproval(
        string $txnId, string $submittedBy, string $description, float $amount, ?string $targetRole = null
    ): string {
        return $this->notify(
            type: 'journal.pending_approval',
            title: 'Bút toán mới cần duyệt',
            message: sprintf('%s vừa submit bút toán "%s" (%.0f VND)', $submittedBy, $description, $amount),
            userId: null, // broadcast
            severity: 'info',
            resource: ['type' => 'transaction', 'id' => $txnId],
            link: "/ghi-so/edit/{$txnId}",
            createdBy: $submittedBy
        );
    }

    //
    // Helper: thông báo kết quả duyệt (gọi từ JournalService::approve/reject)
    // Gửi cho người tạo bút toán
    //
    public function notifyApprovalResult(
        string $txnId, string $creator, bool $approved, string $reviewer, ?string $reason = null
    ): string {
        $title = $approved ? 'Bút toán được duyệt' : 'Bút toán bị từ chối';
        $message = $approved
            ? sprintf('KTT %s đã duyệt bút toán của bạn', $reviewer)
            : sprintf('KTT %s đã từ chối bút toán: %s', $reviewer, $reason ?? '(không có lý do)');
        return $this->notify(
            type: $approved ? 'journal.approved' : 'journal.rejected',
            title: $title,
            message: $message,
            userId: $creator,
            severity: $approved ? 'info' : 'warn',
            resource: ['type' => 'transaction', 'id' => $txnId],
            link: "/ghi-so/edit/{$txnId}",
            createdBy: $reviewer
        );
    }

    //
    // Helper: thông báo kỳ kế toán sắp đóng
    // Có thể gọi từ cron hàng ngày
    //
    public function notifyPeriodDeadline(
        string $periodCode, string $deadline, int $daysLeft
    ): string {
        $severity = $daysLeft <= 1 ? 'critical' : ($daysLeft <= 3 ? 'warn' : 'info');
        $title = $daysLeft <= 0 ? 'Kỳ kế toán đã quá hạn' : "Kỳ kế toán {$periodCode} sắp đóng";
        $message = $daysLeft <= 0
            ? "Kỳ {$periodCode} đã quá deadline {$deadline}. Cần hành động ngay."
            : "Kỳ {$periodCode} còn {$daysLeft} ngày nữa sẽ đóng (deadline: {$deadline})";
        return $this->notify(
            type: 'period.deadline_soon',
            title: $title,
            message: $message,
            userId: null, // broadcast
            severity: $severity,
            resource: ['type' => 'period', 'id' => $periodCode],
            link: "/he-thong/quan-ly-ky",
            createdBy: 'system'
        );
    }

    //
    // Helper: thông báo import hoàn tất / thất bại
    //
    public function notifyImportResult(
        string $entityType, string $fileName, int $rowCount, bool $success, ?string $error = null
    ): string {
        $title = $success ? 'Import dữ liệu thành công' : 'Import dữ liệu thất bại';
        $message = $success
            ? sprintf('Đã import %d dòng %s từ file %s', $rowCount, $entityType, $fileName)
            : sprintf('Import %s từ %s thất bại: %s', $entityType, $fileName, $error ?? 'lỗi không xác định');
        return $this->notify(
            type: $success ? 'import.completed' : 'import.failed',
            title: $title,
            message: $message,
            userId: null, // broadcast
            severity: $success ? 'info' : 'critical',
            resource: ['type' => 'import', 'id' => $entityType],
            link: $success ? "/api/import/batches" : null,
            createdBy: 'system'
        );
    }
}

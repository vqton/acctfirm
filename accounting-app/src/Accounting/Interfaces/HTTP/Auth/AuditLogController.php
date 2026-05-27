<?php
namespace Accounting\Interfaces\HTTP\Auth;

use Accounting\Infrastructure\Helpers;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Kiểm toán (Audit Log)
 *
 * Mục đích nghiệp vụ:
 *   - Tra cứu nhật ký kiểm toán (audit_log) — ghi lại mọi thay đổi dữ liệu quan trọng
 *   - Hỗ trợ lọc theo action, resource, actor, khoảng thời gian
 *   - Phân trang kết quả để xem lịch sử thao tác
 *
 * API endpoints:
 *   GET /api/audit-logs — Danh sách audit log (có filter & phân trang)
 *   GET /api/audit-logs/{id} — Chi tiết một audit log
 *
 * Rủi ro:
 *   - Audit log là bất biến (FORBIDDEN: không được sửa/xóa)
 *   - Dữ liệu audit log có thể rất lớn, cần index và phân trang
 *   - Chỉ người có quyền audit mới được xem (kiểm toán viên, kế toán trưởng)
 *
 * Tích hợp:
 *   - AuditLogger ghi log từ mọi service method
 *   - ActionJournal ghi mọi HTTP request (riêng biệt, file .jsonl)
 *   - Dùng chung bảng audit_log với tất cả module
 */
class AuditLogController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function list(): void
    {
        $action = $_GET['action'] ?? '';
        $resource = $_GET['resource_type'] ?? '';
        $actor = $_GET['actor_id'] ?? '';
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));

        $where = [];
        $params = [];
        if ($action) { $where[] = 'action = ?'; $params[] = $action; }
        if ($resource) { $where[] = 'resource_type = ?'; $params[] = $resource; }
        if ($actor) { $where[] = 'actor_id = ?'; $params[] = $actor; }
        if ($from) { $where[] = 'created_at >= ?'; $params[] = $from; }
        if ($to) { $where[] = 'created_at <= ?'; $params[] = $to . ' 23:59:59'; }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $result = Helpers::paginate(
            $this->pdo,
            "SELECT COUNT(*) FROM audit_log {$whereClause}",
            "SELECT * FROM audit_log {$whereClause} ORDER BY id DESC",
            $params,
            $page
        );

        JsonResponse::ok($result);
    }

    public function get(string $id): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audit_log WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { JsonResponse::error('Không tìm thấy bản ghi nhật ký', 404); return; }
        JsonResponse::ok($row);
    }
}

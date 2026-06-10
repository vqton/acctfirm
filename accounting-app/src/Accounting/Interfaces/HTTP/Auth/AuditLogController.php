<?php
namespace Accounting\Interfaces\HTTP\Auth;

use Accounting\Domain\Service\AuditLogService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Audit Log — Xem lịch sử thay đổi
 *
 * Mục đích nghiệp vụ:
 *   - Xem lịch sử audit log cho mọi thay đổi dữ liệu
 *   - Filter theo action, resource, actor, thời gian
 *   - Hỗ trợ kiểm toán nội bộ và truy vết
 *
 * API endpoints:
 *   GET /api/audit/logs — Danh sách audit logs
 *   GET /api/audit/logs/{id} — Chi tiết audit log
 *
 * Rủi ro:
 *   - Audit log bất biến — không sửa/xoá được
 *   - Query nặng nếu không index
 *
 * Tích hợp:
 *   - AuditLogService đọc từ bảng audit_log
 *   - Yêu cầu quyền admin
 */
class AuditLogController
{
    private AuditLogService $auditLog;

    public function __construct(AuditLogService $auditLog) { $this->auditLog = $auditLog; }

    /**
     * Danh sách audit logs
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('admin', 'read');
        $filters = [
            'action' => $_GET['action'] ?? null,
            'resource' => $_GET['resource'] ?? null,
            'actor' => $_GET['actor'] ?? null,
            'from' => $_GET['from'] ?? null,
            'to' => $_GET['to'] ?? null,
            'limit' => (int)($_GET['limit'] ?? 100),
        ];
        JsonResponse::ok($this->auditLog->getLogs($filters));
    }

    /**
     * Chi tiết audit log
     *
     * @param string $id ID audit log
     * @return void
     */
    public function get(string $id): void
    {
        Auth::requirePermission('admin', 'read');
        $log = $this->auditLog->getLog($id);
        if (!$log) { JsonResponse::error('Không tìm thấy audit log', 404); return; }
        JsonResponse::ok($log);
    }
}

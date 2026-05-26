<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\ApprovalRoutingService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Phê duyệt Bút toán (Approval Workflow)
 *
 * Mục đích nghiệp vụ:
 *   - Hiển thị danh sách bút toán chờ phê duyệt (status = submitted)
 *   - Phê duyệt (approve) bút toán — chuyển trạng thái từ submitted → posted
 *   - Từ chối (reject) bút toán — chuyển trạng thái từ submitted → draft
 *   - Approval routing: chỉ người có quyền mới được approve theo từng loại
 *
 * API endpoints:
 *   GET  /api/approvals/pending    — Danh sách chờ duyệt
 *   POST /api/approvals/{id}/approve — Phê duyệt
 *   POST /api/approvals/{id}/reject  — Từ chối
 *
 * Rủi ro:
 *   - R007: Approve bút toán sai → ghi nhận sai, khó sửa (đã post)
 *   - Phê duyệt không đúng thẩm quyền → kiểm soát nội bộ yếu
 *   - Multi-level approval cần routing theo role và giá trị
 *
 * Tích hợp:
 *   - JournalService.approveEntry thực hiện phê duyệt
 *   - Auth::requirePermission('journal', 'approve') kiểm tra quyền
 *   - AuditLogger ghi lại mọi phê duyệt/từ chối
 */
class ApprovalController
{
    private JournalService $journalService;
    private \PDO $pdo;
    private ApprovalRoutingService $routingService;

    public function __construct(JournalService $journalService, \PDO $pdo, ApprovalRoutingService $routingService)
    {
        $this->journalService = $journalService;
        $this->pdo = $pdo;
        $this->routingService = $routingService;
    }

    public function getPending(): void
    {
        Auth::requirePermission('journal', 'approve');
        $userId = $_SESSION['user']['username'] ?? '';
        $role = $_SESSION['user']['role'] ?? '';

        $stmt = $this->pdo->prepare("
            SELECT t.id, t.date, t.description, t.reference, t.status, t.created_by, t.created_at
            FROM transactions t
            WHERE t.status = 'submitted'
            ORDER BY t.created_at DESC
            LIMIT 50
        ");
        $stmt->execute();
        $txns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $pending = [];
        foreach ($txns as $txn) {
            if ($this->userCanApprove($txn['id'], $role)) {
                $pending[] = $txn;
            }
        }

        JsonResponse::ok($pending);
    }

    // NGHIỆP VỤ: Phê duyệt bút toán — chuyển status từ submitted → posted
    // Input: { comment?: string }
    // Output: { id, status } — 200 OK
    // Service: JournalService::approveEntry() — gọi PostingRuleService + AuditLogger
    // Transaction: JournalService tự wrap transaction
    // Permission: journal, approve
    // Rủi ro: R007 — sau khi approve, bút toán không sửa/xóa được
    // Ràng buộc: Chỉ approve bút toán status=submitted. Kiểm tra period open trước khi post.
    public function approve(string $txnId): void
    {
        Auth::requirePermission('journal', 'approve');
        $userId = $_SESSION['user']['username'] ?? '';
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $comment = $input['comment'] ?? null;

        $txn = $this->journalService->approveEntry($txnId, $userId, $comment);
        JsonResponse::ok(['id' => $txn->getId(), 'status' => $txn->getStatus()]);
    }

    // NGHIỆP VỤ: Từ chối bút toán — chuyển status từ submitted → draft
    // Input: { reason?: string }
    // Output: { id, status } — 200 OK
    // Service: JournalService::rejectEntry() — đưa về draft để sửa
    // Permission: journal, approve
    // Rủi ro: Sau reject, kế toán viên cần sửa và gửi lại (resubmit)
    public function reject(string $txnId): void
    {
        Auth::requirePermission('journal', 'approve');
        $userId = $_SESSION['user']['username'] ?? '';
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = $input['reason'] ?? 'No reason provided';

        $txn = $this->journalService->rejectEntry($txnId, $userId, $reason);
        JsonResponse::ok(['id' => $txn->getId(), 'status' => $txn->getStatus()]);
    }

    public function history(string $txnId): void
    {
        Auth::requirePermission('journal', 'read');
        $stmt = $this->pdo->prepare(
            'SELECT id, action, actor, comment, created_at FROM journal_entry_approvals WHERE transaction_id = ? ORDER BY created_at'
        );
        $stmt->execute([$txnId]);
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function routing(): void
    {
        Auth::requirePermission('journal', 'read');
        $amount = (float)($_GET['amount'] ?? 0);
        $module = $_GET['module'] ?? null;
        $roles = $this->routingService->getRequiredRoles($amount, $module);
        JsonResponse::ok(['required_roles' => $roles]);
    }

    // NGHIỆP VỤ: Kiểm tra quyền phê duyệt dựa trên giá trị bút toán và role
    // Process: Tính tổng tiền bút toán → gọi ApprovalRoutingService.getRequiredRoles()
    // Multi-level approval: Bút toán giá trị lớn cần role cao hơn duyệt
    // Audit trail: Mọi approve/reject được AuditLogger ghi lại
    private function userCanApprove(string $txnId, string $userRole): bool
    {
        // Compute total amount
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total FROM ledger_entries WHERE transaction_id = ? AND is_debit = 1
        ");
        $stmt->execute([$txnId]);
        $total = (float)$stmt->fetchColumn();

        $roles = $this->routingService->getRequiredRoles($total);
        return in_array($userRole, $roles, true);
    }
}

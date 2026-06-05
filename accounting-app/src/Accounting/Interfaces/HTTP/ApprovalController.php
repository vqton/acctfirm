<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\ApprovalRoutingService;
use Accounting\Domain\Contract\JournalServiceInterface;
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
    private JournalServiceInterface $journalService;
    private \PDO $pdo;
    private ApprovalRoutingService $routingService;

    public function __construct(JournalServiceInterface $journalService, \PDO $pdo, ApprovalRoutingService $routingService)
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
            SELECT t.id, t.date, t.description, t.reference, t.status, t.created_by, t.created_at, t.source_module
            FROM transactions t
            WHERE t.status = 'submitted'
            ORDER BY t.created_at DESC
            LIMIT 50
        ");
        $stmt->execute();
        $txns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $pending = [];
        foreach ($txns as $txn) {
            $info = $this->userCanApprove($txn['id'], $role);
            if ($info['can_approve']) {
                $txn['approval_level'] = $info['current_level'];
                $txn['required_levels'] = $info['required_count'];
                $txn['required_role_current'] = $info['required_role_current'] ?? null;
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
    //
    // R-17: Nếu user không có role khớp current level → check delegation
    //       Audit ghi cả actual_approver (delegate) lẫn on_behalf_of (delegator)
    public function approve(string $txnId): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('journal', 'approve');
        $userId = $_SESSION['user']['username'] ?? '';
        $userRole = $_SESSION['user']['role'] ?? '';
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $comment = $input['comment'] ?? null;

        // R-17: check delegation nếu user không có role khớp
        $requiredSteps = $this->routingService->getRequiredApprovalSteps($this->txnTotal($txnId));
        $currentLevel = $this->routingService->getCurrentApprovalLevel($txnId, $requiredSteps);
        $requiredRoleCurrent = $currentLevel <= count($requiredSteps) ? ($requiredSteps[$currentLevel - 1] ?? null) : null;
        $onBehalfOf = null;

        if ($requiredRoleCurrent !== null && $userRole !== $requiredRoleCurrent) {
            // Check delegation: user có được ủy quyền cho requiredRoleCurrent không?
            $delegations = $this->routingService->findActiveDelegationsFor($userId, $requiredRoleCurrent);
            if (empty($delegations)) {
                JsonResponse::error("Bạn không có quyền duyệt cấp {$currentLevel} (cần role {$requiredRoleCurrent})", 403);
                return;
            }
            $onBehalfOf = $delegations[0]['delegator_id'];
        }

        $txn = $this->journalService->approveEntry($txnId, $userId, $comment);
        $isFinal = $currentLevel > count($requiredSteps);
        $resp = [
            'id' => $txn->getId(),
            'status' => $txn->getStatus(),
            'approval_level' => $isFinal ? count($requiredSteps) : $currentLevel,
            'required_levels' => count($requiredSteps),
            'fully_approved' => $isFinal,
        ];
        if ($onBehalfOf !== null) {
            $resp['on_behalf_of'] = $onBehalfOf;
            $resp['delegated'] = true;
            // Ghi audit bổ sung cho delegation
            $this->pdo->prepare(
                "INSERT INTO journal_entry_approvals (transaction_id, action, approval_level, actor, comment)
                 VALUES (?, 'delegate_approve', ?, ?, ?)"
            )->execute([$txnId, $currentLevel, $userId, "On behalf of: {$onBehalfOf}"]);
        }
        JsonResponse::ok($resp);
    }

    private function txnTotal(string $txnId): float
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM ledger_entries WHERE transaction_id = ? AND is_debit = 1");
        $stmt->execute([$txnId]);
        return (float)$stmt->fetchColumn();
    }

    // R-17: Quản lý delegation
    public function listDelegations(string $userId): void
    {
        Auth::requirePermission('journal', 'read');
        $rows = $this->routingService->listDelegations($userId);
        JsonResponse::ok($rows);
    }

    public function createDelegation(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('journal', 'approve');
        $userId = $_SESSION['user']['username'] ?? '';
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $delegateId = $body['delegate_id'] ?? null;
        $role = $body['role'] ?? null;
        $startDate = $body['start_date'] ?? null;
        $endDate = $body['end_date'] ?? null;
        $reason = $body['reason'] ?? null;
        if (!$delegateId || !$role || !$startDate || !$endDate) {
            JsonResponse::error("Thiếu trường bắt buộc: delegate_id, role, start_date, end_date", 400);
            return;
        }
        try {
            $id = $this->routingService->createDelegation(
                $userId, $delegateId, $role, $startDate, $endDate, $reason, $userId
            );
            JsonResponse::ok(['id' => $id, 'message' => 'Đã tạo ủy quyền']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function revokeDelegation(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('journal', 'approve');
        $userId = $_SESSION['user']['username'] ?? '';
        $ok = $this->routingService->revokeDelegation($id, $userId);
        if (!$ok) {
            JsonResponse::error("Không tìm thấy ủy quyền hoặc đã bị hủy", 404);
            return;
        }
        JsonResponse::ok(['message' => 'Đã hủy ủy quyền']);
    }

    // NGHIỆP VỤ: Từ chối bút toán — chuyển status từ submitted → draft
    // Input: { reason?: string }
    // Output: { id, status } — 200 OK
    // Service: JournalService::rejectEntry() — đưa về draft để sửa
    // Permission: journal, approve
    // Rủi ro: Sau reject, kế toán viên cần sửa và gửi lại (resubmit)
    public function reject(string $txnId): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('journal', 'approve');
        $userId = $_SESSION['user']['username'] ?? '';
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = $input['reason'] ?? 'Không có lý do từ chối';

        $txn = $this->journalService->rejectEntry($txnId, $userId, $reason);
        JsonResponse::ok(['id' => $txn->getId(), 'status' => $txn->getStatus()]);
    }

    public function history(string $txnId): void
    {
        Auth::requirePermission('journal', 'read');
        $stmt = $this->pdo->prepare(
            'SELECT id, action, approval_level, actor, comment, created_at FROM journal_entry_approvals WHERE transaction_id = ? ORDER BY created_at, approval_level'
        );
        $stmt->execute([$txnId]);
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function routing(): void
    {
        Auth::requirePermission('journal', 'read');
        $amount = (float)($_GET['amount'] ?? 0);
        $module = $_GET['module'] ?? null;
        $steps = $this->routingService->getRequiredApprovalSteps($amount, $module);
        JsonResponse::ok(['required_steps' => $steps, 'count' => count($steps)]);
    }

    // NGHIỆP VỤ: Kiểm tra quyền phê duyệt dựa trên giá trị bút toán và role
    // R-16 Multi-level: user chỉ duyệt được nếu role của họ khớp với cấp hiện tại
    // (không phải bất kỳ cấp nào trong sequence)
    // Trả về: can_approve + current_level + required_count + required_role_current
    private function userCanApprove(string $txnId, string $userRole): array
    {
        $total = $this->txnTotal($txnId);
        $steps = $this->routingService->getRequiredApprovalSteps($total);
        $currentLevel = $this->routingService->getCurrentApprovalLevel($txnId, $steps);
        $requiredRoleCurrent = $currentLevel <= count($steps) ? ($steps[$currentLevel - 1] ?? null) : null;
        $canApprove = $requiredRoleCurrent !== null && $userRole === $requiredRoleCurrent;
        return [
            'can_approve' => $canApprove,
            'current_level' => $currentLevel,
            'required_count' => count($steps),
            'required_role_current' => $requiredRoleCurrent,
        ];
    }
}

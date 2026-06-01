<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\DebtCollectionService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Thu hồi Công nợ (Debt Collection)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý hàng đợi đòi nợ tự động
 *   - Ghi nhận hoạt động đòi nợ (gọi điện, email, meeting)
 *   - Theo dõi cam kết thanh toán của khách hàng
 *   - Phê duyệt xóa nợ phải thu khó đòi
 *   - Quản lý thỏa thuận thanh toán (settlement)
 *
 * API endpoints:
 *   GET    /api/debt-collection/queue            — Danh sách queue
 *   GET    /api/debt-collection/queue/:id        — Chi tiết queue
 *   POST   /api/debt-collection/queue/generate   — Sinh queue entries
 *   PUT    /api/debt-collection/queue/:id/assign — Phân công collector
 *   PUT    /api/debt-collection/queue/:id/hold   — Tạm dừng
 *   PUT    /api/debt-collection/queue/:id/release— Tiếp tục
 *   PUT    /api/debt-collection/queue/:id/priority—Cập nhật ưu tiên
 *   POST   /api/debt-collection/queue/:id/activities        — Tạo activity
 *   GET    /api/debt-collection/queue/:id/activities        — List activities
 *   POST   /api/debt-collection/queue/:id/promises          — Tạo promise
 *   GET    /api/debt-collection/queue/:id/promises          — List promises
 *   POST   /api/debt-collection/queue/:id/propose-writeoff  — Đề xuất xóa nợ
 *   POST   /api/debt-collection/promises/:id/keep           — Giữ lời hứa
 *   POST   /api/debt-collection/promises/:id/break          — Vỡ lời hứa
 *   GET    /api/debt-collection/approvals                   — Pending approvals
 *   PUT    /api/debt-collection/approvals/:id/approve       — Duyệt
 *   PUT    /api/debt-collection/approvals/:id/reject        — Từ chối
 *   POST   /api/debt-collection/settlements                 — Tạo settlement
 *   POST   /api/debt-collection/settlements/:id/pay         — Thanh toán settlement
 *   GET    /api/debt-collection/stats                       — Thống kê
 *   GET    /api/debt-collection/stats/collector/:id         — Stats collector
 */
class DebtCollectionController
{
    private DebtCollectionService $dcs;

    public function __construct(DebtCollectionService $dcs) { $this->dcs = $dcs; }

    // ── Queue ──

    public function queueList(): void
    {
        $filters = [];
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['collector'])) $filters['assigned_to'] = $_GET['collector'];
        if (!empty($_GET['customer_id'])) $filters['customer_id'] = $_GET['customer_id'];
        if (!empty($_GET['unassigned'])) $filters['unassigned'] = true;
        JsonResponse::ok($this->dcs->listQueues($filters));
    }

    public function queueDetail(int $id): void
    {
        $data = $this->dcs->getQueue($id);
        if (!$data) { JsonResponse::error('Không tìm thấy queue entry.', 404); return; }
        JsonResponse::ok($data);
    }

    public function queueGenerate(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('debt_collection', 'create');
        $d = json_decode(file_get_contents('php://input'), true);
        try {
            $result = $this->dcs->generateQueueEntries($d['created_by'] ?? 'system');
            JsonResponse::ok(['created' => count($result), 'entries' => $result], 201);
        } catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function queueAssign(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('debt_collection', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || empty($d['collector_id'])) { JsonResponse::error('Vui lòng nhập mã nhân viên.'); return; }
        try { JsonResponse::ok($this->dcs->assignQueue($id, $d['collector_id'], $d['assigned_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function queueHold(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('debt_collection', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || empty($d['reason'])) { JsonResponse::error('Vui lòng nhập lý do tạm dừng.'); return; }
        try { JsonResponse::ok($this->dcs->holdQueue($id, $d['reason'], $d['hold_until'] ?? null, $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function queueRelease(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('debt_collection', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->dcs->releaseQueue($id, $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function queuePriority(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('debt_collection', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || !isset($d['priority'])) { JsonResponse::error('Vui lòng nhập priority.'); return; }
        try { JsonResponse::ok($this->dcs->updatePriority($id, (int)$d['priority'], $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    // ── Activities ──

    public function activityList(int $queueId): void
    {
        try {
            $q = $this->dcs->getQueue($queueId);
            if (!$q) { JsonResponse::error('Không tìm thấy queue entry.', 404); return; }
            JsonResponse::ok($q['activities']);
        } catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function activityCreate(int $queueId): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('debt_collection', 'create');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || empty($d['activity_type']) || empty($d['summary'])) {
            JsonResponse::error('Vui lòng nhập loại hoạt động và nội dung tóm tắt.'); return;
        }
        try {
            $result = $this->dcs->logActivity($queueId, $d['activity_type'], $d['summary'], $d['created_by'] ?? 'system', $d);
            JsonResponse::ok($result, 201);
        } catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    // ── Promises ──

    public function promiseList(int $queueId): void
    {
        try {
            $q = $this->dcs->getQueue($queueId);
            if (!$q) { JsonResponse::error('Không tìm thấy queue entry.', 404); return; }
            JsonResponse::ok($q['promises']);
        } catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function promiseCreate(int $queueId): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('debt_collection', 'create');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || empty($d['promise_date']) || !isset($d['promise_amount'])) {
            JsonResponse::error('Vui lòng nhập ngày cam kết và số tiền cam kết.'); return;
        }
        try {
            $result = $this->dcs->createPromise($queueId, $d['promise_date'], (float)$d['promise_amount'],
                $d['created_by'] ?? 'system', isset($d['activity_id']) ? (int)$d['activity_id'] : null, $d['note'] ?? null);
            JsonResponse::ok($result, 201);
        } catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function promiseKeep(int $promiseId): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('debt_collection', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->dcs->keepPromise($promiseId, $d['kept_by'] ?? 'system', $d['payment_date'] ?? null)); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function promiseBreak(int $promiseId): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('debt_collection', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || empty($d['reason'])) { JsonResponse::error('Vui lòng nhập lý do cam kết bị vỡ.'); return; }
        try { JsonResponse::ok($this->dcs->breakPromise($promiseId, $d['reason'], $d['broken_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    // ── Write-off / Approvals ──

    public function proposeWriteOff(int $queueId): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('debt_collection', 'create');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || empty($d['note'])) { JsonResponse::error('Vui lòng nhập lý do đề xuất xóa nợ.'); return; }
        try { JsonResponse::ok($this->dcs->proposeWriteOff($queueId, $d['requested_by'] ?? 'system', $d['note']), 201); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function approvalList(): void
    {
        $approverId = $_GET['approver_id'] ?? null;
        JsonResponse::ok($this->dcs->getPendingApprovals($approverId));
    }

    public function approvalApprove(int $approvalId): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('debt_collection', 'approve_writeoff');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->dcs->approveWriteOff($approvalId, $d['approver_id'] ?? 'system', 'approved', $d['note'] ?? null)); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function approvalReject(int $approvalId): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('debt_collection', 'approve_writeoff');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || empty($d['note'])) { JsonResponse::error('Vui lòng nhập lý do từ chối.'); return; }
        try { JsonResponse::ok($this->dcs->approveWriteOff($approvalId, $d['approver_id'] ?? 'system', 'rejected', $d['note'])); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    // ── Settlements ──

    public function settlementCreate(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('debt_collection', 'create');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || !isset($d['queue_id'], $d['settlement_amount'], $d['agreement_date'], $d['due_by_date'])) {
            JsonResponse::error('Vui lòng nhập đầy đủ thông tin thỏa thuận.'); return;
        }
        try {
            $result = $this->dcs->createSettlement((int)$d['queue_id'], (float)$d['settlement_amount'],
                $d['agreement_date'], $d['due_by_date'], $d['created_by'] ?? 'system',
                isset($d['approval_id']) ? (int)$d['approval_id'] : null, $d['payment_type'] ?? 'lump_sum');
            JsonResponse::ok($result, 201);
        } catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function settlementPay(int $settlementId): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('debt_collection', 'create');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || !isset($d['amount'])) { JsonResponse::error('Vui lòng nhập số tiền thanh toán.'); return; }
        try {
            JsonResponse::ok($this->dcs->recordSettlementPayment($settlementId, (float)$d['amount'],
                $d['payment_date'] ?? date('Y-m-d'), $d['created_by'] ?? 'system'));
        } catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    // ── Stats ──

    public function stats(): void
    {
        JsonResponse::ok($this->dcs->getQueueStats());
    }

    public function collectorStats(string $collectorId): void
    {
        JsonResponse::ok($this->dcs->getCollectorStats($collectorId));
    }

    // ── Views ──

    public function viewDashboard(): void { require __DIR__ . '/../../../../../public/views/debt_collection_dashboard.php'; }
    public function viewQueue(): void { require __DIR__ . '/../../../../../public/views/debt_collection_queue.php'; }
    public function viewApprovals(): void { require __DIR__ . '/../../../../../public/views/debt_collection_approvals.php'; }
}

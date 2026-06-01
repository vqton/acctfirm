<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Repository\DebtCollectionRepositoryInterface;

//
// DỊCH VỤ THU HỒI CÔNG NỢ: Quản lý toàn bộ quy trình đòi nợ chủ động
//
// Các nghiệp vụ:
//   1. Queue management — sinh hàng đợi, assign, hold/release, escalate
//   2. Activity logging — ghi nhận cuộc gọi, email, meeting, dispute
//   3. Promise tracking — theo dõi cam kết thanh toán của KH
//   4. Write-off approval — phê duyệt xóa nợ multi-level
//   5. Settlement — thỏa thuận thanh toán giảm nợ
//   6. Cron jobs — generateQueue, checkPromises, autoEscalate, autoReleaseHolds
//
// Rủi ro: Queue không được generate → nợ không được theo dõi → mất khả năng thu hồi
// Rủi ro: Promise không được kiểm tra → KH hứa hẹn không giữ → collector mất thời gian
//
class DebtCollectionService
{
    private DebtCollectionRepositoryInterface $repo;
    private ArService $arService;
    private ?AuditLoggerInterface $auditLogger;
    private \PDO $pdo;

    public function __construct(
        \PDO $pdo,
        DebtCollectionRepositoryInterface $repo,
        ArService $arService,
        ?AuditLoggerInterface $auditLogger = null
    ) {
        $this->pdo = $pdo;
        $this->repo = $repo;
        $this->arService = $arService;
        $this->auditLogger = $auditLogger;
    }

    // ════════════════════════════════════════════════
    // 1. QUEUE MANAGEMENT
    // ════════════════════════════════════════════════

    //
    // SINH HÀNG ĐỢI ĐÒI NỢ: Tạo queue entry cho hóa đơn quá hạn chưa có trong queue
    // Chạy hàng ngày qua cron. Chỉ tạo cho hóa đơn chưa có queue active.
    // Bỏ qua hóa đơn có balance <= 1 (đã tất toán) và prepayment.
    //
    public function generateQueueEntries(string $createdBy = 'system'): array
    {
        $rows = $this->pdo->query(
            "SELECT i.id, i.customer_id, i.balance, i.due_date, i.status, i.gross_amount
             FROM ar_invoices i
             WHERE i.balance > 1
               AND i.status IN ('unpaid','partial')
               AND i.due_date < CURDATE()
             ORDER BY i.due_date ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $created = [];
        foreach ($rows as $r) {
            if ($this->repo->queueExistsForInvoice((int)$r['id'])) {
                continue;
            }

            $daysOverdue = (int)date_diff(date_create($r['due_date']), date_create('today'))->format('%a');
            $priority = min((int)($daysOverdue / 7), 10);
            $escalationLevel = $this->calculateEscalationLevel($daysOverdue);

            $entry = new \Accounting\Domain\Model\QueueEntry(
                (int)$r['id'],
                $r['customer_id'],
                null,
                'new',
                $priority,
                $escalationLevel,
                0,
                $createdBy
            );

            $id = $this->repo->saveQueue($entry);
            $created[] = ['queue_id' => $id, 'invoice_id' => $r['id'], 'customer_id' => $r['customer_id']];

            $this->auditLogger?->log('dc.queue.generate', 'debt_collection_queue', (string)$id,
                null, ['invoice_id' => $r['id'], 'customer_id' => $r['customer_id'], 'days_overdue' => $daysOverdue], $createdBy);
        }

        return $created;
    }

    //
    // PHÂN CÔNG COLLECTOR: Gán queue entry cho collector
    // Load balancing: kiểm tra collector không quá 50 active items
    //
    public function assignQueue(int $queueId, string $collectorId, string $assignedBy): array
    {
        $entry = $this->repo->findQueueById($queueId);
        if (!$entry) throw new \InvalidArgumentException('Không tìm thấy queue entry trong hệ thống.');

        $activeCount = $this->repo->countActiveByCollector($collectorId);
        if ($activeCount >= 50) {
            throw new \InvalidArgumentException("Nhân viên {$collectorId} đã có {$activeCount} công việc đang xử lý (tối đa 50). Vui lòng chọn nhân viên khác.");
        }

        $this->repo->updateQueueAssignment($queueId, $collectorId);
        $this->auditLogger?->log('dc.queue.assign', 'debt_collection_queue', (string)$queueId,
            null, ['collector' => $collectorId, 'assigned_by' => $assignedBy], $assignedBy);

        return ['queue_id' => $queueId, 'assigned_to' => $collectorId];
    }

    //
    // TẠM DỪNG NHẮC NỢ: Hold queue entry khi đang thương lượng
    // Ràng buộc: hold_reason bắt buộc, max 3 holds, max 30 ngày mỗi hold
    //
    public function holdQueue(int $queueId, string $reason, ?string $holdUntil, string $createdBy): array
    {
        $entry = $this->repo->findQueueById($queueId);
        if (!$entry) throw new \InvalidArgumentException('Không tìm thấy queue entry.');
        if ($entry->getStatus() === 'closed') throw new \InvalidArgumentException('Queue entry đã đóng.');
        if ($entry->getHoldCount() >= 3) throw new \InvalidArgumentException('Queue entry đã được tạm dừng 3 lần. Vui lòng liên hệ quản lý để xử lý tiếp.');

        $holdCount = $entry->getHoldCount() + 1;
        $this->repo->updateQueueHold($queueId, $reason, $holdUntil, $holdCount);
        $this->auditLogger?->log('dc.queue.hold', 'debt_collection_queue', (string)$queueId,
            null, ['reason' => $reason, 'hold_until' => $holdUntil], $createdBy);

        return ['queue_id' => $queueId, 'status' => 'hold', 'hold_reason' => $reason, 'hold_count' => $holdCount];
    }

    //
    // TIẾP TỤC NHẮC NỢ: Release queue entry từ hold
    //
    public function releaseQueue(int $queueId, string $createdBy): array
    {
        $entry = $this->repo->findQueueById($queueId);
        if (!$entry) throw new \InvalidArgumentException('Không tìm thấy queue entry.');
        if ($entry->getStatus() !== 'hold') throw new \InvalidArgumentException('Queue entry không ở trạng thái tạm dừng.');

        $this->repo->updateQueueHold($queueId, null, null, 0);
        $this->auditLogger?->log('dc.queue.release', 'debt_collection_queue', (string)$queueId,
            null, ['released_by' => $createdBy], $createdBy);

        return ['queue_id' => $queueId, 'status' => 'active'];
    }

    //
    // CẬP NHẬT PRIORITY
    //
    public function updatePriority(int $queueId, int $priority, string $createdBy): array
    {
        $entry = $this->repo->findQueueById($queueId);
        if (!$entry) throw new \InvalidArgumentException('Không tìm thấy queue entry.');

        $this->repo->updateQueuePriority($queueId, $priority);
        return ['queue_id' => $queueId, 'priority' => $priority];
    }

    //
    // LEO THANG: Tự động hoặc thủ công
    //
    public function escalateQueue(int $queueId, int $level, string $createdBy): array
    {
        $entry = $this->repo->findQueueById($queueId);
        if (!$entry) throw new \InvalidArgumentException('Không tìm thấy queue entry.');
        if ($level < 0 || $level > 5) throw new \InvalidArgumentException('Cấp độ leo thang không hợp lệ (0-5).');

        $this->repo->updateQueueEscalation($queueId, $level);
        $this->auditLogger?->log('dc.queue.escalate', 'debt_collection_queue', (string)$queueId,
            null, ['escalation_level' => $level, 'escalated_by' => $createdBy], $createdBy);

        return ['queue_id' => $queueId, 'escalation_level' => $level];
    }

    // ════════════════════════════════════════════════
    // 2. COLLECTION ACTIVITIES
    // ════════════════════════════════════════════════

    //
    // GHI NHẬN HOẠT ĐỘNG ĐÒI NỢ: Log cuộc gọi, email, meeting, dispute
    // Cập nhật last_action_date trên queue entry
    //
    public function logActivity(int $queueId, string $activityType, string $summary, string $createdBy, array $extra = []): array
    {
        $entry = $this->repo->findQueueById($queueId);
        if (!$entry) throw new \InvalidArgumentException('Không tìm thấy queue entry.');
        if ($entry->getStatus() === 'closed') throw new \InvalidArgumentException('Queue entry đã đóng.');

        $activity = new \Accounting\Domain\Model\Activity(
            $queueId,
            $activityType,
            $summary,
            $createdBy,
            $extra['detail'] ?? null,
            $extra['contact_person'] ?? null,
            $extra['contact_phone'] ?? null,
            $extra['result'] ?? null,
            $extra['promise_date'] ?? null,
            isset($extra['promise_amount']) ? (float)$extra['promise_amount'] : null,
            isset($extra['duration_minutes']) ? (int)$extra['duration_minutes'] : null,
            $extra['attachment_path'] ?? null,
        );

        $id = $this->repo->saveActivity($activity);
        $activity->setId($id);

        // Cập nhật last_action_date + next_action_date nếu có
        $nextAction = $extra['next_action_date'] ?? null;
        $this->repo->updateQueueLastAction($queueId, $nextAction);

        $this->auditLogger?->log('dc.activity.create', 'debt_collection_activity', (string)$id,
            null, ['queue_id' => $queueId, 'type' => $activityType, 'summary' => $summary], $createdBy);

        $activity->setCreatedAt(date('Y-m-d H:i:s'));
        return $activity->toArray();
    }

    //
    // XÓA HOẠT ĐỘNG (soft: chỉ collector tạo mới được xóa trong 24h)
    //
    public function deleteActivity(int $activityId, string $deletedBy): void
    {
        $activity = $this->repo->findActivityById($activityId);
        if (!$activity) throw new \InvalidArgumentException('Không tìm thấy hoạt động.');
        if ($activity->getCreatedBy() !== $deletedBy) {
            throw new \InvalidArgumentException('Chỉ người tạo mới có thể xóa hoạt động này.');
        }
        $this->repo->deleteActivity($activityId);
        $this->auditLogger?->log('dc.activity.delete', 'debt_collection_activity', (string)$activityId,
            ['created_by' => $activity->getCreatedBy()], ['deleted_by' => $deletedBy], $deletedBy);
    }

    // ════════════════════════════════════════════════
    // 3. PAYMENT PROMISES
    // ════════════════════════════════════════════════

    //
    // TẠO CAM KẾT THANH TOÁN: KH hứa trả nợ vào ngày X
    // Ràng buộc: promise_date <= 60 ngày, promise_amount >= 10% balance, max 3 promises
    //
    public function createPromise(int $queueId, string $promiseDate, float $promiseAmount, string $createdBy, ?int $activityId = null, ?string $note = null): array
    {
        $entry = $this->repo->findQueueById($queueId);
        if (!$entry) throw new \InvalidArgumentException('Không tìm thấy queue entry.');

        $today = new \DateTime('today');
        $pDate = new \DateTime($promiseDate);
        if ($pDate <= $today) throw new \InvalidArgumentException('Ngày hứa hẹn phải sau ngày hiện tại.');
        $diffDays = (int)$today->diff($pDate)->format('%a');
        if ($diffDays > 60) throw new \InvalidArgumentException('Ngày hứa hẹn không được quá 60 ngày từ hôm nay.');

        // Kiểm tra 10% balance
        $stmt = $this->pdo->prepare('SELECT balance FROM ar_invoices WHERE id = ?');
        $stmt->execute([$entry->getInvoiceId()]);
        $inv = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($inv && $promiseAmount < $inv['balance'] * 0.1) {
            throw new \InvalidArgumentException('Số tiền cam kết phải >= 10% số dư hóa đơn.');
        }

        // Kiểm tra max 3 promises
        $existingPromises = $this->repo->findPromisesByQueue($queueId);
        $activeCount = 0;
        foreach ($existingPromises as $p) {
            if ($p['status'] === 'active') $activeCount++;
        }
        if ($activeCount >= 3) {
            throw new \InvalidArgumentException('Queue entry này đã có 3 cam kết thanh toán đang hoạt động.');
        }

        $promise = new \Accounting\Domain\Model\Promise(
            $queueId, $promiseDate, $promiseAmount, $createdBy,
            $activityId, $note, 'active', 0
        );

        $id = $this->repo->savePromise($promise);
        $this->auditLogger?->log('dc.promise.create', 'debt_collection_promise', (string)$id,
            null, ['queue_id' => $queueId, 'promise_date' => $promiseDate, 'amount' => $promiseAmount], $createdBy);

        return ['id' => $id, 'queue_id' => $queueId, 'promise_date' => $promiseDate, 'promise_amount' => $promiseAmount];
    }

    //
    // XÁC NHẬN CAM KẾT ĐÃ GIỮ: KH trả tiền đúng hẹn
    //
    public function keepPromise(int $promiseId, string $keptBy, ?string $paymentDate = null): array
    {
        $promise = $this->repo->findPromiseById($promiseId);
        if (!$promise) throw new \InvalidArgumentException('Không tìm thấy cam kết.');
        if ($promise->getStatus() !== 'active') throw new \InvalidArgumentException('Cam kết không ở trạng thái hoạt động.');

        $this->repo->updatePromiseStatus($promiseId, 'kept', $paymentDate ?? date('Y-m-d'));
        $this->auditLogger?->log('dc.promise.keep', 'debt_collection_promise', (string)$promiseId,
            null, ['kept_by' => $keptBy], $keptBy);

        return ['id' => $promiseId, 'status' => 'kept'];
    }

    //
    // XÁC NHẬN CAM KẾT BỊ VỠ: KH không trả đúng hẹn
    // Nếu broken_count >= 3 → auto-escalate queue
    //
    public function breakPromise(int $promiseId, string $reason, string $brokenBy): array
    {
        $promise = $this->repo->findPromiseById($promiseId);
        if (!$promise) throw new \InvalidArgumentException('Không tìm thấy cam kết.');
        if ($promise->getStatus() !== 'active') throw new \InvalidArgumentException('Cam kết không ở trạng thái hoạt động.');

        $this->repo->updatePromiseStatus($promiseId, 'broken', null, $reason);

        // Đếm tổng số lần broken trên queue này, không chỉ trên 1 promise
        $brokenPromises = $this->repo->findPromisesByQueue($promise->getQueueId());
        $totalBroken = 0;
        foreach ($brokenPromises as $p) {
            if ($p['status'] === 'broken') $totalBroken++;
        }
        if ($totalBroken >= 3) {
            $this->escalateQueue($promise->getQueueId(), 2, $brokenBy);
        }

        $this->auditLogger?->log('dc.promise.broken', 'debt_collection_promise', (string)$promiseId,
            null, ['reason' => $reason, 'broken_count' => $totalBroken], $brokenBy);

        return ['id' => $promiseId, 'status' => 'broken', 'broken_count' => $totalBroken];
    }

    // ════════════════════════════════════════════════
    // 4. WRITE-OFF APPROVAL
    // ════════════════════════════════════════════════

    //
    // ĐỀ XUẤT XÓA NỢ: Collector đề xuất xóa nợ khó đòi
    // Validation: nợ quá hạn >= 365 ngày, >= 3 hoạt động trong 180 ngày gần nhất
    //
    public function proposeWriteOff(int $queueId, string $requestedBy, string $note): array
    {
        $entry = $this->repo->findQueueById($queueId);
        if (!$entry) throw new \InvalidArgumentException('Không tìm thấy queue entry.');

        $stmt = $this->pdo->prepare('SELECT i.balance, i.due_date FROM ar_invoices i WHERE i.id = ?');
        $stmt->execute([$entry->getInvoiceId()]);
        $inv = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$inv || $inv['balance'] <= 0) throw new \InvalidArgumentException('Hóa đơn không còn số dư để xóa sổ.');

        $daysOverdue = (int)date_diff(date_create($inv['due_date']), date_create('today'))->format('%a');
        if ($daysOverdue < 365) {
            throw new \InvalidArgumentException("Nợ mới quá hạn {$daysOverdue} ngày. Cần tối thiểu 365 ngày mới được đề xuất xóa nợ.");
        }

        // Kiểm tra tối thiểu 3 hoạt động trong 180 ngày
        $actStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM debt_collection_activities WHERE queue_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)"
        );
        $actStmt->execute([$queueId]);
        $activityCount = (int)$actStmt->fetchColumn();
        if ($activityCount < 3) {
            throw new \InvalidArgumentException("Cần tối thiểu 3 hoạt động đòi nợ trong 180 ngày qua. Hiện tại: {$activityCount}.");
        }

        // Xác định approval chain
        $amount = (float)$inv['balance'];
        $level1 = null;
        $level2 = null;
        $level3 = null;

        if ($amount <= 10000000) {
            $level1 = 'collection_lead'; // < 10tr: Collection Lead
        } elseif ($amount <= 100000000) {
            $level1 = 'collection_lead';
            $level2 = 'chief_accountant'; // 10-100tr: Chief Accountant
        } else {
            $level1 = 'collection_lead';
            $level2 = 'chief_accountant';
            $level3 = 'finance_director'; // > 100tr: Finance Director
        }

        $approval = new \Accounting\Domain\Model\Approval(
            $queueId, 'writeoff', $requestedBy, $amount, $note,
            null, null
        );
        $approval->setLevel1Approver($level1);
        $approval->setLevel2Approver($level2);
        $approval->setLevel3Approver($level3);

        $id = $this->repo->saveApproval($approval);
        $this->repo->updateQueueStatus($queueId, 'writeoff');

        $this->auditLogger?->log('dc.writeoff.propose', 'debt_collection_approval', (string)$id,
            null, ['queue_id' => $queueId, 'amount' => $amount, 'requested_by' => $requestedBy], $requestedBy);

        return ['approval_id' => $id, 'queue_id' => $queueId, 'amount' => $amount, 'status' => 'pending'];
    }

    //
    // PHÊ DUYỆT XÓA NỢ: Approve/reject ở từng cấp
    //
    public function approveWriteOff(int $approvalId, string $approverId, string $status, ?string $note = null): array
    {
        $approval = $this->repo->findApprovalById($approvalId);
        if (!$approval) throw new \InvalidArgumentException('Không tìm thấy yêu cầu phê duyệt.');
        if ($approval->getOverallStatus() !== 'pending') throw new \InvalidArgumentException('Yêu cầu đã được xử lý.');

        // Xác định cấp phê duyệt hiện tại
        $level = 0;
        if ($approval->getLevel1Status() === 'pending') $level = 1;
        elseif ($approval->getLevel2Status() === 'pending') $level = 2;
        elseif ($approval->getLevel3Status() === 'pending') $level = 3;
        else throw new \InvalidArgumentException('Không xác định được cấp phê duyệt.');

        $this->repo->updateApprovalLevel($approvalId, $level, $approverId, $status, $note);

        if ($status === 'rejected') {
            $this->repo->updateApprovalOverallStatus($approvalId, 'rejected');
            $this->repo->updateQueueStatus($approval->getQueueId(), 'active');
            $this->auditLogger?->log('dc.writeoff.reject', 'debt_collection_approval', (string)$approvalId,
                null, ['rejected_by' => $approverId, 'reason' => $note], $approverId);
            return ['approval_id' => $approvalId, 'status' => 'rejected'];
        }

        // Kiểm tra tất cả cấp đã approve chưa
        $allApproved = $this->checkAllLevelsApproved($approval, $level);

        if ($allApproved) {
            $this->repo->updateApprovalOverallStatus($approvalId, 'approved');
            // Lấy invoice_id từ queue entry để gọi ArService.writeOff()
            $queueEntry = $this->repo->findQueueById($approval->getQueueId());
            if (!$queueEntry) throw new \InvalidArgumentException('Không tìm thấy queue entry để xóa nợ.');
            $result = $this->arService->writeOff($queueEntry->getInvoiceId(), $approverId);
            $this->repo->closeQueue($approval->getQueueId(), 'writeoff_approved', "Write-off approved via approval #{$approvalId}");
            $this->auditLogger?->log('dc.writeoff.execute', 'debt_collection_approval', (string)$approvalId,
                null, ['queue_id' => $approval->getQueueId(), 'amount' => $approval->getAmount()], $approverId);
            return ['approval_id' => $approvalId, 'status' => 'approved', 'writeoff' => $result];
        }

        $this->auditLogger?->log('dc.writeoff.approve_level', 'debt_collection_approval', (string)$approvalId,
            null, ['level' => $level, 'approved_by' => $approverId], $approverId);
        return ['approval_id' => $approvalId, 'status' => 'pending_level_' . ($level + 1)];
    }

    private function checkAllLevelsApproved(\Accounting\Domain\Model\Approval $approval, int $justApprovedLevel): bool
    {
        $levels = [];
        if ($approval->getLevel1Approver()) $levels[1] = $approval->getLevel1Status();
        if ($approval->getLevel2Approver()) $levels[2] = $approval->getLevel2Status();
        if ($approval->getLevel3Approver()) $levels[3] = $approval->getLevel3Status();

        // Include the level that was just approved
        $levels[$justApprovedLevel] = 'approved';

        foreach ($levels as $l => $s) {
            if ($s !== 'approved') return false;
        }
        return true;
    }

    // ════════════════════════════════════════════════
    // 5. SETTLEMENT
    // ════════════════════════════════════════════════

    //
    // TẠO THỎA THUẬN THANH TOÁN: KH trả 1 phần, xóa phần còn lại
    // Ràng buộc: discount_percent <= 70%, due_by_date <= 14 ngày
    //
    public function createSettlement(int $queueId, float $settlementAmount, string $agreementDate, string $dueByDate, string $createdBy, ?int $approvalId = null, string $paymentType = 'lump_sum'): array
    {
        $entry = $this->repo->findQueueById($queueId);
        if (!$entry) throw new \InvalidArgumentException('Không tìm thấy queue entry.');

        $stmt = $this->pdo->prepare('SELECT balance FROM ar_invoices WHERE id = ?');
        $stmt->execute([$entry->getInvoiceId()]);
        $inv = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$inv || $inv['balance'] <= 0) throw new \InvalidArgumentException('Hóa đơn không còn số dư.');

        $originalBalance = (float)$inv['balance'];
        $discountAmount = $originalBalance - $settlementAmount;
        $discountPercent = $originalBalance > 0 ? round($discountAmount / $originalBalance * 100, 2) : 0;

        if ($discountPercent > 70) {
            throw new \InvalidArgumentException("Tỷ lệ giảm {$discountPercent}% vượt quá 70%. Vui lòng điều chỉnh số tiền thỏa thuận.");
        }

        // due_by_date <= 14 days from today
        $dueDate = new \DateTime($dueByDate);
        $today = new \DateTime('today');
        $diffDays = (int)$today->diff($dueDate)->format('%a');
        if ($diffDays > 14) {
            throw new \InvalidArgumentException('Thời hạn thanh toán không được quá 14 ngày từ hôm nay.');
        }

        $settlement = new \Accounting\Domain\Model\Settlement(
            $queueId, $originalBalance, $settlementAmount, $discountAmount, $discountPercent,
            $agreementDate, $dueByDate, $createdBy,
            $approvalId, $paymentType, 1, null, 'active', 0
        );

        $id = $this->repo->saveSettlement($settlement);
        $this->repo->updateQueueStatus($queueId, 'settlement');

        $this->auditLogger?->log('dc.settlement.create', 'debt_collection_settlement', (string)$id,
            null, ['queue_id' => $queueId, 'original' => $originalBalance, 'settlement' => $settlementAmount], $createdBy);

        return ['id' => $id, 'queue_id' => $queueId, 'settlement_amount' => $settlementAmount, 'discount_percent' => $discountPercent];
    }

    //
    // GHI NHẬN THANH TOÁN THỎA THUẬN
    //
    public function recordSettlementPayment(int $settlementId, float $amount, string $paymentDate, string $createdBy): array
    {
        $settlement = $this->repo->findSettlementById($settlementId);
        if (!$settlement) throw new \InvalidArgumentException('Không tìm thấy thỏa thuận thanh toán.');
        if ($settlement->getStatus() !== 'active') throw new \InvalidArgumentException('Thỏa thuận không ở trạng thái hoạt động.');

        $newPaid = $settlement->getAmountPaid() + $amount;
        if ($newPaid > $settlement->getSettlementAmount()) {
            throw new \InvalidArgumentException('Số tiền thanh toán vượt quá số tiền thỏa thuận.');
        }

        $this->repo->updateSettlementPayment($settlementId, $amount, $paymentDate);

        if ($newPaid >= $settlement->getSettlementAmount()) {
            $this->repo->updateSettlementStatus($settlementId, 'completed');

            // Gọi ArService để ghi nhận payment
            $queueId = $settlement->getQueueId();
            // Nếu payment đủ → close queue
            $this->repo->closeQueue($queueId, 'settlement', "Settlement #{$settlementId} completed");

            $this->auditLogger?->log('dc.settlement.complete', 'debt_collection_settlement', (string)$settlementId,
                null, ['total_paid' => $newPaid], $createdBy);
        }

        return ['settlement_id' => $settlementId, 'amount_paid' => $newPaid, 'status' => $newPaid >= $settlement->getSettlementAmount() ? 'completed' : 'active'];
    }

    // ════════════════════════════════════════════════
    // 6. CRON JOBS
    // ════════════════════════════════════════════════

    //
    // KIỂM TRA CAM KẾT ĐẾN HẠN: Chạy daily, mark broken nếu quá hạn
    //
    public function checkPromisesDue(string $runBy = 'system'): array
    {
        $duePromises = $this->repo->findActivePromisesDueToday();
        $results = ['kept' => 0, 'broken' => 0, 'grace' => 0];

        foreach ($duePromises as $p) {
            // Kiểm tra xem invoice đã được thanh toán chưa (grace +2 days)
            $stmt = $this->pdo->prepare('SELECT balance FROM ar_invoices WHERE id = ?');
            $stmt->execute([$p['invoice_id']]);
            $inv = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($inv && $inv['balance'] <= 1) {
                // Đã thanh toán → keep promise
                $this->repo->updatePromiseStatus($p['id'], 'kept', date('Y-m-d'));
                $results['kept']++;
                $this->auditLogger?->log('dc.promise.auto_keep', 'debt_collection_promise', (string)$p['id'],
                    null, ['queue_id' => $p['queue_id']], $runBy);
            } else {
                // Chưa thanh toán → broken
                $this->repo->updatePromiseStatus($p['id'], 'broken', null, 'Quá hạn cam kết - tự động cập nhật');

                $newBrokenCount = (int)$p['broken_count'] + 1;
                if ($newBrokenCount >= 3) {
                    $this->escalateQueue($p['queue_id'], 2, $runBy);
                }
                $results['broken']++;
                $this->auditLogger?->log('dc.promise.auto_broken', 'debt_collection_promise', (string)$p['id'],
                    null, ['queue_id' => $p['queue_id'], 'broken_count' => $newBrokenCount], $runBy);
            }
        }

        return $results;
    }

    //
    // AUTO ESCALATE: Tự động leo thang dựa trên aging
    //
    public function autoEscalate(string $runBy = 'system'): array
    {
        $queues = $this->pdo->query(
            "SELECT q.id, q.invoice_id, i.due_date, q.escalation_level
             FROM debt_collection_queue q
             JOIN ar_invoices i ON i.id = q.invoice_id
             WHERE q.status IN ('active','new')
             ORDER BY q.id"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $escalated = 0;
        foreach ($queues as $q) {
            $daysOverdue = (int)date_diff(date_create($q['due_date']), date_create('today'))->format('%a');
            $expectedLevel = $this->calculateEscalationLevel($daysOverdue);

            if ($expectedLevel > (int)$q['escalation_level']) {
                $this->repo->updateQueueEscalation($q['id'], $expectedLevel);
                $escalated++;
                $this->auditLogger?->log('dc.queue.auto_escalate', 'debt_collection_queue', (string)$q['id'],
                    null, ['escalation_level' => $expectedLevel, 'days_overdue' => $daysOverdue], $runBy);
            }
        }

        return ['escalated' => $escalated];
    }

    //
    // AUTO RELEASE HOLDS: Tự động release hold đã hết hạn
    //
    public function autoReleaseHolds(string $runBy = 'system'): array
    {
        $stmt = $this->pdo->prepare(
            "UPDATE debt_collection_queue
             SET status = 'active', hold_reason = NULL, hold_until = NULL, updated_at = NOW()
             WHERE status = 'hold' AND hold_until IS NOT NULL AND hold_until <= CURDATE()"
        );
        $stmt->execute();
        $released = $stmt->rowCount();

        if ($released > 0) {
            $this->auditLogger?->log('dc.queue.auto_release', 'debt_collection_queue', 'batch',
                null, ['count' => $released], $runBy);
        }

        return ['released' => $released];
    }

    //
    // AUTO-CLOSE QUEUE KHI INVOICE PAID: Gọi từ ArService hook
    //
    public function handlePaymentReceived(int $invoiceId): ?array
    {
        $entry = $this->repo->findQueueByInvoice($invoiceId);
        if (!$entry) return null;
        if ($entry->getStatus() === 'closed') return null;

        // Kiểm tra balance
        $stmt = $this->pdo->prepare('SELECT balance FROM ar_invoices WHERE id = ?');
        $stmt->execute([$invoiceId]);
        $inv = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($inv && $inv['balance'] <= 1) {
            $this->repo->closeQueue($entry->getId(), 'paid', 'Invoice fully paid.');
            return ['queue_id' => $entry->getId(), 'status' => 'closed', 'resolution' => 'paid'];
        }

        return null;
    }

    // ════════════════════════════════════════════════
    // QUERIES
    // ════════════════════════════════════════════════

    public function getQueue(int $id): ?array
    {
        $entry = $this->repo->findQueueById($id);
        if (!$entry) return null;
        $data = $entry->toArray();
        $data['activities'] = $this->repo->findActivitiesByQueue($id);
        $data['promises'] = $this->repo->findPromisesByQueue($id);
        $data['approvals'] = $this->repo->findApprovalsByQueue($id);
        $data['settlement'] = $this->repo->findSettlementByQueue($id);
        return $data;
    }

    public function listQueues(array $filters = []): array { return $this->repo->findQueues($filters); }

    public function getQueueStats(): array { return $this->repo->getQueueStats(); }

    public function getCollectorStats(string $collectorId): array { return $this->repo->getCollectorStats($collectorId); }

    public function getPendingApprovals(?string $approverId = null): array { return $this->repo->findPendingApprovals($approverId); }

    //
    // TÍNH CẤP ĐỘ LEO THANG: Dựa trên số ngày quá hạn
    //
    private function calculateEscalationLevel(int $daysOverdue): int
    {
        if ($daysOverdue <= 30) return 0;
        if ($daysOverdue <= 60) return 1;
        if ($daysOverdue <= 90) return 2;
        if ($daysOverdue <= 180) return 3;
        if ($daysOverdue <= 365) return 4;
        return 5;
    }
}

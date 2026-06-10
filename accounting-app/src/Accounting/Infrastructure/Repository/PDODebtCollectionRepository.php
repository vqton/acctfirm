<?php
namespace Accounting\Infrastructure\Repository;

use Accounting\Domain\Model\QueueEntry;
use Accounting\Domain\Model\Activity;
use Accounting\Domain\Model\Promise;
use Accounting\Domain\Model\Approval;
use Accounting\Domain\Model\Settlement;
use Accounting\Domain\Repository\DebtCollectionRepositoryInterface;

/**
 * Repository PDO cho module Thu hồi công nợ (Debt Collection).
 *
 * Quản lý các thao tác CRUD cho:
 * - debt_collection_queue (danh sách công nợ cần thu hồi)
 * - debt_collection_activities (hoạt động thu hồi)
 * - debt_collection_promises (cam kết thanh toán)
 * - debt_collection_approvals (phê duyệt phương án)
 * - debt_collection_settlements (thỏa thuận thanh toán)
 */
class PDODebtCollectionRepository implements DebtCollectionRepositoryInterface
{
    private \PDO $pdo;

    /**
     * @param \PDO $pdo Kết nối PDO đến MySQL
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Queue ──

    /**
     * Tìm mục công nợ trong hàng đợi theo ID.
     *
     * JOIN với ar_invoices và customers để lấy thông tin hóa đơn và khách hàng.
     *
     * @param int $id ID của mục trong hàng đợi
     * @return QueueEntry|null Đối tượng QueueEntry nếu tìm thấy, null nếu không
     */
    public function findQueueById(int $id): ?QueueEntry
    {
        $stmt = $this->pdo->prepare('SELECT q.*, i.invoice_number, i.gross_amount, i.balance, i.due_date, i.status as invoice_status, c.name as customer_name, c.code as customer_code
            FROM debt_collection_queue q
            JOIN ar_invoices i ON i.id = q.invoice_id
            JOIN customers c ON c.id = q.customer_id
            WHERE q.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? QueueEntry::fromRow($row) : null;
    }

    /**
     * Tìm mục công nợ trong hàng đợi theo ID hóa đơn.
     *
     * @param int $invoiceId ID của hóa đơn
     * @return QueueEntry|null Đối tượng QueueEntry nếu tìm thấy, null nếu không
     */
    public function findQueueByInvoice(int $invoiceId): ?QueueEntry
    {
        $stmt = $this->pdo->prepare('SELECT * FROM debt_collection_queue WHERE invoice_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$invoiceId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? QueueEntry::fromRow($row) : null;
    }

    /**
     * Tìm danh sách công nợ trong hàng đợi theo bộ lọc.
     *
     * Hỗ trợ lọc theo: status (một hoặc nhiều), assigned_to, customer_id,
     * priority_min, escalation_level, unassigned.
     * Giới hạn tối đa 200 bản ghi, sắp xếp theo priority DESC, entered_at ASC.
     *
     * @param array $filters Mảng các điều kiện lọc
     * @return array Danh sách các mục công nợ (mảng kết hợp)
     */
    public function findQueues(array $filters = []): array
    {
        $sql = 'SELECT q.*, i.invoice_number, i.gross_amount, i.balance, i.due_date, i.status as invoice_status, c.name as customer_name, c.code as customer_code
            FROM debt_collection_queue q
            JOIN ar_invoices i ON i.id = q.invoice_id
            JOIN customers c ON c.id = q.customer_id WHERE 1=1';
        $params = [];

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '?'));
                $sql .= " AND q.status IN ($placeholders)";
                $params = array_merge($params, $filters['status']);
            } else {
                $sql .= ' AND q.status = ?';
                $params[] = $filters['status'];
            }
        }
        if (!empty($filters['assigned_to'])) {
            $sql .= ' AND q.assigned_to = ?';
            $params[] = $filters['assigned_to'];
        }
        if (!empty($filters['customer_id'])) {
            $sql .= ' AND q.customer_id = ?';
            $params[] = $filters['customer_id'];
        }
        if (!empty($filters['priority_min'])) {
            $sql .= ' AND q.priority >= ?';
            $params[] = (int)$filters['priority_min'];
        }
        if (!empty($filters['escalation_level'])) {
            $sql .= ' AND q.escalation_level >= ?';
            $params[] = (int)$filters['escalation_level'];
        }
        if (isset($filters['unassigned']) && $filters['unassigned']) {
            $sql .= ' AND q.assigned_to IS NULL';
        }

        $sql .= ' ORDER BY q.priority DESC, q.entered_at ASC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Tìm danh sách công nợ đang hoạt động của một nhân viên thu hồi.
     *
     * Lọc các mục có trạng thái new, active, hold và assigned_to = collectorId.
     *
     * @param string $collectorId ID của nhân viên thu hồi
     * @return array Danh sách các mục công nợ (mảng kết hợp)
     */
    public function findActiveQueuesByCollector(string $collectorId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT q.*, i.invoice_number, i.gross_amount, i.balance, i.due_date, i.status as invoice_status, c.name as customer_name, c.code as customer_code
             FROM debt_collection_queue q
             JOIN ar_invoices i ON i.id = q.invoice_id
             JOIN customers c ON c.id = q.customer_id
             WHERE q.assigned_to = ? AND q.status IN ("new","active","hold")
             ORDER BY q.priority DESC, q.entered_at ASC'
        );
        $stmt->execute([$collectorId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Tìm danh sách công nợ chưa được phân công.
     *
     * @return array Danh sách các mục công nợ chưa gán (mảng kết hợp)
     */
    public function findUnassignedQueues(): array
    {
        return $this->findQueues(['unassigned' => true]);
    }

    /**
     * Lưu mục công nợ mới vào hàng đợi.
     *
     * @param QueueEntry $entry Đối tượng QueueEntry cần lưu
     * @return int ID của bản ghi vừa tạo
     */
    public function saveQueue(QueueEntry $entry): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO debt_collection_queue (invoice_id, customer_id, assigned_to, status, priority, escalation_level, hold_count, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $entry->getInvoiceId(),
            $entry->getCustomerId(),
            $entry->getAssignedTo(),
            $entry->getStatus(),
            $entry->getPriority(),
            $entry->getEscalationLevel(),
            $entry->getHoldCount(),
            $entry->getCreatedBy(),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Cập nhật trạng thái mục công nợ.
     *
     * Nếu trạng thái là 'closed', tự động ghi nhận thời gian và lý do giải quyết.
     *
     * @param int $id ID của mục công nợ
     * @param string $status Trạng thái mới (new, active, hold, escalated, closed)
     * @param string|null $resolution Hình thức giải quyết (chỉ khi đóng)
     * @param string|null $resolutionNote Ghi chú giải quyết (chỉ khi đóng)
     * @return void
     */
    public function updateQueueStatus(int $id, string $status, ?string $resolution = null, ?string $resolutionNote = null): void
    {
        if ($status === 'closed') {
            $stmt = $this->pdo->prepare(
                'UPDATE debt_collection_queue SET status = ?, resolved_at = NOW(), resolution = ?, resolution_note = ? WHERE id = ?'
            );
            $stmt->execute([$status, $resolution, $resolutionNote, $id]);
        } else {
            $stmt = $this->pdo->prepare('UPDATE debt_collection_queue SET status = ? WHERE id = ?');
            $stmt->execute([$status, $id]);
        }
    }

    /**
     * Phân công mục công nợ cho nhân viên thu hồi.
     *
     * Tự động chuyển trạng thái thành 'active'.
     *
     * @param int $id ID của mục công nợ
     * @param string $collectorId ID của nhân viên thu hồi
     * @return void
     */
    public function updateQueueAssignment(int $id, string $collectorId): void
    {
        $stmt = $this->pdo->prepare('UPDATE debt_collection_queue SET assigned_to = ?, status = "active" WHERE id = ?');
        $stmt->execute([$collectorId, $id]);
    }

    /**
     * Tạm giữ hoặc bỏ tạm giữ mục công nợ.
     *
     * Nếu có lý do -> chuyển sang trạng thái hold.
     * Nếu không có lý do -> chuyển về trạng thái active và xóa thông tin hold.
     *
     * @param int $id ID của mục công nợ
     * @param string|null $reason Lý do tạm giữ (null để bỏ tạm giữ)
     * @param string|null $holdUntil Thời hạn tạm giữ
     * @param int $holdCount Số lần đã tạm giữ
     * @return void
     */
    public function updateQueueHold(int $id, ?string $reason, ?string $holdUntil, int $holdCount): void
    {
        if ($reason !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE debt_collection_queue SET status = "hold", hold_reason = ?, hold_until = ?, hold_count = ? WHERE id = ?'
            );
            $stmt->execute([$reason, $holdUntil, $holdCount, $id]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE debt_collection_queue SET status = "active", hold_reason = NULL, hold_until = NULL WHERE id = ?'
            );
            $stmt->execute([$id]);
        }
    }

    /**
     * Cập nhật mức ưu tiên của mục công nợ.
     *
     * @param int $id ID của mục công nợ
     * @param int $priority Mức ưu tiên mới (cao hơn = quan trọng hơn)
     * @return void
     */
    public function updateQueuePriority(int $id, int $priority): void
    {
        $stmt = $this->pdo->prepare('UPDATE debt_collection_queue SET priority = ? WHERE id = ?');
        $stmt->execute([$priority, $id]);
    }

    /**
     * Cập nhật mức leo thang của mục công nợ.
     *
     * Tự động chuyển trạng thái thành 'escalated'.
     *
     * @param int $id ID của mục công nợ
     * @param int $level Mức leo thang mới
     * @return void
     */
    public function updateQueueEscalation(int $id, int $level): void
    {
        $stmt = $this->pdo->prepare('UPDATE debt_collection_queue SET escalation_level = ?, status = "escalated" WHERE id = ?');
        $stmt->execute([$level, $id]);
    }

    /**
     * Cập nhật thông tin hành động gần nhất của mục công nợ.
     *
     * @param int $id ID của mục công nợ
     * @param string|null $nextActionDate Ngày hành động tiếp theo
     * @return void
     */
    public function updateQueueLastAction(int $id, ?string $nextActionDate): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE debt_collection_queue SET last_action_date = NOW(), next_action_date = ? WHERE id = ?'
        );
        $stmt->execute([$nextActionDate, $id]);
    }

    /**
     * Đóng mục công nợ với lý do giải quyết.
     *
     * @param int $id ID của mục công nợ
     * @param string $resolution Hình thức giải quyết (paid, written_off, etc.)
     * @param string|null $note Ghi chú giải quyết
     * @return void
     */
    public function closeQueue(int $id, string $resolution, ?string $note = null): void
    {
        $this->updateQueueStatus($id, 'closed', $resolution, $note);
    }

    /**
     * Đếm số lượng công nợ đang xử lý của một nhân viên thu hồi.
     *
     * @param string $collectorId ID của nhân viên thu hồi
     * @return int Số lượng công nợ đang xử lý
     */
    public function countActiveByCollector(string $collectorId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM debt_collection_queue WHERE assigned_to = ? AND status IN ('new','active','hold')"
        );
        $stmt->execute([$collectorId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Kiểm tra xem hóa đơn đã có trong hàng đợi chưa (chưa đóng).
     *
     * @param int $invoiceId ID của hóa đơn
     * @return bool True nếu đã tồn tại trong hàng đợi
     */
    public function queueExistsForInvoice(int $invoiceId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM debt_collection_queue WHERE invoice_id = ? AND status NOT IN ('closed')"
        );
        $stmt->execute([$invoiceId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ── Activities ──

    /**
     * Tìm hoạt động thu hồi theo ID.
     *
     * @param int $id ID của hoạt động
     * @return Activity|null Đối tượng Activity nếu tìm thấy, null nếu không
     */
    public function findActivityById(int $id): ?Activity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM debt_collection_activities WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? Activity::fromRow($row) : null;
    }

    /**
     * Tìm danh sách hoạt động thu hồi theo mục công nợ.
     *
     * @param int $queueId ID của mục công nợ
     * @return array Danh sách hoạt động (mảng kết hợp)
     */
    public function findActivitiesByQueue(int $queueId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*
             FROM debt_collection_activities a
             WHERE a.queue_id = ?
             ORDER BY a.created_at DESC'
        );
        $stmt->execute([$queueId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Lưu hoạt động thu hồi mới.
     *
     * @param Activity $activity Đối tượng Activity cần lưu
     * @return int ID của bản ghi vừa tạo
     */
    public function saveActivity(Activity $activity): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO debt_collection_activities (queue_id, activity_type, summary, detail, contact_person, contact_phone, result, promise_date, promise_amount, duration_minutes, attachment_path, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $activity->getQueueId(),
            $activity->getActivityType(),
            $activity->getSummary(),
            $activity->getDetail(),
            $activity->getContactPerson(),
            $activity->getContactPhone(),
            $activity->getResult(),
            $activity->getPromiseDate(),
            $activity->getPromiseAmount(),
            $activity->getDurationMinutes(),
            $activity->getAttachmentPath(),
            $activity->getCreatedBy(),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Xóa hoạt động thu hồi.
     *
     * @param int $id ID của hoạt động cần xóa
     * @return void
     */
    public function deleteActivity(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM debt_collection_activities WHERE id = ?');
        $stmt->execute([$id]);
    }

    // ── Promises ──

    /**
     * Tìm cam kết thanh toán theo ID.
     *
     * @param int $id ID của cam kết
     * @return Promise|null Đối tượng Promise nếu tìm thấy, null nếu không
     */
    public function findPromiseById(int $id): ?Promise
    {
        $stmt = $this->pdo->prepare('SELECT * FROM debt_collection_promises WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? Promise::fromRow($row) : null;
    }

    /**
     * Tìm danh sách cam kết thanh toán theo mục công nợ.
     *
     * @param int $queueId ID của mục công nợ
     * @return array Danh sách cam kết (mảng kết hợp)
     */
    public function findPromisesByQueue(int $queueId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.* FROM debt_collection_promises p WHERE p.queue_id = ? ORDER BY p.created_at DESC'
        );
        $stmt->execute([$queueId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Tìm các cam kết thanh toán đang hoạt động và đến hạn hôm nay.
     *
     * JOIN với debt_collection_queue và customers để lấy thông tin liên quan.
     *
     * @return array Danh sách cam kết đến hạn (mảng kết hợp)
     */
    public function findActivePromisesDueToday(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, q.invoice_id, q.customer_id, c.name as customer_name
             FROM debt_collection_promises p
             JOIN debt_collection_queue q ON q.id = p.queue_id
             JOIN customers c ON c.id = q.customer_id
             WHERE p.status = 'active' AND p.promise_date <= CURDATE()"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Tìm các cam kết thanh toán đang hoạt động của một khách hàng.
     *
     * @param string $customerId ID của khách hàng
     * @return array Danh sách cam kết (mảng kết hợp)
     */
    public function findActivePromisesByCustomer(string $customerId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.* FROM debt_collection_promises p
             JOIN debt_collection_queue q ON q.id = p.queue_id
             WHERE q.customer_id = ? AND p.status = 'active'
             ORDER BY p.promise_date DESC"
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Lưu cam kết thanh toán mới.
     *
     * @param Promise $promise Đối tượng Promise cần lưu
     * @return int ID của bản ghi vừa tạo
     */
    public function savePromise(Promise $promise): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO debt_collection_promises (queue_id, activity_id, promise_date, promise_amount, promise_note, status, broken_count, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $promise->getQueueId(),
            $promise->getActivityId(),
            $promise->getPromiseDate(),
            $promise->getPromiseAmount(),
            $promise->getPromiseNote(),
            $promise->getStatus(),
            $promise->getBrokenCount(),
            $promise->getCreatedBy(),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Cập nhật trạng thái cam kết thanh toán.
     *
     * Nếu có kept_date -> cập nhật ngày giữ cam kết.
     * Nếu có broken_reason -> cập nhật lý do vi phạm và tăng broken_count.
     *
     * @param int $id ID của cam kết
     * @param string $status Trạng thái mới (active, kept, broken)
     * @param string|null $keptDate Ngày giữ cam kết (nếu có)
     * @param string|null $brokenReason Lý do vi phạm (nếu có)
     * @return void
     */
    public function updatePromiseStatus(int $id, string $status, ?string $keptDate = null, ?string $brokenReason = null): void
    {
        if ($keptDate) {
            $stmt = $this->pdo->prepare('UPDATE debt_collection_promises SET status = ?, kept_date = ? WHERE id = ?');
            $stmt->execute([$status, $keptDate, $id]);
        } elseif ($brokenReason) {
            $stmt = $this->pdo->prepare('UPDATE debt_collection_promises SET status = ?, broken_reason = ?, broken_count = broken_count + 1 WHERE id = ?');
            $stmt->execute([$status, $brokenReason, $id]);
        } else {
            $stmt = $this->pdo->prepare('UPDATE debt_collection_promises SET status = ? WHERE id = ?');
            $stmt->execute([$status, $id]);
        }
    }

    /**
     * Tăng số lần vi phạm cam kết.
     *
     * @param int $id ID của cam kết
     * @return void
     */
    public function incrementPromiseBrokenCount(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE debt_collection_promises SET broken_count = broken_count + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    // ── Approvals ──

    /**
     * Tìm phê duyệt phương án thu hồi theo ID.
     *
     * @param int $id ID của phê duyệt
     * @return Approval|null Đối tượng Approval nếu tìm thấy, null nếu không
     */
    public function findApprovalById(int $id): ?Approval
    {
        $stmt = $this->pdo->prepare('SELECT * FROM debt_collection_approvals WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? Approval::fromRow($row) : null;
    }

    /**
     * Tìm danh sách phê duyệt theo mục công nợ.
     *
     * @param int $queueId ID của mục công nợ
     * @return array Danh sách phê duyệt (mảng kết hợp)
     */
    public function findApprovalsByQueue(int $queueId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM debt_collection_approvals WHERE queue_id = ? ORDER BY id DESC'
        );
        $stmt->execute([$queueId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Tìm danh sách phê duyệt đang chờ xử lý.
     *
     * Có thể lọc theo người phê duyệt (level 1, 2, hoặc 3).
     *
     * @param string|null $approverId ID của người phê duyệt (null để lấy tất cả)
     * @return array Danh sách phê duyệt đang chờ (mảng kết hợp)
     */
    public function findPendingApprovals(?string $approverId = null): array
    {
        $sql = 'SELECT a.*, q.customer_id, c.name as customer_name, i.invoice_number, i.balance
            FROM debt_collection_approvals a
            JOIN debt_collection_queue q ON q.id = a.queue_id
            JOIN customers c ON c.id = q.customer_id
            JOIN ar_invoices i ON i.id = q.invoice_id
            WHERE a.overall_status = "pending"';
        $params = [];

        if ($approverId) {
            $sql .= ' AND (a.level_1_approver = ? OR a.level_2_approver = ? OR a.level_3_approver = ?)';
            $params = [$approverId, $approverId, $approverId];
        }

        $sql .= ' ORDER BY a.requested_at ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Lưu phê duyệt phương án thu hồi mới.
     *
     * @param Approval $approval Đối tượng Approval cần lưu
     * @return int ID của bản ghi vừa tạo
     */
    public function saveApproval(Approval $approval): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO debt_collection_approvals (queue_id, approval_type, requested_by, amount, request_note, settlement_percent, settlement_amount, level_1_approver, level_2_approver, level_3_approver)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $approval->getQueueId(),
            $approval->getApprovalType(),
            $approval->getRequestedBy(),
            $approval->getAmount(),
            $approval->getRequestNote(),
            $approval->getSettlementPercent(),
            $approval->getSettlementAmount(),
            $approval->getLevel1Approver(),
            $approval->getLevel2Approver(),
            $approval->getLevel3Approver(),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Cập nhật trạng thái phê duyệt theo cấp.
     *
     * Nếu cấp nào bị từ chối (rejected), tự động cập nhật overall_status thành rejected.
     *
     * @param int $id ID của phê duyệt
     * @param int $level Cấp phê duyệt (1, 2, 3)
     * @param string $approver ID người phê duyệt
     * @param string $status Trạng thái phê duyệt (approved, rejected)
     * @param string|null $note Ghi chú phê duyệt
     * @return void
     */
    public function updateApprovalLevel(int $id, int $level, string $approver, string $status, ?string $note = null): void
    {
        $field = "level_{$level}_status";
        $approverField = "level_{$level}_approver";
        $noteField = "level_{$level}_note";
        $atField = "level_{$level}_at";

        $stmt = $this->pdo->prepare(
            "UPDATE debt_collection_approvals SET {$approverField} = ?, {$field} = ?, {$noteField} = ?, {$atField} = NOW() WHERE id = ?"
        );
        $stmt->execute([$approver, $status, $note, $id]);

        if ($status === 'rejected') {
            $stmt = $this->pdo->prepare('UPDATE debt_collection_approvals SET overall_status = "rejected", resolved_at = NOW() WHERE id = ?');
            $stmt->execute([$id]);
        }
    }

    /**
     * Cập nhật trạng thái tổng thể của phê duyệt.
     *
     * Nếu trạng thái là approved hoặc rejected, tự động ghi nhận thời gian giải quyết.
     *
     * @param int $id ID của phê duyệt
     * @param string $status Trạng thái tổng thể mới (pending, approved, rejected)
     * @return void
     */
    public function updateApprovalOverallStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE debt_collection_approvals SET overall_status = ?, resolved_at = IF(? IN ("approved","rejected"), NOW(), resolved_at) WHERE id = ?'
        );
        $stmt->execute([$status, $status, $id]);
    }

    // ── Settlements ──

    /**
     * Tìm thỏa thuận thanh toán theo ID.
     *
     * @param int $id ID của thỏa thuận
     * @return Settlement|null Đối tượng Settlement nếu tìm thấy, null nếu không
     */
    public function findSettlementById(int $id): ?Settlement
    {
        $stmt = $this->pdo->prepare('SELECT * FROM debt_collection_settlements WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? Settlement::fromRow($row) : null;
    }

    /**
     * Tìm thỏa thuận thanh toán theo mục công nợ.
     *
     * @param int $queueId ID của mục công nợ
     * @return Settlement|null Đối tượng Settlement nếu tìm thấy, null nếu không
     */
    public function findSettlementByQueue(int $queueId): ?Settlement
    {
        $stmt = $this->pdo->prepare('SELECT * FROM debt_collection_settlements WHERE queue_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$queueId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? Settlement::fromRow($row) : null;
    }

    /**
     * Tìm danh sách thỏa thuận thanh toán đang hoạt động.
     *
     * JOIN với debt_collection_queue và customers để lấy thông tin liên quan.
     *
     * @return array Danh sách thỏa thuận đang hoạt động (mảng kết hợp)
     */
    public function findActiveSettlements(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, q.customer_id, c.name as customer_name
             FROM debt_collection_settlements s
             JOIN debt_collection_queue q ON q.id = s.queue_id
             JOIN customers c ON c.id = q.customer_id
             WHERE s.status = "active"
             ORDER BY s.due_by_date ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Lưu thỏa thuận thanh toán mới.
     *
     * @param Settlement $settlement Đối tượng Settlement cần lưu
     * @return int ID của bản ghi vừa tạo
     */
    public function saveSettlement(Settlement $settlement): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO debt_collection_settlements (queue_id, approval_id, original_balance, settlement_amount, discount_amount, discount_percent, payment_type, installment_count, installment_frequency, agreement_date, due_by_date, status, amount_paid, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $settlement->getQueueId(),
            $settlement->getApprovalId(),
            $settlement->getOriginalBalance(),
            $settlement->getSettlementAmount(),
            $settlement->getDiscountAmount(),
            $settlement->getDiscountPercent(),
            $settlement->getPaymentType(),
            $settlement->getInstallmentCount(),
            $settlement->getInstallmentFrequency(),
            $settlement->getAgreementDate(),
            $settlement->getDueByDate(),
            $settlement->getStatus(),
            $settlement->getAmountPaid(),
            $settlement->getCreatedBy(),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Cập nhật số tiền đã thanh toán của thỏa thuận.
     *
     * Cộng dồn số tiền thanh toán và ghi nhận ngày thanh toán cuối.
     *
     * @param int $id ID của thỏa thuận
     * @param float $amount Số tiền thanh toán thêm
     * @param string $paymentDate Ngày thanh toán
     * @return void
     */
    public function updateSettlementPayment(int $id, float $amount, string $paymentDate): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE debt_collection_settlements SET amount_paid = amount_paid + ?, last_payment_date = ? WHERE id = ?'
        );
        $stmt->execute([$amount, $paymentDate, $id]);
    }

    /**
     * Cập nhật trạng thái thỏa thuận thanh toán.
     *
     * @param int $id ID của thỏa thuận
     * @param string $status Trạng thái mới (active, completed, defaulted)
     * @return void
     */
    public function updateSettlementStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE debt_collection_settlements SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    // ── Stats ──

    /**
     * Lấy thống kê tổng quan về hàng đợi thu hồi công nợ.
     *
     * Trả về số lượng: new, active, hold, escalated, closed, total_open, total.
     *
     * @return array Mảng kết hợp chứa các thống kê
     */
    public function getQueueStats(): array
    {
        return $this->pdo->query(
            "SELECT
                SUM(status='new') as new_count,
                SUM(status='active') as active_count,
                SUM(status='hold') as hold_count,
                SUM(status='escalated') as escalated_count,
                SUM(status='closed') as closed_count,
                SUM(status IN ('new','active','hold','escalated')) as total_open,
                COUNT(*) as total
             FROM debt_collection_queue"
        )->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Lấy thống kê hiệu suất của một nhân viên thu hồi.
     *
     * Trả về: total_assigned, active, on_hold, resolved_paid, total_closed.
     *
     * @param string $collectorId ID của nhân viên thu hồi
     * @return array Mảng kết hợp chứa các thống kê
     */
    public function getCollectorStats(string $collectorId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) as total_assigned,
                SUM(status='active') as active,
                SUM(status='hold') as on_hold,
                SUM(status='closed' AND resolution='paid') as resolved_paid,
                SUM(status='closed') as total_closed
             FROM debt_collection_queue WHERE assigned_to = ?"
        );
        $stmt->execute([$collectorId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: [];
    }
}

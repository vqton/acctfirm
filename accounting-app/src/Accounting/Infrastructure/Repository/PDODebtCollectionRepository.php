<?php
namespace Accounting\Infrastructure\Repository;

use Accounting\Domain\Model\QueueEntry;
use Accounting\Domain\Model\Activity;
use Accounting\Domain\Model\Promise;
use Accounting\Domain\Model\Approval;
use Accounting\Domain\Model\Settlement;
use Accounting\Domain\Repository\DebtCollectionRepositoryInterface;

class PDODebtCollectionRepository implements DebtCollectionRepositoryInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Queue ──

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

    public function findQueueByInvoice(int $invoiceId): ?QueueEntry
    {
        $stmt = $this->pdo->prepare('SELECT * FROM debt_collection_queue WHERE invoice_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$invoiceId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? QueueEntry::fromRow($row) : null;
    }

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

    public function findUnassignedQueues(): array
    {
        return $this->findQueues(['unassigned' => true]);
    }

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

    public function updateQueueAssignment(int $id, string $collectorId): void
    {
        $stmt = $this->pdo->prepare('UPDATE debt_collection_queue SET assigned_to = ?, status = "active" WHERE id = ?');
        $stmt->execute([$collectorId, $id]);
    }

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

    public function updateQueuePriority(int $id, int $priority): void
    {
        $stmt = $this->pdo->prepare('UPDATE debt_collection_queue SET priority = ? WHERE id = ?');
        $stmt->execute([$priority, $id]);
    }

    public function updateQueueEscalation(int $id, int $level): void
    {
        $stmt = $this->pdo->prepare('UPDATE debt_collection_queue SET escalation_level = ?, status = "escalated" WHERE id = ?');
        $stmt->execute([$level, $id]);
    }

    public function updateQueueLastAction(int $id, ?string $nextActionDate): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE debt_collection_queue SET last_action_date = NOW(), next_action_date = ? WHERE id = ?'
        );
        $stmt->execute([$nextActionDate, $id]);
    }

    public function closeQueue(int $id, string $resolution, ?string $note = null): void
    {
        $this->updateQueueStatus($id, 'closed', $resolution, $note);
    }

    public function countActiveByCollector(string $collectorId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM debt_collection_queue WHERE assigned_to = ? AND status IN ('new','active','hold')"
        );
        $stmt->execute([$collectorId]);
        return (int)$stmt->fetchColumn();
    }

    public function queueExistsForInvoice(int $invoiceId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM debt_collection_queue WHERE invoice_id = ? AND status NOT IN ('closed')"
        );
        $stmt->execute([$invoiceId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ── Activities ──

    public function findActivityById(int $id): ?Activity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM debt_collection_activities WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? Activity::fromRow($row) : null;
    }

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

    public function deleteActivity(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM debt_collection_activities WHERE id = ?');
        $stmt->execute([$id]);
    }

    // ── Promises ──

    public function findPromiseById(int $id): ?Promise
    {
        $stmt = $this->pdo->prepare('SELECT * FROM debt_collection_promises WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? Promise::fromRow($row) : null;
    }

    public function findPromisesByQueue(int $queueId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.* FROM debt_collection_promises p WHERE p.queue_id = ? ORDER BY p.created_at DESC'
        );
        $stmt->execute([$queueId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

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

    public function incrementPromiseBrokenCount(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE debt_collection_promises SET broken_count = broken_count + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    // ── Approvals ──

    public function findApprovalById(int $id): ?Approval
    {
        $stmt = $this->pdo->prepare('SELECT * FROM debt_collection_approvals WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? Approval::fromRow($row) : null;
    }

    public function findApprovalsByQueue(int $queueId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM debt_collection_approvals WHERE queue_id = ? ORDER BY id DESC'
        );
        $stmt->execute([$queueId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

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

    public function updateApprovalOverallStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE debt_collection_approvals SET overall_status = ?, resolved_at = IF(? IN ("approved","rejected"), NOW(), resolved_at) WHERE id = ?'
        );
        $stmt->execute([$status, $status, $id]);
    }

    // ── Settlements ──

    public function findSettlementById(int $id): ?Settlement
    {
        $stmt = $this->pdo->prepare('SELECT * FROM debt_collection_settlements WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? Settlement::fromRow($row) : null;
    }

    public function findSettlementByQueue(int $queueId): ?Settlement
    {
        $stmt = $this->pdo->prepare('SELECT * FROM debt_collection_settlements WHERE queue_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$queueId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? Settlement::fromRow($row) : null;
    }

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

    public function updateSettlementPayment(int $id, float $amount, string $paymentDate): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE debt_collection_settlements SET amount_paid = amount_paid + ?, last_payment_date = ? WHERE id = ?'
        );
        $stmt->execute([$amount, $paymentDate, $id]);
    }

    public function updateSettlementStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE debt_collection_settlements SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    // ── Stats ──

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

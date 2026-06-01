<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\QueueEntry;
use Accounting\Domain\Model\Activity;
use Accounting\Domain\Model\Promise;
use Accounting\Domain\Model\Approval;
use Accounting\Domain\Model\Settlement;

interface DebtCollectionRepositoryInterface
{
    // ── Queue ──
    public function findQueueById(int $id): ?QueueEntry;
    public function findQueueByInvoice(int $invoiceId): ?QueueEntry;
    public function findQueues(array $filters = []): array;
    public function findActiveQueuesByCollector(string $collectorId): array;
    public function findUnassignedQueues(): array;
    public function saveQueue(QueueEntry $entry): int;
    public function updateQueueStatus(int $id, string $status, ?string $resolution = null, ?string $resolutionNote = null): void;
    public function updateQueueAssignment(int $id, string $collectorId): void;
    public function updateQueueHold(int $id, ?string $reason, ?string $holdUntil, int $holdCount): void;
    public function updateQueuePriority(int $id, int $priority): void;
    public function updateQueueEscalation(int $id, int $level): void;
    public function updateQueueLastAction(int $id, ?string $nextActionDate): void;
    public function closeQueue(int $id, string $resolution, ?string $note = null): void;
    public function countActiveByCollector(string $collectorId): int;
    public function queueExistsForInvoice(int $invoiceId): bool;

    // ── Activities ──
    public function findActivityById(int $id): ?Activity;
    public function findActivitiesByQueue(int $queueId): array;
    public function saveActivity(Activity $activity): int;
    public function deleteActivity(int $id): void;

    // ── Promises ──
    public function findPromiseById(int $id): ?Promise;
    public function findPromisesByQueue(int $queueId): array;
    public function findActivePromisesDueToday(): array;
    public function findActivePromisesByCustomer(string $customerId): array;
    public function savePromise(Promise $promise): int;
    public function updatePromiseStatus(int $id, string $status, ?string $keptDate = null, ?string $brokenReason = null): void;
    public function incrementPromiseBrokenCount(int $id): void;

    // ── Approvals ──
    public function findApprovalById(int $id): ?Approval;
    public function findApprovalsByQueue(int $queueId): array;
    public function findPendingApprovals(?string $approverId = null): array;
    public function saveApproval(Approval $approval): int;
    public function updateApprovalLevel(int $id, int $level, string $approver, string $status, ?string $note = null): void;
    public function updateApprovalOverallStatus(int $id, string $status): void;

    // ── Settlements ──
    public function findSettlementById(int $id): ?Settlement;
    public function findSettlementByQueue(int $queueId): ?Settlement;
    public function findActiveSettlements(): array;
    public function saveSettlement(Settlement $settlement): int;
    public function updateSettlementPayment(int $id, float $amount, string $paymentDate): void;
    public function updateSettlementStatus(int $id, string $status): void;

    // ── Stats ──
    public function getQueueStats(): array;
    public function getCollectorStats(string $collectorId): array;
}

<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\QueueEntry;
use Accounting\Domain\Model\Activity;
use Accounting\Domain\Model\Promise;
use Accounting\Domain\Model\Approval;
use Accounting\Domain\Model\Settlement;

/**
 * Giao diện repository cho quản lý thu hồi công nợ.
 *
 * Cung cấp các phương thức quản lý hàng chờ xử lý (queue), hoạt động thu hồi,
 * cam kết thanh toán, phê duyệt và phương án thanh lý công nợ.
 */
interface DebtCollectionRepositoryInterface
{
    // ── Queue ──

    /**
     * Tìm queue entry theo ID.
     *
     * @param int $id ID của queue entry
     * @return QueueEntry|null Đối tượng QueueEntry nếu tìm thấy, null nếu không
     */
    public function findQueueById(int $id): ?QueueEntry;

    /**
     * Tìm queue entry theo hóa đơn.
     *
     * @param int $invoiceId ID của hóa đơn
     * @return QueueEntry|null Đối tượng QueueEntry nếu tìm thấy, null nếu không
     */
    public function findQueueByInvoice(int $invoiceId): ?QueueEntry;

    /**
     * Tìm danh sách queue theo bộ lọc.
     *
     * @param array $filters Mảng bộ lọc (status, collector_id, ...)
     * @return QueueEntry[] Danh sách queue phù hợp
     */
    public function findQueues(array $filters = []): array;

    /**
     * Tìm queue đang hoạt động theo nhân viên thu hồi.
     *
     * @param string $collectorId ID của nhân viên thu hồi
     * @return QueueEntry[] Danh sách queue
     */
    public function findActiveQueuesByCollector(string $collectorId): array;

    /**
     * Tìm queue chưa được phân công.
     *
     * @return QueueEntry[] Danh sách queue chưa phân công
     */
    public function findUnassignedQueues(): array;

    /**
     * Lưu queue entry mới.
     *
     * @param QueueEntry $entry Đối tượng QueueEntry cần lưu
     * @return int ID của queue entry sau khi lưu
     */
    public function saveQueue(QueueEntry $entry): int;

    /**
     * Cập nhật trạng thái queue.
     *
     * @param int $id ID của queue
     * @param string $status Trạng thái mới
     * @param string|null $resolution Kết quả xử lý
     * @param string|null $resolutionNote Ghi chú kết quả
     * @return void
     */
    public function updateQueueStatus(int $id, string $status, ?string $resolution = null, ?string $resolutionNote = null): void;

    /**
     * Cập nhật phân công nhân viên thu hồi.
     *
     * @param int $id ID của queue
     * @param string $collectorId ID nhân viên thu hồi
     * @return void
     */
    public function updateQueueAssignment(int $id, string $collectorId): void;

    /**
     * Cập nhật trạng thái tạm giữ queue.
     *
     * @param int $id ID của queue
     * @param string|null $reason Lý do tạm giữ
     * @param string|null $holdUntil Ngày hết hạn tạm giữ
     * @param int $holdCount Số lần tạm giữ
     * @return void
     */
    public function updateQueueHold(int $id, ?string $reason, ?string $holdUntil, int $holdCount): void;

    /**
     * Cập nhật mức ưu tiên queue.
     *
     * @param int $id ID của queue
     * @param int $priority Mức ưu tiên mới
     * @return void
     */
    public function updateQueuePriority(int $id, int $priority): void;

    /**
     * Cập nhật mức leo thang queue.
     *
     * @param int $id ID của queue
     * @param int $level Mức leo thang
     * @return void
     */
    public function updateQueueEscalation(int $id, int $level): void;

    /**
     * Cập nhật ngày hành động cuối cùng.
     *
     * @param int $id ID của queue
     * @param string|null $nextActionDate Ngày hành động tiếp theo
     * @return void
     */
    public function updateQueueLastAction(int $id, ?string $nextActionDate): void;

    /**
     * Đóng queue với kết quả xử lý.
     *
     * @param int $id ID của queue
     * @param string $resolution Kết quả xử lý
     * @param string|null $note Ghi chú
     * @return void
     */
    public function closeQueue(int $id, string $resolution, ?string $note = null): void;

    /**
     * Đếm số queue đang hoạt động của nhân viên thu hồi.
     *
     * @param string $collectorId ID của nhân viên thu hồi
     * @return int Số lượng queue đang hoạt động
     */
    public function countActiveByCollector(string $collectorId): int;

    /**
     * Kiểm tra queue đã tồn tại cho hóa đơn chưa.
     *
     * @param int $invoiceId ID của hóa đơn
     * @return bool True nếu đã tồn tại, false nếu chưa
     */
    public function queueExistsForInvoice(int $invoiceId): bool;

    // ── Activities ──

    /**
     * Tìm hoạt động theo ID.
     *
     * @param int $id ID của hoạt động
     * @return Activity|null Đối tượng Activity nếu tìm thấy, null nếu không
     */
    public function findActivityById(int $id): ?Activity;

    /**
     * Tìm danh sách hoạt động theo queue.
     *
     * @param int $queueId ID của queue
     * @return Activity[] Danh sách hoạt động
     */
    public function findActivitiesByQueue(int $queueId): array;

    /**
     * Lưu hoạt động mới.
     *
     * @param Activity $activity Đối tượng Activity cần lưu
     * @return int ID của hoạt động sau khi lưu
     */
    public function saveActivity(Activity $activity): int;

    /**
     * Xóa hoạt động theo ID.
     *
     * @param int $id ID của hoạt động cần xóa
     * @return void
     */
    public function deleteActivity(int $id): void;

    // ── Promises ──

    /**
     * Tìm cam kết thanh toán theo ID.
     *
     * @param int $id ID của cam kết
     * @return Promise|null Đối tượng Promise nếu tìm thấy, null nếu không
     */
    public function findPromiseById(int $id): ?Promise;

    /**
     * Tìm danh sách cam kết theo queue.
     *
     * @param int $queueId ID của queue
     * @return Promise[] Danh sách cam kết
     */
    public function findPromisesByQueue(int $queueId): array;

    /**
     * Tìm cam kết đang hoạt động đến hạn hôm nay.
     *
     * @return Promise[] Danh sách cam kết đến hạn
     */
    public function findActivePromisesDueToday(): array;

    /**
     * Tìm cam kết đang hoạt động theo khách hàng.
     *
     * @param string $customerId ID của khách hàng
     * @return Promise[] Danh sách cam kết
     */
    public function findActivePromisesByCustomer(string $customerId): array;

    /**
     * Lưu cam kết thanh toán mới.
     *
     * @param Promise $promise Đối tượng Promise cần lưu
     * @return int ID của cam kết sau khi lưu
     */
    public function savePromise(Promise $promise): int;

    /**
     * Cập nhật trạng thái cam kết.
     *
     * @param int $id ID của cam kết
     * @param string $status Trạng thái mới
     * @param string|null $keptDate Ngày thực hiện cam kết
     * @param string|null $brokenReason Lý do không thực hiện
     * @return void
     */
    public function updatePromiseStatus(int $id, string $status, ?string $keptDate = null, ?string $brokenReason = null): void;

    /**
     * Tăng số lần cam kết bị phá vỡ.
     *
     * @param int $id ID của cam kết
     * @return void
     */
    public function incrementPromiseBrokenCount(int $id): void;

    // ── Approvals ──

    /**
     * Tìm phê duyệt theo ID.
     *
     * @param int $id ID của phê duyệt
     * @return Approval|null Đối tượng Approval nếu tìm thấy, null nếu không
     */
    public function findApprovalById(int $id): ?Approval;

    /**
     * Tìm danh sách phê duyệt theo queue.
     *
     * @param int $queueId ID của queue
     * @return Approval[] Danh sách phê duyệt
     */
    public function findApprovalsByQueue(int $queueId): array;

    /**
     * Tìm phê duyệt đang chờ.
     *
     * @param string|null $approverId ID người phê duyệt (nếu null thì lấy tất cả)
     * @return Approval[] Danh sách phê duyệt đang chờ
     */
    public function findPendingApprovals(?string $approverId = null): array;

    /**
     * Lưu phê duyệt mới.
     *
     * @param Approval $approval Đối tượng Approval cần lưu
     * @return int ID của phê duyệt sau khi lưu
     */
    public function saveApproval(Approval $approval): int;

    /**
     * Cập nhật cấp phê duyệt.
     *
     * @param int $id ID của phê duyệt
     * @param int $level Cấp phê duyệt
     * @param string $approver Người phê duyệt
     * @param string $status Trạng thái phê duyệt
     * @param string|null $note Ghi chú
     * @return void
     */
    public function updateApprovalLevel(int $id, int $level, string $approver, string $status, ?string $note = null): void;

    /**
     * Cập nhật trạng thái tổng thể phê duyệt.
     *
     * @param int $id ID của phê duyệt
     * @param string $status Trạng thái tổng thể
     * @return void
     */
    public function updateApprovalOverallStatus(int $id, string $status): void;

    // ── Settlements ──

    /**
     * Tìm phương án thanh lý theo ID.
     *
     * @param int $id ID của phương án thanh lý
     * @return Settlement|null Đối tượng Settlement nếu tìm thấy, null nếu không
     */
    public function findSettlementById(int $id): ?Settlement;

    /**
     * Tìm phương án thanh lý theo queue.
     *
     * @param int $queueId ID của queue
     * @return Settlement|null Đối tượng Settlement nếu tìm thấy, null nếu không
     */
    public function findSettlementByQueue(int $queueId): ?Settlement;

    /**
     * Tìm phương án thanh lý đang hoạt động.
     *
     * @return Settlement[] Danh sách phương án thanh lý
     */
    public function findActiveSettlements(): array;

    /**
     * Lưu phương án thanh lý mới.
     *
     * @param Settlement $settlement Đối tượng Settlement cần lưu
     * @return int ID của phương án thanh lý sau khi lưu
     */
    public function saveSettlement(Settlement $settlement): int;

    /**
     * Cập nhật thông tin thanh toán của phương án thanh lý.
     *
     * @param int $id ID của phương án thanh lý
     * @param float $amount Số tiền thanh toán
     * @param string $paymentDate Ngày thanh toán
     * @return void
     */
    public function updateSettlementPayment(int $id, float $amount, string $paymentDate): void;

    /**
     * Cập nhật trạng thái phương án thanh lý.
     *
     * @param int $id ID của phương án thanh lý
     * @param string $status Trạng thái mới
     * @return void
     */
    public function updateSettlementStatus(int $id, string $status): void;

    // ── Stats ──

    /**
     * Lấy thống kê queue.
     *
     * @return array Mảng thống kê (tổng số, theo trạng thái, ...)
     */
    public function getQueueStats(): array;

    /**
     * Lấy thống kê của nhân viên thu hồi.
     *
     * @param string $collectorId ID của nhân viên thu hồi
     * @return array Mảng thống kê của nhân viên
     */
    public function getCollectorStats(string $collectorId): array;
}

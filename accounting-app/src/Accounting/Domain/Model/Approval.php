<?php
namespace Accounting\Domain\Model;

/**
 * Phê duyệt — Quy trình phê duyệt nhiều cấp cho các yêu cầu trong hàng đợi.
 *
 * Mỗi Approval có thể có tối đa 3 cấp phê duyệt, mỗi cấp có
 * approver, status và note riêng. Overall status là tổng hợp
 * của tất cả các cấp.
 *
 * NGHIỆP VỤ:
 * - $approvalType: 'settlement', 'credit_limit', 'discount', 'write_off'
 * - Mỗi cấp có thể: 'pending', 'approved', 'rejected'
 * - Overall: 'pending' (nếu có cấp nào pending), 'approved' (all approved),
 *   'rejected' (nếu có cấp nào rejected)
 */
class Approval
{
    private ?int $id;
    private int $queueId;
    private string $approvalType;
    private string $requestedBy;
    private float $amount;
    private string $requestNote;
    private ?float $settlementPercent;
    private ?float $settlementAmount;
    private ?string $level1Approver;
    private string $level1Status;
    private ?string $level1Note;
    private ?string $level2Approver;
    private string $level2Status;
    private ?string $level2Note;
    private ?string $level3Approver;
    private string $level3Status;
    private ?string $level3Note;
    private string $overallStatus;
    private ?string $resolvedAt;

    /**
     * Khởi tạo phê duyệt.
     *
     * @param int $queueId ID hàng đợi đòi nợ
     * @param string $approvalType Loại phê duyệt
     * @param string $requestedBy Người yêu cầu
     * @param float $amount Số tiền
     * @param string $requestNote Ghi chú yêu cầu
     * @param float|null $settlementPercent Tỷ lệ thanh lý (%)
     * @param float|null $settlementAmount Số tiền thanh lý
     * @param int|null $id Định danh
     */
    public function __construct(
        int $queueId,
        string $approvalType,
        string $requestedBy,
        float $amount,
        string $requestNote,
        ?float $settlementPercent = null,
        ?float $settlementAmount = null,
        ?int $id = null
    ) {
        $this->queueId = $queueId;
        $this->approvalType = $approvalType;
        $this->requestedBy = $requestedBy;
        $this->amount = $amount;
        $this->requestNote = $requestNote;
        $this->settlementPercent = $settlementPercent;
        $this->settlementAmount = $settlementAmount;
        $this->id = $id;
        $this->level1Status = 'pending';
        $this->level2Status = 'pending';
        $this->level3Status = 'pending';
        $this->overallStatus = 'pending';
    }

    /** @return int|null Định danh phê duyệt */
    public function getId(): ?int { return $this->id; }

    /** @return int ID hàng đợi đòi nợ */
    public function getQueueId(): int { return $this->queueId; }

    /** @return string Loại phê duyệt */
    public function getApprovalType(): string { return $this->approvalType; }

    /** @return string Người yêu cầu */
    public function getRequestedBy(): string { return $this->requestedBy; }

    /** @return float Số tiền */
    public function getAmount(): float { return $this->amount; }

    /** @return string Ghi chú yêu cầu */
    public function getRequestNote(): string { return $this->requestNote; }

    /** @return float|null Tỷ lệ thanh lý (%) */
    public function getSettlementPercent(): ?float { return $this->settlementPercent; }

    /** @return float|null Số tiền thanh lý */
    public function getSettlementAmount(): ?float { return $this->settlementAmount; }

    /** @return string|null Người phê duyệt cấp 1 */
    public function getLevel1Approver(): ?string { return $this->level1Approver; }

    /** @return string Trạng thái phê duyệt cấp 1 */
    public function getLevel1Status(): string { return $this->level1Status; }

    /** @return string|null Ghi chú cấp 1 */
    public function getLevel1Note(): ?string { return $this->level1Note; }

    /** @return string|null Người phê duyệt cấp 2 */
    public function getLevel2Approver(): ?string { return $this->level2Approver; }

    /** @return string Trạng thái phê duyệt cấp 2 */
    public function getLevel2Status(): string { return $this->level2Status; }

    /** @return string|null Ghi chú cấp 2 */
    public function getLevel2Note(): ?string { return $this->level2Note; }

    /** @return string|null Người phê duyệt cấp 3 */
    public function getLevel3Approver(): ?string { return $this->level3Approver; }

    /** @return string Trạng thái phê duyệt cấp 3 */
    public function getLevel3Status(): string { return $this->level3Status; }

    /** @return string|null Ghi chú cấp 3 */
    public function getLevel3Note(): ?string { return $this->level3Note; }

    /** @return string Trạng thái tổng thể */
    public function getOverallStatus(): string { return $this->overallStatus; }

    /** @return string|null Thời điểm giải quyết */
    public function getResolvedAt(): ?string { return $this->resolvedAt; }

    /** @param string|null $v Người phê duyệt cấp 1 */
    public function setLevel1Approver(?string $v): void { $this->level1Approver = $v; }

    /** @param string $v Trạng thái cấp 1 */
    public function setLevel1Status(string $v): void { $this->level1Status = $v; }

    /** @param string|null $v Ghi chú cấp 1 */
    public function setLevel1Note(?string $v): void { $this->level1Note = $v; }

    /** @param string|null $v Người phê duyệt cấp 2 */
    public function setLevel2Approver(?string $v): void { $this->level2Approver = $v; }

    /** @param string $v Trạng thái cấp 2 */
    public function setLevel2Status(string $v): void { $this->level2Status = $v; }

    /** @param string|null $v Ghi chú cấp 2 */
    public function setLevel2Note(?string $v): void { $this->level2Note = $v; }

    /** @param string|null $v Người phê duyệt cấp 3 */
    public function setLevel3Approver(?string $v): void { $this->level3Approver = $v; }

    /** @param string $v Trạng thái cấp 3 */
    public function setLevel3Status(string $v): void { $this->level3Status = $v; }

    /** @param string|null $v Ghi chú cấp 3 */
    public function setLevel3Note(?string $v): void { $this->level3Note = $v; }

    /** @param string $v Trạng thái tổng thể */
    public function setOverallStatus(string $v): void { $this->overallStatus = $v; }

    /** @param string|null $v Thời điểm giải quyết */
    public function setResolvedAt(?string $v): void { $this->resolvedAt = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu phê duyệt dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'queue_id' => $this->queueId,
            'approval_type' => $this->approvalType,
            'requested_by' => $this->requestedBy,
            'amount' => $this->amount,
            'request_note' => $this->requestNote,
            'settlement_percent' => $this->settlementPercent,
            'settlement_amount' => $this->settlementAmount,
            'level_1_approver' => $this->level1Approver,
            'level_1_status' => $this->level1Status,
            'level_1_note' => $this->level1Note,
            'level_2_approver' => $this->level2Approver,
            'level_2_status' => $this->level2Status,
            'level_2_note' => $this->level2Note,
            'level_3_approver' => $this->level3Approver,
            'level_3_status' => $this->level3Status,
            'level_3_note' => $this->level3Note,
            'overall_status' => $this->overallStatus,
            'resolved_at' => $this->resolvedAt,
        ];
    }

    /**
     * Tạo Approval từ một dòng dữ liệu database.
     *
     * @param array $row Dữ liệu từ database
     * @return self
     */
    public static function fromRow(array $row): self
    {
        $a = new self(
            (int)$row['queue_id'],
            $row['approval_type'],
            $row['requested_by'],
            (float)$row['amount'],
            $row['request_note'],
            isset($row['settlement_percent']) ? (float)$row['settlement_percent'] : null,
            isset($row['settlement_amount']) ? (float)$row['settlement_amount'] : null,
            isset($row['id']) ? (int)$row['id'] : null
        );
        $a->level1Approver = $row['level_1_approver'] ?? null;
        $a->level1Status = $row['level_1_status'] ?? 'pending';
        $a->level1Note = $row['level_1_note'] ?? null;
        $a->level2Approver = $row['level_2_approver'] ?? null;
        $a->level2Status = $row['level_2_status'] ?? 'pending';
        $a->level2Note = $row['level_2_note'] ?? null;
        $a->level3Approver = $row['level_3_approver'] ?? null;
        $a->level3Status = $row['level_3_status'] ?? 'pending';
        $a->level3Note = $row['level_3_note'] ?? null;
        $a->overallStatus = $row['overall_status'] ?? 'pending';
        $a->resolvedAt = $row['resolved_at'] ?? null;
        return $a;
    }
}

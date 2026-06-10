<?php
namespace Accounting\Domain\Model;

/**
 * Cam kết thanh toán — Lời hứa thanh toán từ khách hàng trong quá trình đòi nợ.
 *
 * Mỗi Promise được tạo từ một Activity (cuộc gọi, gặp mặt...) khi khách hàng
 * cam kết sẽ thanh toán một khoản tiền vào một ngày cụ thể.
 *
 * NGHIỆP VỤ:
 * - $status: 'active' (đang hiệu lực), 'kept' (đã giữ lời), 'broken' (thất hứa)
 * - $brokenCount: số lần thất hứa (để đánh giá độ tin cậy)
 * - Khi promise bị broken, có thể leo thang (escalation)
 */
class Promise
{
    private ?int $id;
    private int $queueId;
    private ?int $activityId;
    private string $promiseDate;
    private float $promiseAmount;
    private ?string $promiseNote;
    private string $status;
    private ?string $keptDate;
    private ?string $brokenReason;
    private int $brokenCount;
    private string $createdBy;

    /**
     * Khởi tạo cam kết thanh toán.
     *
     * @param int $queueId ID hàng đợi
     * @param string $promiseDate Ngày cam kết
     * @param float $promiseAmount Số tiền cam kết
     * @param string $createdBy Người tạo
     * @param int|null $activityId ID hoạt động liên quan
     * @param string|null $promiseNote Ghi chú cam kết
     * @param string $status Trạng thái: 'active', 'kept', 'broken'
     * @param int $brokenCount Số lần thất hứa
     * @param int|null $id Định danh
     */
    public function __construct(
        int $queueId,
        string $promiseDate,
        float $promiseAmount,
        string $createdBy,
        ?int $activityId = null,
        ?string $promiseNote = null,
        string $status = 'active',
        int $brokenCount = 0,
        ?int $id = null
    ) {
        $this->queueId = $queueId;
        $this->promiseDate = $promiseDate;
        $this->promiseAmount = $promiseAmount;
        $this->createdBy = $createdBy;
        $this->activityId = $activityId;
        $this->promiseNote = $promiseNote;
        $this->status = $status;
        $this->brokenCount = $brokenCount;
        $this->id = $id;
    }

    /** @return int|null Định danh */
    public function getId(): ?int { return $this->id; }

    /** @return int ID hàng đợi */
    public function getQueueId(): int { return $this->queueId; }

    /** @return int|null ID hoạt động liên quan */
    public function getActivityId(): ?int { return $this->activityId; }

    /** @return string Ngày cam kết */
    public function getPromiseDate(): string { return $this->promiseDate; }

    /** @return float Số tiền cam kết */
    public function getPromiseAmount(): float { return $this->promiseAmount; }

    /** @return string|null Ghi chú cam kết */
    public function getPromiseNote(): ?string { return $this->promiseNote; }

    /** @return string Trạng thái */
    public function getStatus(): string { return $this->status; }

    /** @return string|null Ngày giữ lời hứa (thanh toán thực tế) */
    public function getKeptDate(): ?string { return $this->keptDate; }

    /** @return string|null Lý do thất hứa */
    public function getBrokenReason(): ?string { return $this->brokenReason; }

    /** @return int Số lần thất hứa */
    public function getBrokenCount(): int { return $this->brokenCount; }

    /** @return string Người tạo */
    public function getCreatedBy(): string { return $this->createdBy; }

    /** @param string $v Trạng thái mới */
    public function setStatus(string $v): void { $this->status = $v; }

    /** @param string|null $v Ngày giữ lời hứa mới */
    public function setKeptDate(?string $v): void { $this->keptDate = $v; }

    /** @param string|null $v Lý do thất hứa mới */
    public function setBrokenReason(?string $v): void { $this->brokenReason = $v; }

    /** @param int $v Số lần thất hứa mới */
    public function setBrokenCount(int $v): void { $this->brokenCount = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu cam kết dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'queue_id' => $this->queueId,
            'activity_id' => $this->activityId,
            'promise_date' => $this->promiseDate,
            'promise_amount' => $this->promiseAmount,
            'promise_note' => $this->promiseNote,
            'status' => $this->status,
            'kept_date' => $this->keptDate,
            'broken_reason' => $this->brokenReason,
            'broken_count' => $this->brokenCount,
            'created_by' => $this->createdBy,
        ];
    }

    /**
     * Tạo Promise từ một dòng dữ liệu database.
     *
     * @param array $row Dữ liệu từ database
     * @return self
     */
    public static function fromRow(array $row): self
    {
        $p = new self(
            (int)$row['queue_id'],
            $row['promise_date'],
            (float)$row['promise_amount'],
            $row['created_by'],
            isset($row['activity_id']) ? (int)$row['activity_id'] : null,
            $row['promise_note'] ?? null,
            $row['status'] ?? 'active',
            (int)($row['broken_count'] ?? 0),
            isset($row['id']) ? (int)$row['id'] : null
        );
        $p->keptDate = $row['kept_date'] ?? null;
        $p->brokenReason = $row['broken_reason'] ?? null;
        return $p;
    }
}

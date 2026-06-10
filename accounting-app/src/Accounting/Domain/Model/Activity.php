<?php
namespace Accounting\Domain\Model;

/**
 * Hoạt động — Ghi nhận các tương tác với khách hàng trong hàng đợi đòi nợ.
 *
 * Mỗi Activity là một lần tương tác (gọi điện, gửi email, gặp trực tiếp...)
 * với khách hàng nhằm thu hồi công nợ. Hoạt động được ghi nhận trong
 * QueueEntry để theo dõi lịch sử đòi nợ.
 *
 * NGHIỆP VỤ:
 * - $activityType: 'call', 'email', 'meeting', 'visit', 'reminder', 'negotiation'
 * - $result: kết quả tương tác (VD: "Khách hẹn trả 15/06")
 * - $promiseDate/$promiseAmount: cam kết thanh toán từ khách hàng
 * - $durationMinutes: thời lượng tương tác
 */
class Activity
{
    private ?int $id;
    private int $queueId;
    private string $activityType;
    private string $summary;
    private ?string $detail;
    private ?string $contactPerson;
    private ?string $contactPhone;
    private ?string $result;
    private ?string $promiseDate;
    private ?float $promiseAmount;
    private ?int $durationMinutes;
    private ?string $attachmentPath;
    private string $createdBy;
    private ?string $createdAt;

    /**
     * Khởi tạo hoạt động.
     *
     * @param int $queueId ID hàng đợi đòi nợ
     * @param string $activityType Loại hoạt động: 'call', 'email', 'meeting', 'visit', 'reminder', 'negotiation'
     * @param string $summary Tóm tắt nội dung
     * @param string $createdBy Người tạo
     * @param string|null $detail Chi tiết hoạt động
     * @param string|null $contactPerson Người liên hệ
     * @param string|null $contactPhone Số điện thoại liên hệ
     * @param string|null $result Kết quả tương tác
     * @param string|null $promiseDate Ngày hứa hẹn thanh toán
     * @param float|null $promiseAmount Số tiền cam kết
     * @param int|null $durationMinutes Thời lượng (phút)
     * @param string|null $attachmentPath Đường dẫn file đính kèm
     * @param int|null $id Định danh (null khi tạo mới)
     */
    public function __construct(
        int $queueId,
        string $activityType,
        string $summary,
        string $createdBy,
        ?string $detail = null,
        ?string $contactPerson = null,
        ?string $contactPhone = null,
        ?string $result = null,
        ?string $promiseDate = null,
        ?float $promiseAmount = null,
        ?int $durationMinutes = null,
        ?string $attachmentPath = null,
        ?int $id = null
    ) {
        $this->queueId = $queueId;
        $this->activityType = $activityType;
        $this->summary = $summary;
        $this->createdBy = $createdBy;
        $this->detail = $detail;
        $this->contactPerson = $contactPerson;
        $this->contactPhone = $contactPhone;
        $this->result = $result;
        $this->promiseDate = $promiseDate;
        $this->promiseAmount = $promiseAmount;
        $this->durationMinutes = $durationMinutes;
        $this->attachmentPath = $attachmentPath;
        $this->id = $id;
    }

    /** @return int|null Định danh hoạt động */
    public function getId(): ?int { return $this->id; }

    /** @return int ID hàng đợi đòi nợ */
    public function getQueueId(): int { return $this->queueId; }

    /** @return string Loại hoạt động */
    public function getActivityType(): string { return $this->activityType; }

    /** @return string Tóm tắt nội dung */
    public function getSummary(): string { return $this->summary; }

    /** @return string|null Chi tiết hoạt động */
    public function getDetail(): ?string { return $this->detail; }

    /** @return string|null Người liên hệ */
    public function getContactPerson(): ?string { return $this->contactPerson; }

    /** @return string|null Số điện thoại liên hệ */
    public function getContactPhone(): ?string { return $this->contactPhone; }

    /** @return string|null Kết quả tương tác */
    public function getResult(): ?string { return $this->result; }

    /** @return string|null Ngày hứa hẹn thanh toán */
    public function getPromiseDate(): ?string { return $this->promiseDate; }

    /** @return float|null Số tiền cam kết */
    public function getPromiseAmount(): ?float { return $this->promiseAmount; }

    /** @return int|null Thời lượng (phút) */
    public function getDurationMinutes(): ?int { return $this->durationMinutes; }

    /** @return string|null Đường dẫn file đính kèm */
    public function getAttachmentPath(): ?string { return $this->attachmentPath; }

    /** @return string Người tạo */
    public function getCreatedBy(): string { return $this->createdBy; }

    /** @return string|null Thời điểm tạo */
    public function getCreatedAt(): ?string { return $this->createdAt; }

    /** @param int|null $v Định danh hoạt động */
    public function setId(?int $v): void { $this->id = $v; }

    /** @param string|null $v Thời điểm tạo */
    public function setCreatedAt(?string $v): void { $this->createdAt = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu hoạt động dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'queue_id' => $this->queueId,
            'activity_type' => $this->activityType,
            'summary' => $this->summary,
            'detail' => $this->detail,
            'contact_person' => $this->contactPerson,
            'contact_phone' => $this->contactPhone,
            'result' => $this->result,
            'promise_date' => $this->promiseDate,
            'promise_amount' => $this->promiseAmount,
            'duration_minutes' => $this->durationMinutes,
            'attachment_path' => $this->attachmentPath,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * Tạo Activity từ một dòng dữ liệu database.
     *
     * @param array $row Dữ liệu từ database
     * @return self
     */
    public static function fromRow(array $row): self
    {
        $a = new self(
            (int)$row['queue_id'],
            $row['activity_type'],
            $row['summary'],
            $row['created_by'],
            $row['detail'] ?? null,
            $row['contact_person'] ?? null,
            $row['contact_phone'] ?? null,
            $row['result'] ?? null,
            $row['promise_date'] ?? null,
            isset($row['promise_amount']) ? (float)$row['promise_amount'] : null,
            isset($row['duration_minutes']) ? (int)$row['duration_minutes'] : null,
            $row['attachment_path'] ?? null,
            isset($row['id']) ? (int)$row['id'] : null
        );
        $a->createdAt = $row['created_at'] ?? null;
        return $a;
    }
}

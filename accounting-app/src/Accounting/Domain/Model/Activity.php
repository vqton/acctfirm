<?php
namespace Accounting\Domain\Model;

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

    public function getId(): ?int { return $this->id; }
    public function getQueueId(): int { return $this->queueId; }
    public function getActivityType(): string { return $this->activityType; }
    public function getSummary(): string { return $this->summary; }
    public function getDetail(): ?string { return $this->detail; }
    public function getContactPerson(): ?string { return $this->contactPerson; }
    public function getContactPhone(): ?string { return $this->contactPhone; }
    public function getResult(): ?string { return $this->result; }
    public function getPromiseDate(): ?string { return $this->promiseDate; }
    public function getPromiseAmount(): ?float { return $this->promiseAmount; }
    public function getDurationMinutes(): ?int { return $this->durationMinutes; }
    public function getAttachmentPath(): ?string { return $this->attachmentPath; }
    public function getCreatedBy(): string { return $this->createdBy; }
    public function getCreatedAt(): ?string { return $this->createdAt; }

    public function setId(?int $v): void { $this->id = $v; }
    public function setCreatedAt(?string $v): void { $this->createdAt = $v; }

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

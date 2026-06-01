<?php
namespace Accounting\Domain\Model;

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

    public function getId(): ?int { return $this->id; }
    public function getQueueId(): int { return $this->queueId; }
    public function getActivityId(): ?int { return $this->activityId; }
    public function getPromiseDate(): string { return $this->promiseDate; }
    public function getPromiseAmount(): float { return $this->promiseAmount; }
    public function getPromiseNote(): ?string { return $this->promiseNote; }
    public function getStatus(): string { return $this->status; }
    public function getKeptDate(): ?string { return $this->keptDate; }
    public function getBrokenReason(): ?string { return $this->brokenReason; }
    public function getBrokenCount(): int { return $this->brokenCount; }
    public function getCreatedBy(): string { return $this->createdBy; }

    public function setStatus(string $v): void { $this->status = $v; }
    public function setKeptDate(?string $v): void { $this->keptDate = $v; }
    public function setBrokenReason(?string $v): void { $this->brokenReason = $v; }
    public function setBrokenCount(int $v): void { $this->brokenCount = $v; }

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

<?php
namespace Accounting\Domain\Model;

/**
 * Ky luong — dai dien cho mot thang/ky tinh luong.
 *
 * NGHIEP VU:
 * - Moi thang mo mot ky luong, co start_date va end_date
 * - status: open (dang mo), processing (dang tinh), closed (da dong/khoa so)
 * - period_code: dinh dang YYYYMM (VD: 202605)
 * - Khi ky luong closed, khong duoc them/sua bang luong
 *
 * QUAN HE: 1 ky luong -> N bang luong (payroll_entries)
 */
class PayrollPeriod
{
    private string $id;
    private string $periodCode;
    private string $name;
    private \DateTimeImmutable $startDate;
    private \DateTimeImmutable $endDate;
    private string $status;
    private ?string $createdBy;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $periodCode,
        string $name,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        string $status = 'open',
        ?string $createdBy = null
    ) {
        $this->id = $id;
        $this->periodCode = $periodCode;
        $this->name = $name;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->status = $status;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getPeriodCode(): string { return $this->periodCode; }
    public function getName(): string { return $this->name; }
    public function getStartDate(): \DateTimeImmutable { return $this->startDate; }
    public function getEndDate(): \DateTimeImmutable { return $this->endDate; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setName(string $v): void { $this->name = $v; }
    public function setStatus(string $v): void { $this->status = $v; }
    public function setStartDate(\DateTimeImmutable $v): void { $this->startDate = $v; }
    public function setEndDate(\DateTimeImmutable $v): void { $this->endDate = $v; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'period_code' => $this->periodCode,
            'name' => $this->name,
            'start_date' => $this->startDate->format('Y-m-d'),
            'end_date' => $this->endDate->format('Y-m-d'),
            'status' => $this->status,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

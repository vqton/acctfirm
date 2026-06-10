<?php
namespace Accounting\Domain\Model;

/**
 * Kỳ lương — Đại diện cho một tháng/kỳ tính lương.
 *
 * NGHIỆP VỤ:
 * - Mỗi tháng mở một kỳ lương, có start_date và end_date
 * - status: open (đang mở), processing (đang tính), closed (đã đóng/khóa sổ)
 * - period_code: định dạng YYYYMM (VD: 202605)
 * - Khi kỳ lương closed, không được thêm/sửa bảng lương
 *
 * QUAN HỆ: 1 kỳ lương -> N bảng lương (payroll_entries)
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

    /**
     * Khởi tạo kỳ lương.
     *
     * @param string $id Định danh
     * @param string $periodCode Mã kỳ (YYYYMM)
     * @param string $name Tên kỳ lương
     * @param \DateTimeImmutable $startDate Ngày bắt đầu
     * @param \DateTimeImmutable $endDate Ngày kết thúc
     * @param string $status Trạng thái: 'open', 'processing', 'closed'
     * @param string|null $createdBy Người tạo
     */
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

    /** @return string Định danh */
    public function getId(): string { return $this->id; }

    /** @return string Mã kỳ (YYYYMM) */
    public function getPeriodCode(): string { return $this->periodCode; }

    /** @return string Tên kỳ lương */
    public function getName(): string { return $this->name; }

    /** @return \DateTimeImmutable Ngày bắt đầu */
    public function getStartDate(): \DateTimeImmutable { return $this->startDate; }

    /** @return \DateTimeImmutable Ngày kết thúc */
    public function getEndDate(): \DateTimeImmutable { return $this->endDate; }

    /** @return string Trạng thái */
    public function getStatus(): string { return $this->status; }

    /** @return string|null Người tạo */
    public function getCreatedBy(): ?string { return $this->createdBy; }

    /** @return \DateTimeImmutable Thời điểm tạo */
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @param string $v Tên kỳ mới */
    public function setName(string $v): void { $this->name = $v; }

    /** @param string $v Trạng thái mới */
    public function setStatus(string $v): void { $this->status = $v; }

    /** @param \DateTimeImmutable $v Ngày bắt đầu mới */
    public function setStartDate(\DateTimeImmutable $v): void { $this->startDate = $v; }

    /** @param \DateTimeImmutable $v Ngày kết thúc mới */
    public function setEndDate(\DateTimeImmutable $v): void { $this->endDate = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu kỳ lương dạng mảng
     */
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

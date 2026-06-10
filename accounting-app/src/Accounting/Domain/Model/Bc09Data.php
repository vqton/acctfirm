<?php
namespace Accounting\Domain\Model;

/**
 * Dữ liệu BC09 — Giá trị thực tế của các chỉ tiêu trên Thuyết minh BCTC.
 *
 * Lưu dữ liệu nhập tay hoặc kết quả tự động tính cho từng chỉ tiêu BC09
 * theo từng kỳ kế toán. Hỗ trợ cả giá trị đầu năm và cuối kỳ.
 *
 * NGHIỆP VỤ:
 * - $sectionCode/$indicatorCode: xác định chỉ tiêu (liên kết Bc09Config)
 * - $yearStart: số đầu năm / $yearEnd: số cuối kỳ
 * - $isManual: true nếu nhập tay, false nếu tự động tính
 */
class Bc09Data
{
    /**
     * Khởi tạo dữ liệu BC09.
     *
     * @param int $id Định danh
     * @param int $periodId ID kỳ kế toán
     * @param string $sectionCode Mã phần
     * @param string $indicatorCode Mã chỉ tiêu
     * @param float $yearStart Giá trị đầu năm
     * @param float $yearEnd Giá trị cuối kỳ
     * @param string|null $noteText Ghi chú thuyết minh
     * @param bool $isManual Nhập tay (true) hay tự động (false)
     * @param int|null $createdBy Người tạo
     */
    public function __construct(
        private int $id,
        private int $periodId,
        private string $sectionCode,
        private string $indicatorCode,
        private float $yearStart,
        private float $yearEnd,
        private ?string $noteText,
        private bool $isManual,
        private ?int $createdBy
    ) {}

    /** @return int Định danh */
    public function getId(): int { return $this->id; }

    /** @return int ID kỳ kế toán */
    public function getPeriodId(): int { return $this->periodId; }

    /** @return string Mã phần */
    public function getSectionCode(): string { return $this->sectionCode; }

    /** @return string Mã chỉ tiêu */
    public function getIndicatorCode(): string { return $this->indicatorCode; }

    /** @return float Giá trị đầu năm */
    public function getYearStart(): float { return $this->yearStart; }

    /** @return float Giá trị cuối kỳ */
    public function getYearEnd(): float { return $this->yearEnd; }

    /** @return string|null Ghi chú thuyết minh */
    public function getNoteText(): ?string { return $this->noteText; }

    /** @return bool true nếu nhập tay */
    public function isManual(): bool { return $this->isManual; }

    /** @return int|null Người tạo */
    public function getCreatedBy(): ?int { return $this->createdBy; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu BC09 dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'period_id' => $this->periodId,
            'section_code' => $this->sectionCode,
            'indicator_code' => $this->indicatorCode,
            'year_start' => $this->yearStart,
            'year_end' => $this->yearEnd,
            'note_text' => $this->noteText,
            'is_manual' => $this->isManual,
            'created_by' => $this->createdBy,
        ];
    }
}

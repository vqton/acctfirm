<?php
namespace Accounting\Domain\Model;

class Bc09Data
{
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

    public function getId(): int { return $this->id; }
    public function getPeriodId(): int { return $this->periodId; }
    public function getSectionCode(): string { return $this->sectionCode; }
    public function getIndicatorCode(): string { return $this->indicatorCode; }
    public function getYearStart(): float { return $this->yearStart; }
    public function getYearEnd(): float { return $this->yearEnd; }
    public function getNoteText(): ?string { return $this->noteText; }
    public function isManual(): bool { return $this->isManual; }
    public function getCreatedBy(): ?int { return $this->createdBy; }

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

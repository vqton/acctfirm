<?php
namespace Accounting\Domain\Model;

class Bc09Config
{
    public function __construct(
        private int $id,
        private string $sectionCode,
        private string $indicatorCode,
        private string $indicatorName,
        private ?string $formulaExpression,
        private ?string $accountCodes,
        private bool $isAutoCalc,
        private bool $isRequired,
        private ?string $parentCode,
        private int $sortOrder
    ) {}

    public function getId(): int { return $this->id; }
    public function getSectionCode(): string { return $this->sectionCode; }
    public function getIndicatorCode(): string { return $this->indicatorCode; }
    public function getIndicatorName(): string { return $this->indicatorName; }
    public function getFormulaExpression(): ?string { return $this->formulaExpression; }
    public function getAccountCodes(): ?string { return $this->accountCodes; }
    public function isAutoCalc(): bool { return $this->isAutoCalc; }
    public function isRequired(): bool { return $this->isRequired; }
    public function getParentCode(): ?string { return $this->parentCode; }
    public function getSortOrder(): int { return $this->sortOrder; }

    // Lấy danh sách mã tài khoản từ account_codes
    public function getAccountCodeList(): array
    {
        if (!$this->accountCodes || trim($this->accountCodes) === '') return [];
        return array_map('trim', explode(',', $this->accountCodes));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'section_code' => $this->sectionCode,
            'indicator_code' => $this->indicatorCode,
            'indicator_name' => $this->indicatorName,
            'formula_expression' => $this->formulaExpression,
            'account_codes' => $this->accountCodes,
            'is_auto_calc' => $this->isAutoCalc,
            'is_required' => $this->isRequired,
            'parent_code' => $this->parentCode,
            'sort_order' => $this->sortOrder,
        ];
    }
}

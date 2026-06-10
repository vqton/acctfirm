<?php
namespace Accounting\Domain\Model;

/**
 * Cấu hình BC09 — Định nghĩa các chỉ tiêu trên Thuyết minh Báo cáo tài chính.
 *
 * BC09 là thuyết minh BCTC, giải trình chi tiết các chỉ tiêu trên BC01, BC02, BC03.
 * Bc09Config định nghĩa cấu trúc từng chỉ tiêu: mã chỉ tiêu, công thức tính,
 * danh sách tài khoản liên quan, thứ tự hiển thị.
 *
 * NGHIỆP VỤ:
 * - $sectionCode: mã phần (VD: "I", "II", "III")
 * - $indicatorCode: mã chỉ tiêu (VD: "01", "02")
 * - Có thể auto tính (isAutoCalc) hoặc nhập tay
 * - $parentCode: chỉ tiêu cha (cấu trúc cây)
 */
class Bc09Config
{
    /**
     * Khởi tạo cấu hình BC09.
     *
     * @param int $id Định danh
     * @param string $sectionCode Mã phần
     * @param string $indicatorCode Mã chỉ tiêu
     * @param string $indicatorName Tên chỉ tiêu
     * @param string|null $formulaExpression Công thức tính
     * @param string|null $accountCodes Danh sách mã tài khoản (phân cách bằng dấu phẩy)
     * @param bool $isAutoCalc Cho phép tự động tính
     * @param bool $isRequired Bắt buộc nhập
     * @param string|null $parentCode Chỉ tiêu cha
     * @param int $sortOrder Thứ tự sắp xếp
     */
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

    /** @return int Định danh */
    public function getId(): int { return $this->id; }

    /** @return string Mã phần */
    public function getSectionCode(): string { return $this->sectionCode; }

    /** @return string Mã chỉ tiêu */
    public function getIndicatorCode(): string { return $this->indicatorCode; }

    /** @return string Tên chỉ tiêu */
    public function getIndicatorName(): string { return $this->indicatorName; }

    /** @return string|null Công thức tính */
    public function getFormulaExpression(): ?string { return $this->formulaExpression; }

    /** @return string|null Danh sách mã tài khoản */
    public function getAccountCodes(): ?string { return $this->accountCodes; }

    /** @return bool Cho phép tự động tính */
    public function isAutoCalc(): bool { return $this->isAutoCalc; }

    /** @return bool Bắt buộc nhập */
    public function isRequired(): bool { return $this->isRequired; }

    /** @return string|null Chỉ tiêu cha */
    public function getParentCode(): ?string { return $this->parentCode; }

    /** @return int Thứ tự sắp xếp */
    public function getSortOrder(): int { return $this->sortOrder; }

    /**
     * Lấy danh sách mã tài khoản từ chuỗi account_codes.
     *
     * @return array Danh sách mã tài khoản
     */
    public function getAccountCodeList(): array
    {
        if (!$this->accountCodes || trim($this->accountCodes) === '') return [];
        return array_map('trim', explode(',', $this->accountCodes));
    }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu cấu hình BC09 dạng mảng
     */
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

<?php
namespace Accounting\Domain\Model;

/**
 * Thuế suất — Danh mục các loại thuế áp dụng.
 *
 * Chủ yếu quản lý thuế GTGT (VAT) cho các nghiệp vụ mua/bán hàng hóa,
 * dịch vụ. Thuế suất ảnh hưởng đến số thuế đầu vào được khấu trừ và
 * thuế đầu ra phải nộp.
 *
 * NGHIỆP VỤ:
 * - $taxType: 'vat' (GTGT), 'import' (nhập khẩu), 'excise' (tiêu thụ đặc biệt)
 * - $rate: thuế suất phần trăm (VD: 0, 5, 8, 10 cho GTGT)
 * - Mỗi mặt hàng/dịch vụ có thể có thuế suất khác nhau tùy theo quy định
 *
 * LIÊN KẾT:
 * - Item → mỗi mặt hàng gắn với một TaxRate
 * - JournalService → tách riêng phần thuế khi hạch toán mua/bán
 *
 * RỦI RO:
 * - Sai thuế suất → kê khai thuế sai → phạt chậm nộp thuế
 * - Thuế suất thay đổi theo thời điểm (VD: giảm thuế GTGT 2024-2026)
 *   → cần lịch sử thuế suất để hạch toán đúng thời kỳ
 */
class TaxRate
{
    private string $id;
    private string $code;
    private string $name;
    private float $rate;
    private string $taxType;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $code, string $name, float $rate = 0, string $taxType = 'vat'
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->rate = $rate;
        $this->taxType = $taxType;
        $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getRate(): float { return $this->rate; }
    public function getTaxType(): string { return $this->taxType; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setName(string $name): void { $this->name = $name; }
    public function setRate(float $rate): void { $this->rate = $rate; }
    public function setTaxType(string $type): void { $this->taxType = $type; }
    public function setStatus(bool $status): void { $this->status = $status; }

    // Chuyển đổi model thành mảng để response API.
    // 'rate': thuế suất phần trăm (VD: 0, 5, 8, 10) — ảnh hưởng TK 133 (đầu vào) và 3331 (đầu ra).
    // 'tax_type': 'vat' (GTGT), 'import' (nhập khẩu), 'excise' (tiêu thụ đặc biệt).
    // RỦI RO: Sai thuế suất → kê khai thuế sai → phạt chậm nộp thuế (theo Luật Quản lý thuế).
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'rate' => $this->rate, 'tax_type' => $this->taxType, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

<?php
namespace Accounting\Domain\Model;

/**
 * Chính sách khấu hao — Quy định phương pháp và thông số khấu hao mặc định.
 *
 * Dùng làm khuôn mẫu khi ghi nhận TSCĐ mới. Mỗi TSCĐ có thể ghi đè
 * chính sách mặc định nếu có đặc thù riêng.
 *
 * NGHIỆP VỤ:
 * - $method: 'straight_line' (đường thẳng — thông dụng nhất),
 *   'declining_balance' (số dư giảm dần),
 *   'unit_of_production' (theo sản lượng)
 * - $defaultLife: thời gian sử dụng mặc định (tháng)
 * - $defaultSalvageRate: tỷ lệ giá trị thu hồi mặc định (%)
 * - Chính sách khấu hao phải phù hợp với Circular 45/2013/TT-BTC và
 *   Thông tư 99/2025/TT-BTC
 *
 * LIÊN KẾT:
 * - FixedAsset → TSCĐ áp dụng chính sách khấu hao
 * - FixedAssetService → tính khấu hao dựa trên chính sách
 *
 * RỦI RO:
 * - Khấu hao nhanh (declining_balance) cho lợi ích thuế nhưng phải
 *   thỏa mãn điều kiện của Luật Thuế TNDN
 * - Sai thời gian khấu hao → sai chi phí hàng năm → sai BC02
 */
class DepreciationPolicy
{
    private string $id;
    private string $code;
    private string $name;
    private string $method;
    private int $defaultLife;
    private float $defaultSalvageRate;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $code, string $name, string $method = 'straight_line',
        int $defaultLife = 0, float $defaultSalvageRate = 0
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->name = $name;
        $this->method = $method;
        $this->defaultLife = $defaultLife;
        $this->defaultSalvageRate = $defaultSalvageRate;
        $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getMethod(): string { return $this->method; }
    public function getDefaultLife(): int { return $this->defaultLife; }
    public function getDefaultSalvageRate(): float { return $this->defaultSalvageRate; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setName(string $name): void { $this->name = $name; }
    public function setMethod(string $method): void { $this->method = $method; }
    public function setDefaultLife(int $life): void { $this->defaultLife = $life; }
    public function setDefaultSalvageRate(float $rate): void { $this->defaultSalvageRate = $rate; }
    public function setStatus(bool $status): void { $this->status = $status; }

    // Chuyển đổi model thành mảng để response API.
    // Chính sách khấu hao là khuôn mẫu khi ghi nhận TSCĐ mới.
    // 'method': 'straight_line' (đường thẳng), 'declining_balance' (số dư giảm dần).
    // 'default_salvage_rate': tỷ lệ giá trị thu hồi (%) — ảnh hưởng mức khấu hao hàng tháng.
    // RỦI RO: Khấu hao nhanh cho lợi ích thuế nhưng phải thỏa mãn điều kiện Luật TNDN.
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'method' => $this->method, 'default_life' => $this->defaultLife,
            'default_salvage_rate' => $this->defaultSalvageRate, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

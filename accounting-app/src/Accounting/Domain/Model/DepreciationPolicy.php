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

    /**
     * Khởi tạo chính sách khấu hao.
     *
     * @param string $id Định danh
     * @param string $code Mã chính sách
     * @param string $name Tên chính sách
     * @param string $method Phương pháp: 'straight_line', 'declining_balance', 'unit_of_production'
     * @param int $defaultLife Thời gian sử dụng mặc định (tháng)
     * @param float $defaultSalvageRate Tỷ lệ giá trị thu hồi mặc định (%)
     */
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

    /** @return string Định danh */
    public function getId(): string { return $this->id; }

    /** @return string Mã chính sách */
    public function getCode(): string { return $this->code; }

    /** @return string Tên chính sách */
    public function getName(): string { return $this->name; }

    /** @return string Phương pháp khấu hao */
    public function getMethod(): string { return $this->method; }

    /** @return int Thời gian sử dụng mặc định (tháng) */
    public function getDefaultLife(): int { return $this->defaultLife; }

    /** @return float Tỷ lệ giá trị thu hồi mặc định (%) */
    public function getDefaultSalvageRate(): float { return $this->defaultSalvageRate; }

    /** @return bool Trạng thái hoạt động */
    public function isStatus(): bool { return $this->status; }

    /** @return \DateTimeImmutable Thời điểm tạo */
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @param string $code Mã chính sách mới */
    public function setCode(string $code): void { $this->code = $code; }

    /** @param string $name Tên chính sách mới */
    public function setName(string $name): void { $this->name = $name; }

    /** @param string $method Phương pháp mới */
    public function setMethod(string $method): void { $this->method = $method; }

    /** @param int $life Thời gian sử dụng mới */
    public function setDefaultLife(int $life): void { $this->defaultLife = $life; }

    /** @param float $rate Tỷ lệ thu hồi mới */
    public function setDefaultSalvageRate(float $rate): void { $this->defaultSalvageRate = $rate; }

    /** @param bool $status Trạng thái mới */
    public function setStatus(bool $status): void { $this->status = $status; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu chính sách khấu hao dạng mảng
     */
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

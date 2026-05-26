<?php
namespace Accounting\Domain\Model;

/**
 * Tỷ giá ngoại tệ — Tỷ giá quy đổi ngoại tệ ra VND.
 *
 * Sử dụng để hạch toán các nghiệp vụ bằng ngoại tệ. Tỷ giá được cập nhật
 * theo ngày, áp dụng cho các giao dịch phát sinh trong ngày đó.
 *
 * NGHIỆP VỤ:
 * - $rate: tỷ giá quy đổi (1 ngoại tệ = ? VND)
 * - $rateDate: ngày áp dụng tỷ giá — mỗi ngày có một tỷ giá riêng
 * - Áp dụng tỷ giá giao dịch thực tế (tỷ giá bán của ngân hàng) hoặc
 *   tỷ giá bình quân liên ngân hàng do NHNN công bố
 *
 * LIÊN KẾT:
 * - LedgerEntry → sử dụng để quy đổi fcAmount ra VND
 * - FsService → đánh giá chênh lệch tỷ giá cuối kỳ (TK 413/635/515)
 *
 * RỦI RO:
 * - Sai tỷ giá → sai số dư tài khoản ngoại tệ → sai chênh lệch tỷ giá
 * - Chênh lệch tỷ giá cuối kỳ phải được hạch toán đầy đủ theo quy định
 * - Tỷ giá mua và tỷ giá bán khác nhau — cần phân biệt rõ ràng
 */
class ExchangeRate
{
    private string $id;
    private string $currencyCode;
    private string $currencyName;
    private float $rate;
    private string $rateDate;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $currencyCode, string $currencyName, float $rate, string $rateDate
    ) {
        $this->id = $id;
        $this->currencyCode = $currencyCode;
        $this->currencyName = $currencyName;
        $this->rate = $rate;
        $this->rateDate = $rateDate;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCurrencyCode(): string { return $this->currencyCode; }
    public function getCurrencyName(): string { return $this->currencyName; }
    public function getRate(): float { return $this->rate; }
    public function getRateDate(): string { return $this->rateDate; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCurrencyCode(string $code): void { $this->currencyCode = $code; }
    public function setCurrencyName(string $name): void { $this->currencyName = $name; }
    public function setRate(float $rate): void { $this->rate = $rate; }
    public function setRateDate(string $date): void { $this->rateDate = $date; }

    // Chuyển đổi model thành mảng để response API.
    // 'rate': 1 ngoại tệ = ? VND — sử dụng để quy đổi fcAmount trong LedgerEntry.
    // 'rate_date': ngày áp dụng — mỗi giao dịch dùng tỷ giá tại ngày giao dịch.
    // RỦI RO: Chênh lệch tỷ giá cuối kỳ (TK 413/635/515) phải được hạch toán đầy đủ.
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'currency_code' => $this->currencyCode,
            'currency_name' => $this->currencyName, 'rate' => $this->rate,
            'rate_date' => $this->rateDate, 'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

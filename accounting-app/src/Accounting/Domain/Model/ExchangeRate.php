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

    /**
     * Khởi tạo tỷ giá.
     *
     * @param string $id Định danh
     * @param string $currencyCode Mã tiền tệ (VD: "USD", "EUR")
     * @param string $currencyName Tên tiền tệ
     * @param float $rate Tỷ giá quy đổi (1 ngoại tệ = ? VND)
     * @param string $rateDate Ngày áp dụng
     */
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

    /** @return string Định danh */
    public function getId(): string { return $this->id; }

    /** @return string Mã tiền tệ */
    public function getCurrencyCode(): string { return $this->currencyCode; }

    /** @return string Tên tiền tệ */
    public function getCurrencyName(): string { return $this->currencyName; }

    /** @return float Tỷ giá quy đổi */
    public function getRate(): float { return $this->rate; }

    /** @return string Ngày áp dụng */
    public function getRateDate(): string { return $this->rateDate; }

    /** @return \DateTimeImmutable Thời điểm tạo */
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @param string $code Mã tiền tệ mới */
    public function setCurrencyCode(string $code): void { $this->currencyCode = $code; }

    /** @param string $name Tên tiền tệ mới */
    public function setCurrencyName(string $name): void { $this->currencyName = $name; }

    /** @param float $rate Tỷ giá mới */
    public function setRate(float $rate): void { $this->rate = $rate; }

    /** @param string $date Ngày áp dụng mới */
    public function setRateDate(string $date): void { $this->rateDate = $date; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu tỷ giá dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'currency_code' => $this->currencyCode,
            'currency_name' => $this->currencyName, 'rate' => $this->rate,
            'rate_date' => $this->rateDate, 'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

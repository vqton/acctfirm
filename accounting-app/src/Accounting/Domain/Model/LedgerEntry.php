<?php
namespace Accounting\Domain\Model;

/**
 * Dòng chi tiết bút toán — Một bên Nợ hoặc một bên Có trong bút toán kép.
 *
 * Mỗi LedgerEntry là một dòng trong Transaction, ghi nhận số tiền vào một
 * tài khoản cụ thể. Một Transaction phải có ít nhất 2 dòng (1 Nợ + 1 Có)
 * và tổng số tiền bên Nợ phải bằng bên Có.
 *
 * NGHIỆP VỤ:
 * - $isDebit = true → ghi Nợ, false → ghi Có
 * - $amount luôn là số dương (không âm — đã có validation trong constructor)
 * - $currency/$exchangeRate/$fcAmount hỗ trợ hạch toán ngoại tệ:
 *   Nguyên tệ ghi ở $fcAmount, quy đổi ra VND = $fcAmount × $exchangeRate
 *
 * RỦI RO:
 * - $accountId phải là tài khoản chi tiết (không phải tài khoản tổng hợp)
 * - Sai tài khoản → sai số dư → sai báo cáo tài chính
 * - $lineOrder quyết định thứ tự hiển thị trên chứng từ in
 */
class LedgerEntry
{
    private string $id;
    private string $accountId;
    private float $amount;
    private bool $isDebit;
    private ?string $note;
    private string $currency;
    private float $exchangeRate;
    private float $fcAmount;
    private int $lineOrder;

    /**
     * Khởi tạo dòng bút toán.
     *
     * @param string $id Định danh dòng
     * @param string $accountId ID tài khoản
     * @param float $amount Số tiền (phải >= 0)
     * @param bool $isDebit true = Nợ, false = Có
     * @param string|null $note Diễn giải dòng
     * @param string $currency Loại tiền (mặc định VND)
     * @param float $exchangeRate Tỷ giá quy đổi
     * @param float $fcAmount Số tiền nguyên tệ
     * @param int $lineOrder Thứ tự dòng
     */
    public function __construct(
        string $id,
        string $accountId,
        float $amount,
        bool $isDebit,
        ?string $note = null,
        string $currency = 'VND',
        float $exchangeRate = 1.0,
        float $fcAmount = 0.0,
        int $lineOrder = 0
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Số tiền không được âm. Vui lòng kiểm tra lại.');
        }
        $this->id = $id;
        $this->accountId = $accountId;
        $this->amount = $amount;
        $this->isDebit = $isDebit;
        $this->note = $note;
        $this->currency = $currency;
        $this->exchangeRate = $exchangeRate;
        $this->fcAmount = $fcAmount;
        $this->lineOrder = $lineOrder;
    }

    /** @return string Định danh dòng */
    public function getId(): string { return $this->id; }

    /** @return string ID tài khoản */
    public function getAccountId(): string { return $this->accountId; }

    /** @return float Số tiền */
    public function getAmount(): float { return $this->amount; }

    /** @return bool true = Nợ */
    public function isDebit(): bool { return $this->isDebit; }

    /** @return bool true = Có (ngược lại của isDebit) */
    public function isCredit(): bool { return !$this->isDebit; }

    /** @return string|null Diễn giải */
    public function getNote(): ?string { return $this->note; }

    /** @return string Loại tiền */
    public function getCurrency(): string { return $this->currency; }

    /** @return float Tỷ giá quy đổi */
    public function getExchangeRate(): float { return $this->exchangeRate; }

    /** @return float Số tiền nguyên tệ */
    public function getFcAmount(): float { return $this->fcAmount; }

    /** @return int Thứ tự dòng */
    public function getLineOrder(): int { return $this->lineOrder; }
}

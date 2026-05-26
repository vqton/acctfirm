<?php
// src/Accounting/Domain/Model/LedgerEntry.php

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

    // Constructor yêu cầu $amount >= 0 vì: số tiền âm làm sai bản chất Nợ/Có.
    // Nghiệp vụ điều chỉnh giảm phải dùng bút toán đảo (reverse) thay vì âm.
    // $isDebit = true là ghi Nợ, false là ghi Có — không thay đổi dấu của amount.
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
            throw new \InvalidArgumentException('Amount cannot be negative');
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

    public function getId(): string { return $this->id; }
    public function getAccountId(): string { return $this->accountId; }
    public function getAmount(): float { return $this->amount; }
    public function isDebit(): bool { return $this->isDebit; }
    public function isCredit(): bool { return !$this->isDebit; }
    public function getNote(): ?string { return $this->note; }
    public function getCurrency(): string { return $this->currency; }
    public function getExchangeRate(): float { return $this->exchangeRate; }
    public function getFcAmount(): float { return $this->fcAmount; }
    public function getLineOrder(): int { return $this->lineOrder; }
}
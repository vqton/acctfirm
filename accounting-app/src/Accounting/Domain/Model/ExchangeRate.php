<?php
namespace Accounting\Domain\Model;

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

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'currency_code' => $this->currencyCode,
            'currency_name' => $this->currencyName, 'rate' => $this->rate,
            'rate_date' => $this->rateDate, 'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

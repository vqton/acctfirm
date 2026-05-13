<?php
namespace Accounting\Domain\Model;

class BankAccount
{
    private string $id;
    private string $code;
    private string $bankName;
    private string $accountNumber;
    private string $accountHolder;
    private string $branch;
    private string $currency;
    private float $openingBalance;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id, string $code, string $bankName, string $accountNumber,
        string $accountHolder, string $branch = '', string $currency = 'VND',
        float $openingBalance = 0
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->bankName = $bankName;
        $this->accountNumber = $accountNumber;
        $this->accountHolder = $accountHolder;
        $this->branch = $branch;
        $this->currency = $currency;
        $this->openingBalance = $openingBalance;
        $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getBankName(): string { return $this->bankName; }
    public function getAccountNumber(): string { return $this->accountNumber; }
    public function getAccountHolder(): string { return $this->accountHolder; }
    public function getBranch(): string { return $this->branch; }
    public function getCurrency(): string { return $this->currency; }
    public function getOpeningBalance(): float { return $this->openingBalance; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setBankName(string $name): void { $this->bankName = $name; }
    public function setAccountNumber(string $num): void { $this->accountNumber = $num; }
    public function setAccountHolder(string $holder): void { $this->accountHolder = $holder; }
    public function setBranch(string $branch): void { $this->branch = $branch; }
    public function setCurrency(string $currency): void { $this->currency = $currency; }
    public function setOpeningBalance(float $balance): void { $this->openingBalance = $balance; }
    public function setStatus(bool $status): void { $this->status = $status; }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'bank_name' => $this->bankName,
            'account_number' => $this->accountNumber, 'account_holder' => $this->accountHolder,
            'branch' => $this->branch, 'currency' => $this->currency,
            'opening_balance' => $this->openingBalance, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

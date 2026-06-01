<?php
namespace Accounting\Domain\Contract;

interface CashServiceInterface
{
    public function recordReceipt(
        float $amount, string $creditAccountCode, string $description,
        string $reference, string $createdBy,
        float $vatAmount = 0, float $vatRate = 0
    ): array;

    public function recordPayment(
        float $amount, string $debitAccountCode, string $description,
        string $reference, string $createdBy,
        float $vatAmount = 0, float $vatRate = 0
    ): array;

    public function recordBankDeposit(
        float $amount, string $description, string $reference, string $createdBy
    ): array;

    public function recordBankWithdrawal(
        float $amount, string $description, string $reference, string $createdBy
    ): array;

    public function recordBankReceipt(
        float $amount, string $creditAccountCode, string $description,
        string $reference, string $createdBy,
        float $vatAmount = 0, float $vatRate = 0
    ): array;

    public function recordBankPayment(
        float $amount, string $debitAccountCode, string $description,
        string $reference, string $createdBy,
        float $vatAmount = 0, float $vatRate = 0
    ): array;

    public function recordBankInterest(
        float $amount, string $description, string $reference, string $createdBy
    ): array;

    public function recordBankCharge(
        float $amount, string $description, string $reference, string $createdBy,
        float $vatAmount = 0, float $vatRate = 0
    ): array;

    public function recordTransit(
        float $amount, string $description, string $reference, string $createdBy
    ): array;

    public function confirmTransit(string $transitId, string $createdBy): array;
    public function reverseTransit(string $transitId, string $createdBy): array;

    public function getCashBook(string $fromDate = null, string $toDate = null): array;

    public function recordReceiptFC(
        float $fcAmount, string $creditAccountCode, string $currencyCode,
        float $exchangeRate, string $description, string $reference, string $createdBy
    ): array;

    public function recordPaymentFC(
        float $fcAmount, string $debitAccountCode, string $currencyCode,
        float $exchangeRate, string $description, string $reference, string $createdBy
    ): array;

    public function getFCBalances(): array;

    public function revalueFC(
        string $accountCode, string $currencyCode, float $closingRate,
        string $asOfDate, string $createdBy
    ): array;
}

<?php
namespace Accounting\Domain\Contract;

use Accounting\Domain\Model\Transaction;

interface JournalServiceInterface
{
    public function createDraft(
        string $description, string $reference, array $lines, string $createdBy,
        bool $allowControl = false, ?string $module = null, ?string $date = null,
        ?string $voucherType = null, ?string $sourceModule = null,
        string $currency = 'VND', float $exchangeRate = 1.0
    ): Transaction;

    public function submitEntry(string $txnId, string $submittedBy): Transaction;
    public function approveEntry(string $txnId, string $approverId, ?string $comment = null): Transaction;
    public function rejectEntry(string $txnId, string $approverId, string $reason): Transaction;
    public function returnEntry(string $txnId, string $userId, ?string $comment = null): Transaction;
    public function approveDraft(string $txnId, string $approvedBy): Transaction;
    public function generateVoucherNo(string $prefix = 'JV'): string;

    public function postEntry(
        string $description, string $reference, array $lines, string $createdBy,
        bool $allowControl = false, ?string $module = null, ?string $date = null,
        ?string $voucherType = null, ?string $sourceModule = null,
        string $currency = 'VND', float $exchangeRate = 1.0
    ): Transaction;

    public function createSupplementaryEntry(
        string $originalTxnId, array $correctLines, string $reason,
        string $createdBy, bool $allowControl = false
    ): Transaction;

    public function createNegativeEntry(
        string $originalTxnId, string $reason, string $createdBy,
        bool $allowControl = false
    ): Transaction;

    public function createAdjustingEntry(
        string $originalTxnId, array $movingLines, string $reason,
        string $createdBy, bool $allowControl = false
    ): Transaction;

    public function getCorrectionHistory(string $transactionId): array;
}

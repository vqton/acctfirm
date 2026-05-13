<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;

class CashService
{
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;

    public function __construct(
        AccountRepositoryInterface $accountRepo,
        TransactionRepositoryInterface $txnRepo
    ) {
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
    }

    public function recordReceipt(float $amount, string $creditAccountCode, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        $creditAccount = $this->accountRepo->findByCode($creditAccountCode);
        if (!$creditAccount) throw new \InvalidArgumentException("Account not found: {$creditAccountCode}");

        $journal = new JournalService($this->accountRepo, $this->txnRepo);
        $txn = $journal->postEntry($description, $reference, [
            ['account_code' => '111', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => $creditAccountCode, 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'type' => 'receipt'];
    }

    public function recordPayment(float $amount, string $debitAccountCode, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        $debitAccount = $this->accountRepo->findByCode($debitAccountCode);
        if (!$debitAccount) throw new \InvalidArgumentException("Account not found: {$debitAccountCode}");

        // Check sufficient cash balance
        $cash = $this->accountRepo->findByCode('111');
        if ($cash && $cash->getBalance() < $amount && in_array($cash->getType(), ['asset', 'expense'])) {
            // Asset/expense normal = debit. Balance = net debit. For 111 (asset, debit-normal),
            // balance IS the cash amount. If cash balance < payment, reject.
            // But 111 is debit-normal asset: credit() adds, debit() subtracts.
            // JournalService: Cr 111 → for asset, calls debit(), so balance decreases.
            // getBalance() returns current numeric balance which for asset IS the cash amount.
            $actualBalance = $cash->getBalance();
            // For asset accounts, getBalance() returns the debit balance (positive = cash on hand)
            if ($actualBalance < $amount) {
                throw new \InvalidArgumentException("Insufficient cash balance: have {$actualBalance}, need {$amount}");
            }
        }

        $journal = new JournalService($this->accountRepo, $this->txnRepo);
        $txn = $journal->postEntry($description, $reference, [
            ['account_code' => $debitAccountCode, 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '111', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'type' => 'payment'];
    }
}

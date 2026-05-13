<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;

class CashService
{
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;
    private ?\PDO $pdo;

    public function __construct(
        AccountRepositoryInterface $accountRepo,
        TransactionRepositoryInterface $txnRepo,
        ?\PDO $pdo = null
    ) {
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
        $this->pdo = $pdo;
    }

    // ── Cash Receipt ──

    public function recordReceipt(float $amount, string $creditAccountCode, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        $creditAccount = $this->accountRepo->findByCode($creditAccountCode);
        if (!$creditAccount) throw new \InvalidArgumentException("Account not found: {$creditAccountCode}");

        $journal = new JournalService($this->accountRepo, $this->txnRepo);
        $txn = $journal->postEntry("Cash receipt: {$description}", $reference, [
            ['account_code' => '111', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => $creditAccountCode, 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'type' => 'receipt'];
    }

    // ── Cash Payment ──

    public function recordPayment(float $amount, string $debitAccountCode, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        $debitAccount = $this->accountRepo->findByCode($debitAccountCode);
        if (!$debitAccount) throw new \InvalidArgumentException("Account not found: {$debitAccountCode}");

        $cash = $this->accountRepo->findByCode('111');
        if ($cash && $cash->getBalance() < $amount) {
            throw new \InvalidArgumentException("Insufficient cash balance: have {$cash->getBalance()}, need {$amount}");
        }

        $journal = new JournalService($this->accountRepo, $this->txnRepo);
        $txn = $journal->postEntry("Cash payment: {$description}", $reference, [
            ['account_code' => $debitAccountCode, 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '111', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'type' => 'payment'];
    }

    // ── Bank Deposit (Dr 112 — Cr 111) ──

    public function recordBankDeposit(float $amount, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        $cash = $this->accountRepo->findByCode('111');
        if ($cash && $cash->getBalance() < $amount) {
            throw new \InvalidArgumentException("Insufficient cash balance: have {$cash->getBalance()}, need {$amount}");
        }

        $journal = new JournalService($this->accountRepo, $this->txnRepo);
        $txn = $journal->postEntry("Bank deposit: {$description}", $reference, [
            ['account_code' => '112', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '111', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'type' => 'bank_deposit'];
    }

    // ── Bank Withdrawal (Dr 111 — Cr 112) ──

    public function recordBankWithdrawal(float $amount, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        $bank = $this->accountRepo->findByCode('112');
        if ($bank && $bank->getBalance() < $amount) {
            throw new \InvalidArgumentException("Insufficient bank balance: have {$bank->getBalance()}, need {$amount}");
        }

        $journal = new JournalService($this->accountRepo, $this->txnRepo);
        $txn = $journal->postEntry("Bank withdrawal: {$description}", $reference, [
            ['account_code' => '111', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '112', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'type' => 'bank_withdrawal'];
    }

    // ── Bank Receipt (Dr 112 — Cr counterparty, e.g. customer pays to bank) ──

    public function recordBankReceipt(float $amount, string $creditAccountCode, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        $creditAccount = $this->accountRepo->findByCode($creditAccountCode);
        if (!$creditAccount) throw new \InvalidArgumentException("Account not found: {$creditAccountCode}");

        $journal = new JournalService($this->accountRepo, $this->txnRepo);
        $txn = $journal->postEntry("Bank receipt: {$description}", $reference, [
            ['account_code' => '112', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => $creditAccountCode, 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'type' => 'bank_receipt'];
    }

    // ── Bank Payment (Dr counterparty — Cr 112, e.g. supplier paid from bank) ──

    public function recordBankPayment(float $amount, string $debitAccountCode, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        $debitAccount = $this->accountRepo->findByCode($debitAccountCode);
        if (!$debitAccount) throw new \InvalidArgumentException("Account not found: {$debitAccountCode}");

        $bank = $this->accountRepo->findByCode('112');
        if ($bank && $bank->getBalance() < $amount) {
            throw new \InvalidArgumentException("Insufficient bank balance: have {$bank->getBalance()}, need {$amount}");
        }

        $journal = new JournalService($this->accountRepo, $this->txnRepo);
        $txn = $journal->postEntry("Bank payment: {$description}", $reference, [
            ['account_code' => $debitAccountCode, 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '112', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'type' => 'bank_payment'];
    }

    // ── Bank Interest (Dr 112 — Cr 515) ──

    public function recordBankInterest(float $amount, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        $journal = new JournalService($this->accountRepo, $this->txnRepo);
        $txn = $journal->postEntry("Bank interest: {$description}", $reference, [
            ['account_code' => '112', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '515', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'type' => 'bank_interest'];
    }

    // ── Bank Charges (Dr 642 — Cr 112) ──

    public function recordBankCharge(float $amount, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        $bank = $this->accountRepo->findByCode('112');
        if ($bank && $bank->getBalance() < $amount) {
            throw new \InvalidArgumentException("Insufficient bank balance: have {$bank->getBalance()}, need {$amount}");
        }

        $journal = new JournalService($this->accountRepo, $this->txnRepo);
        $txn = $journal->postEntry("Bank charge: {$description}", $reference, [
            ['account_code' => '642', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '112', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'type' => 'bank_charge'];
    }

    // ── Cash in Transit (Dr 113 — Cr 111) ──

    public function recordTransit(float $amount, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        $cash = $this->accountRepo->findByCode('111');
        if ($cash && $cash->getBalance() < $amount) {
            throw new \InvalidArgumentException("Insufficient cash balance: have {$cash->getBalance()}, need {$amount}");
        }

        $journal = new JournalService($this->accountRepo, $this->txnRepo);
        $txn = $journal->postEntry("Cash in transit: {$description}", $reference, [
            ['account_code' => '113', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '111', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        $transitId = uniqid('tr_');
        if ($this->pdo) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO cash_transit (id, amount, source_account, destination_account, description, reference, status, transit_date, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)'
            );
            $stmt->execute([$transitId, $amount, '111', '112', $description, $reference, 'in_transit', $createdBy]);
        }

        return ['transaction_id' => $txn->getId(), 'transit_id' => $transitId, 'amount' => $amount, 'type' => 'transit'];
    }

    // ── Confirm Transit (Dr 112 — Cr 113) ──

    public function confirmTransit(string $transitId, string $createdBy): array
    {
        if (!$this->pdo) {
            throw new \RuntimeException('PDO not available for transit tracking');
        }

        $row = $this->pdo->query("SELECT amount FROM cash_transit WHERE id='{$transitId}' AND status='in_transit'")->fetch();
        if (!$row) {
            throw new \InvalidArgumentException("Transit record not found or already resolved: {$transitId}");
        }

        $amount = (float)$row['amount'];
        $journal = new JournalService($this->accountRepo, $this->txnRepo);
        $txn = $journal->postEntry("Transit confirmed: bank credited", "CNF-{$transitId}", [
            ['account_code' => '112', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '113', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        $this->pdo->prepare(
            'UPDATE cash_transit SET status=?, confirm_date=CURDATE() WHERE id=?'
        )->execute(['confirmed', $transitId]);

        return ['transaction_id' => $txn->getId(), 'transit_id' => $transitId, 'type' => 'transit_confirm'];
    }

    // ── Reverse Transit (Dr 111 — Cr 113) ──

    public function reverseTransit(string $transitId, string $createdBy): array
    {
        $journal = new JournalService($this->accountRepo, $this->txnRepo);

        if ($this->pdo) {
            $row = $this->pdo->query("SELECT amount FROM cash_transit WHERE id='{$transitId}' AND status='in_transit'")->fetch();
            if ($row) {
                $amount = (float)$row['amount'];
                $this->pdo->prepare(
                    'UPDATE cash_transit SET status=? WHERE id=?'
                )->execute(['reversed', $transitId]);

                $txn = $journal->postEntry("Transit reversed", "REV-{$transitId}", [
                    ['account_code' => '111', 'amount' => $amount, 'is_debit' => true],
                    ['account_code' => '113', 'amount' => $amount, 'is_debit' => false],
                ], $createdBy);

                return ['transaction_id' => $txn->getId(), 'transit_id' => $transitId, 'type' => 'transit_reverse'];
            }
            throw new \InvalidArgumentException("Transit record not found or already resolved: {$transitId}");
        }

        throw new \RuntimeException('PDO not available for transit tracking');
    }
}

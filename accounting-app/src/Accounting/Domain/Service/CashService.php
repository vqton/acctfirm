<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;

class CashService
{
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;
    private ?\PDO $pdo;
    private JournalService $journal;

    public function __construct(
        AccountRepositoryInterface $accountRepo,
        TransactionRepositoryInterface $txnRepo,
        JournalService $journal,
        ?\PDO $pdo = null
    ) {
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
        $this->journal = $journal;
        $this->pdo = $pdo;
    }

    // ── Cash Receipt ──

    public function recordReceipt(float $amount, string $creditAccountCode, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        $creditAccount = $this->accountRepo->findByCode($creditAccountCode);
        if (!$creditAccount) throw new \InvalidArgumentException("Account not found: {$creditAccountCode}");

        
        $txn = $this->journal->postEntry("Cash receipt: {$description}", $reference, [
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

        
        $txn = $this->journal->postEntry("Cash payment: {$description}", $reference, [
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

        
        $txn = $this->journal->postEntry("Bank deposit: {$description}", $reference, [
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

        
        $txn = $this->journal->postEntry("Bank withdrawal: {$description}", $reference, [
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

        
        $txn = $this->journal->postEntry("Bank receipt: {$description}", $reference, [
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

        
        $txn = $this->journal->postEntry("Bank payment: {$description}", $reference, [
            ['account_code' => $debitAccountCode, 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '112', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'type' => 'bank_payment'];
    }

    // ── Bank Interest (Dr 112 — Cr 515) ──

    public function recordBankInterest(float $amount, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        
        $txn = $this->journal->postEntry("Bank interest: {$description}", $reference, [
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

        
        $txn = $this->journal->postEntry("Bank charge: {$description}", $reference, [
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

        
        $txn = $this->journal->postEntry("Cash in transit: {$description}", $reference, [
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

        $stmt = $this->pdo->prepare('SELECT amount FROM cash_transit WHERE id = ? AND status = ?');
        $stmt->execute([$transitId, 'in_transit']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \InvalidArgumentException("Transit record not found or already resolved: {$transitId}");
        }

        $amount = (float)$row['amount'];
        
        $txn = $this->journal->postEntry("Transit confirmed: bank credited", "CNF-{$transitId}", [
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
        

        if ($this->pdo) {
            $stmt = $this->pdo->prepare('SELECT amount FROM cash_transit WHERE id = ? AND status = ?');
            $stmt->execute([$transitId, 'in_transit']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $amount = (float)$row['amount'];
                $this->pdo->prepare(
                    'UPDATE cash_transit SET status=? WHERE id=?'
                )->execute(['reversed', $transitId]);

                $txn = $this->journal->postEntry("Transit reversed", "REV-{$transitId}", [
                    ['account_code' => '111', 'amount' => $amount, 'is_debit' => true],
                    ['account_code' => '113', 'amount' => $amount, 'is_debit' => false],
                ], $createdBy);

                return ['transaction_id' => $txn->getId(), 'transit_id' => $transitId, 'type' => 'transit_reverse'];
            }
            throw new \InvalidArgumentException("Transit record not found or already resolved: {$transitId}");
        }

        throw new \RuntimeException('PDO not available for transit tracking');
    }

    // ── Cash Book (computed view of TK 111 ledger entries with running balance) ──

    public function getCashBook(string $fromDate = null, string $toDate = null): array
    {
        if (!$this->pdo) {
            throw new \RuntimeException('PDO not available for cash book');
        }

        $sql = "SELECT t.id, t.description, t.reference, t.created_at, le.amount, le.is_debit
                FROM ledger_entries le
                JOIN transactions t ON t.id = le.transaction_id
                JOIN accounts a ON a.id = le.account_id
                WHERE a.code = '111'";
        $params = [];
        if ($fromDate) {
            $sql .= " AND t.created_at >= ?";
            $params[] = $fromDate;
        }
        if ($toDate) {
            $sql .= " AND t.created_at <= ?";
            $params[] = $toDate . ' 23:59:59';
        }
        $sql .= " ORDER BY t.created_at ASC, t.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $running = 0.0;
        $entries = [];
        foreach ($rows as $r) {
            $amt = (float)$r['amount'];
            if ($r['is_debit']) {
                $running += $amt;
            } else {
                $running -= $amt;
            }
            $entries[] = [
                'date' => $r['created_at'],
                'reference' => $r['reference'],
                'description' => $r['description'],
                'receipt_amount' => $r['is_debit'] ? $amt : 0,
                'payment_amount' => $r['is_debit'] ? 0 : $amt,
                'balance' => round($running, 2),
            ];
        }

        return $entries;
    }

    // ── Foreign Currency ──

    public function recordReceiptFC(float $fcAmount, string $creditAccountCode, string $currencyCode, float $exchangeRate, string $description, string $reference, string $createdBy): array
    {
        if ($fcAmount <= 0) throw new \InvalidArgumentException('Amount must be positive');
        if ($exchangeRate <= 0) throw new \InvalidArgumentException('Exchange rate must be positive');

        $vndAmount = round($fcAmount * $exchangeRate);

        $creditAccount = $this->accountRepo->findByCode($creditAccountCode);
        if (!$creditAccount) throw new \InvalidArgumentException("Account not found: {$creditAccountCode}");

        
        $txn = $this->journal->postEntry("FC receipt: {$description}", $reference, [
            ['account_code' => '112', 'amount' => $vndAmount, 'is_debit' => true],
            ['account_code' => $creditAccountCode, 'amount' => $vndAmount, 'is_debit' => false],
        ], $createdBy);

        $this->recordFCTransaction($txn->getId(), '112', $currencyCode, $fcAmount, $exchangeRate, $vndAmount, 'receipt', $description);

        return ['transaction_id' => $txn->getId(), 'fc_amount' => $fcAmount, 'vnd_amount' => $vndAmount, 'rate' => $exchangeRate, 'currency' => $currencyCode, 'type' => 'fc_receipt'];
    }

    public function recordPaymentFC(float $fcAmount, string $debitAccountCode, string $currencyCode, float $exchangeRate, string $description, string $reference, string $createdBy): array
    {
        if ($fcAmount <= 0) throw new \InvalidArgumentException('Amount must be positive');
        if ($exchangeRate <= 0) throw new \InvalidArgumentException('Exchange rate must be positive');

        $vndAmount = round($fcAmount * $exchangeRate);

        $bank = $this->accountRepo->findByCode('112');
        if ($bank && $bank->getBalance() < $vndAmount) {
            throw new \InvalidArgumentException("Insufficient bank balance: have {$bank->getBalance()}, need {$vndAmount}");
        }

        $debitAccount = $this->accountRepo->findByCode($debitAccountCode);
        if (!$debitAccount) throw new \InvalidArgumentException("Account not found: {$debitAccountCode}");

        
        $txn = $this->journal->postEntry("FC payment: {$description}", $reference, [
            ['account_code' => $debitAccountCode, 'amount' => $vndAmount, 'is_debit' => true],
            ['account_code' => '112', 'amount' => $vndAmount, 'is_debit' => false],
        ], $createdBy);

        $this->recordFCTransaction($txn->getId(), '112', $currencyCode, -$fcAmount, $exchangeRate, -$vndAmount, 'payment', $description);

        return ['transaction_id' => $txn->getId(), 'fc_amount' => -$fcAmount, 'vnd_amount' => $vndAmount, 'rate' => $exchangeRate, 'currency' => $currencyCode, 'type' => 'fc_payment'];
    }

    public function getFCBalances(): array
    {
        if (!$this->pdo) return [];
        $rows = $this->pdo->query(
            "SELECT account_code, currency_code as currency, 
                    SUM(fc_amount) as fc_balance,
                    SUM(vnd_amount) as vnd_balance,
                    COUNT(*) as transaction_count
             FROM fc_transactions 
             GROUP BY account_code, currency_code
             ORDER BY currency_code"
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn($r) => [
            'account' => $r['account_code'],
            'currency' => $r['currency'],
            'fc_balance' => (float)$r['fc_balance'],
            'vnd_balance' => (float)$r['vnd_balance'],
            'avg_rate' => (float)$r['fc_balance'] != 0 
                ? round((float)$r['vnd_balance'] / (float)$r['fc_balance'], 2) 
                : 0,
            'transaction_count' => (int)$r['transaction_count'],
        ], $rows);
    }

    public function revalueFC(string $accountCode, string $currencyCode, float $closingRate, string $asOfDate, string $createdBy): array
    {
        $balances = $this->getFCBalances();
        $entry = current(array_filter($balances, fn($b) => $b['account'] === $accountCode && $b['currency'] === $currencyCode));

        if (!$entry || abs($entry['fc_balance']) < 0.01) {
            return ['transaction_id' => null, 'gain_loss' => 0, 'message' => 'No FC balance to revalue'];
        }

        $bookRate = $entry['avg_rate'];
        $fcBalance = $entry['fc_balance'];
        $currentVnd = $entry['vnd_balance'];
        $revaluedVnd = round($fcBalance * $closingRate);
        $gainLoss = $revaluedVnd - $currentVnd;

        if (abs($gainLoss) < 1) {
            return ['transaction_id' => null, 'gain_loss' => 0, 'message' => 'No gain/loss'];
        }

        

        if ($gainLoss > 0) {
            // Unrealized gain: Dr 112 — Cr 413
            $txn = $this->journal->postEntry("FC revaluation: {$currencyCode} gain", "REV-{$currencyCode}-{$asOfDate}", [
                ['account_code' => $accountCode, 'amount' => $gainLoss, 'is_debit' => true],
                ['account_code' => '413', 'amount' => $gainLoss, 'is_debit' => false],
            ], $createdBy);
        } else {
            // Unrealized loss: Dr 413 — Cr 112
            $loss = abs($gainLoss);
            $txn = $this->journal->postEntry("FC revaluation: {$currencyCode} loss", "REV-{$currencyCode}-{$asOfDate}", [
                ['account_code' => '413', 'amount' => $loss, 'is_debit' => true],
                ['account_code' => $accountCode, 'amount' => $loss, 'is_debit' => false],
            ], $createdBy);
        }

        $this->recordFCTransaction($txn->getId(), $accountCode, $currencyCode, 0, $closingRate, $gainLoss, 'revaluation', "Period-end FX revaluation adj");

        return ['transaction_id' => $txn->getId(), 'gain_loss' => $gainLoss, 'book_rate' => $bookRate, 'closing_rate' => $closingRate, 'fc_balance' => $fcBalance];
    }

    private function recordFCTransaction(string $transactionId, string $accountCode, string $currencyCode, float $fcAmount, float $exchangeRate, float $vndAmount, string $type, string $description): void
    {
        if (!$this->pdo) return;
        $this->pdo->prepare(
            'INSERT INTO fc_transactions (transaction_id, account_code, currency_code, fc_amount, exchange_rate, vnd_amount, type, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$transactionId, $accountCode, $currencyCode, $fcAmount, $exchangeRate, $vndAmount, $type, $description]);
    }
}

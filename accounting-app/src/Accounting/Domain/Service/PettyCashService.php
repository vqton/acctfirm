<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;

class PettyCashService
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

    public function establishPettyCash(string $fundName, float $imprestAmount, string $createdBy): array
    {
        if ($imprestAmount <= 0) throw new \InvalidArgumentException('Imprest amount must be positive');

        $fundId = uniqid('pc_');
        if ($this->pdo) {
            $this->pdo->prepare(
                'INSERT INTO petty_cash_funds (id, fund_name, imprest_amount, current_balance, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$fundId, $fundName, $imprestAmount, $imprestAmount, 'active', $createdBy]);
        }

        return ['fund_id' => $fundId, 'fund_name' => $fundName, 'imprest_amount' => $imprestAmount, 'current_balance' => $imprestAmount];
    }

    public function disbursePettyCash(string $fundId, float $amount, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        $fund = $this->getPettyCashFundById($fundId);
        if (!$fund) throw new \InvalidArgumentException("Petty cash fund not found: {$fundId}");
        if ($fund['status'] !== 'active') throw new \InvalidArgumentException('Fund is not active');
        if ($fund['current_balance'] < $amount) {
            throw new \InvalidArgumentException("Insufficient fund balance: have {$fund['current_balance']}, need {$amount}");
        }

        $txId = uniqid('pctx_');
        if ($this->pdo) {
            $this->pdo->prepare(
                'INSERT INTO petty_cash_transactions (id, fund_id, amount, type, description, reference, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$txId, $fundId, $amount, 'disbursement', $description, $reference, $createdBy]);

            $this->pdo->prepare(
                'UPDATE petty_cash_funds SET current_balance = current_balance - ? WHERE id = ?'
            )->execute([$amount, $fundId]);
        }

        return ['transaction_id' => $txId, 'amount' => $amount, 'type' => 'disbursement'];
    }

    public function replenishPettyCash(string $fundId, string $expenseAccount, float $totalAmount, string $description, string $reference, string $createdBy): array
    {
        $fund = $this->getPettyCashFundById($fundId);
        if (!$fund) throw new \InvalidArgumentException("Petty cash fund not found: {$fundId}");
        if ($fund['status'] !== 'active') throw new \InvalidArgumentException('Fund is not active');

        if ($totalAmount <= 0) throw new \InvalidArgumentException('Amount must be positive');

        $txn = $this->journal->postEntry("Petty cash replenishment: {$description}", $reference, [
            ['account_code' => $expenseAccount, 'amount' => $totalAmount, 'is_debit' => true],
            ['account_code' => '111', 'amount' => $totalAmount, 'is_debit' => false],
        ], $createdBy);

        if ($this->pdo) {
            $txId = uniqid('pctx_');
            $this->pdo->prepare(
                'INSERT INTO petty_cash_transactions (id, fund_id, amount, type, description, reference, expense_account, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$txId, $fundId, $totalAmount, 'replenishment', $description, $reference, $expenseAccount, $createdBy]);

            $this->pdo->prepare(
                'UPDATE petty_cash_funds SET current_balance = imprest_amount WHERE id = ?'
            )->execute([$fundId]);
        }

        return ['transaction_id' => $txn->getId(), 'amount' => $totalAmount, 'type' => 'replenishment'];
    }

    public function closePettyCash(string $fundId, float $returnAmount, string $createdBy): array
    {
        $fund = $this->getPettyCashFundById($fundId);
        if (!$fund) throw new \InvalidArgumentException("Petty cash fund not found: {$fundId}");
        if ($fund['status'] !== 'active') throw new \InvalidArgumentException('Fund is not active');

        $txn = $this->journal->postEntry("Petty cash fund closure: {$fund['fund_name']}", "CLOSE-{$fundId}", [
            ['account_code' => '111', 'amount' => $returnAmount, 'is_debit' => true],
            ['account_code' => '111', 'amount' => $returnAmount, 'is_debit' => false],
        ], $createdBy);

        if ($this->pdo) {
            $txId = uniqid('pctx_');
            $this->pdo->prepare(
                'INSERT INTO petty_cash_transactions (id, fund_id, amount, type, description, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$txId, $fundId, $returnAmount, 'closure', 'Fund closed, cash returned', $createdBy]);

            $this->pdo->prepare(
                'UPDATE petty_cash_funds SET current_balance = 0, status = ? WHERE id = ?'
            )->execute(['closed', $fundId]);
        }

        return ['transaction_id' => $txn->getId(), 'fund_id' => $fundId, 'type' => 'closure'];
    }

    public function getPettyCashFunds(): array
    {
        if (!$this->pdo) return [];
        $rows = $this->pdo->query('SELECT * FROM petty_cash_funds ORDER BY created_at DESC')->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => [
            'id' => $r['id'], 'fund_name' => $r['fund_name'],
            'imprest_amount' => (float)$r['imprest_amount'],
            'current_balance' => (float)$r['current_balance'],
            'status' => $r['status'], 'created_by' => $r['created_by'],
            'created_at' => $r['created_at'],
        ], $rows);
    }

    public function getPettyCashTransactions(string $fundId): array
    {
        if (!$this->pdo) return [];
        $stmt = $this->pdo->prepare('SELECT * FROM petty_cash_transactions WHERE fund_id = ? ORDER BY created_at DESC');
        $stmt->execute([$fundId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getPettyCashFundById(string $id): ?array
    {
        if (!$this->pdo) return null;
        $stmt = $this->pdo->prepare('SELECT * FROM petty_cash_funds WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }
}

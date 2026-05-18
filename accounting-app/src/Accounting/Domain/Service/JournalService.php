<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Model\LedgerEntry;
use Accounting\Domain\Model\Transaction;
use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;

class JournalService
{
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;
    private ?\PDO $pdo;
    private ?AuditLoggerInterface $auditLogger;

    public function __construct(
        AccountRepositoryInterface $accountRepo,
        TransactionRepositoryInterface $txnRepo,
        ?\PDO $pdo = null,
        ?AuditLoggerInterface $auditLogger = null
    ) {
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
        $this->pdo = $pdo;
        $this->auditLogger = $auditLogger;
    }

    /**
     * Create a draft journal entry: validates Dr=Cr, saves as 'pending' without balance changes.
     */
    public function createDraft(string $description, string $reference, array $lines, string $createdBy, bool $allowControl = false): Transaction
    {
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('Journal entry must have at least 2 lines');
        }

        $totalDr = 0.0;
        $totalCr = 0.0;
        $entryLines = [];

        foreach ($lines as $line) {
            if ($line['amount'] <= 0) {
                throw new \InvalidArgumentException('Amount must be positive');
            }
            $account = $this->accountRepo->findByCode($line['account_code']);
            if (!$account) {
                throw new \InvalidArgumentException("Account not found: {$line['account_code']}");
            }
            if ($account->isControl() && !$allowControl) {
                throw new \InvalidArgumentException(
                    "Account {$line['account_code']} ({$account->getName()}) is a control account"
                );
            }
            if ($line['is_debit']) $totalDr += $line['amount'];
            else $totalCr += $line['amount'];
            $entryLines[] = new LedgerEntry(uniqid('led_'), $account->getId(), $line['amount'], $line['is_debit']);
        }

        if (abs($totalDr - $totalCr) > 10) {
            throw new \InvalidArgumentException("Debit ($totalDr) does not equal Credit ($totalCr)");
        }

        $txn = new Transaction(uniqid('jrn_'), new \DateTimeImmutable(), $description, $reference);
        foreach ($entryLines as $e) $txn->addLedgerEntry($e);
        $txn->setCreatedBy($createdBy);
        $this->txnRepo->save($txn);

        $this->auditLogger?->log('journal.draft', 'transaction', $txn->getId(), null, [
            'reference' => $reference, 'description' => $description,
            'total_dr' => $totalDr, 'total_cr' => $totalCr,
        ], $createdBy);

        return $txn;
    }

    /**
     * Approve and post a draft: applies balance changes atomically.
     */
    public function approveDraft(string $txnId, string $approvedBy): Transaction
    {
        $txn = $this->txnRepo->findById($txnId);
        if (!$txn || $txn->getStatus() !== 'pending') {
            throw new \InvalidArgumentException('Draft not found or already posted');
        }

        if (!PeriodService::isPeriodOpen(date('Y-m-d'), $this->pdo)) {
            throw new \RuntimeException('Cannot post: current date is in a closed period');
        }

        $inTransaction = $this->pdo !== null;
        if ($inTransaction) $this->pdo->beginTransaction();

        try {
            foreach ($txn->getLedgerEntries() as $entry) {
                $account = $this->accountRepo->findById($entry->getAccountId());
                if (!$account) continue;
                if ($entry->isDebit()) {
                    if (in_array($account->getType(), ['asset', 'expense'])) $account->credit($entry->getAmount());
                    else $account->debit($entry->getAmount());
                } else {
                    if (in_array($account->getType(), ['liability', 'equity', 'revenue'])) $account->credit($entry->getAmount());
                    else $account->debit($entry->getAmount());
                }
                $this->accountRepo->save($account);
            }

            $txn->post($approvedBy);
            $this->txnRepo->save($txn);

            if ($inTransaction) $this->pdo->commit();

            $this->auditLogger?->log('journal.approve', 'transaction', $txn->getId(),
                ['status' => 'pending'], ['status' => 'posted'], $approvedBy);

            return $txn;
        } catch (\Exception $e) {
            if ($inTransaction) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Post a journal entry: validates Dr=Cr first, then applies balance changes atomically.
     * Control accounts (Level 1 parent accounts with sub-accounts) are blocked.
     * Set $allowControl to true for Chief Accountant override.
     */
    public function postEntry(string $description, string $reference, array $lines, string $createdBy, bool $allowControl = false): Transaction
    {
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('Journal entry must have at least 2 lines');
        }

        if (!PeriodService::isPeriodOpen(date('Y-m-d'), $this->pdo)) {
            throw new \RuntimeException('Cannot post: current date is in a closed period');
        }

        $inTransaction = $this->pdo !== null;
        if ($inTransaction) $this->pdo->beginTransaction();

        try {
            // Phase 1: Validate all lines + compute totals (NO balance changes yet)
            $totalDr = 0.0;
            $totalCr = 0.0;
            $validated = [];

            foreach ($lines as $line) {
                if ($line['amount'] <= 0) {
                    throw new \InvalidArgumentException('Amount must be positive');
                }

                $account = $this->accountRepo->findByCode($line['account_code']);
                if (!$account) {
                    throw new \InvalidArgumentException("Account not found: {$line['account_code']}");
                }

                // BR15: Block posting to control accounts unless override
                if ($account->isControl() && !$allowControl) {
                    throw new \InvalidArgumentException(
                        "Account {$line['account_code']} ({$account->getName()}) is a control account — post to a detail sub-account instead"
                    );
                }

                if ($line['is_debit']) {
                    $totalDr += $line['amount'];
                } else {
                    $totalCr += $line['amount'];
                }

                $validated[] = ['account' => $account, 'amount' => $line['amount'], 'is_debit' => $line['is_debit']];
            }

            // Phase 2: Check Dr = Cr BEFORE any balance changes
            if (abs($totalDr - $totalCr) > 10) {
                throw new \InvalidArgumentException(
                    "Debit ({$totalDr}) does not equal Credit ({$totalCr})"
                );
            }

            // Phase 3: Apply balance changes + create ledger entries
            $entryLines = [];
            foreach ($validated as $v) {
                $account = $this->accountRepo->findByCode($v['account']->getCode());
                if ($v['is_debit']) {
                    if (in_array($account->getType(), ['asset', 'expense'])) {
                        $account->credit($v['amount']);
                    } else {
                        $account->debit($v['amount']);
                    }
                } else {
                    if (in_array($account->getType(), ['liability', 'equity', 'revenue'])) {
                        $account->credit($v['amount']);
                    } else {
                        $account->debit($v['amount']);
                    }
                }
                $this->accountRepo->save($account);
                $entryLines[] = new LedgerEntry(uniqid('led_'), $account->getId(), $v['amount'], $v['is_debit']);
            }

            // Phase 4: Create and persist transaction
            $txn = new Transaction(uniqid('jrn_'), new \DateTimeImmutable(), $description, $reference);
            foreach ($entryLines as $entry) {
                $txn->addLedgerEntry($entry);
            }
            $txn->post($createdBy);
            $this->txnRepo->save($txn);

            if ($inTransaction) $this->pdo->commit();

            $this->auditLogger?->log('journal.post', 'transaction', $txn->getId(), null, [
                'reference' => $reference,
                'description' => $description,
                'total_dr' => $totalDr,
                'total_cr' => $totalCr,
                'lines' => array_map(fn($l) => [
                    'account_code' => $l['account']->getCode(),
                    'amount' => $l['amount'],
                    'is_debit' => $l['is_debit'],
                ], $validated),
            ], $createdBy);

            return $txn;
        } catch (\Exception $e) {
            if ($inTransaction) $this->pdo->rollBack();
            throw $e;
        }
    }
}
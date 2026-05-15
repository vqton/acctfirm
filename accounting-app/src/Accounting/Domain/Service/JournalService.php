<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Model\LedgerEntry;
use Accounting\Domain\Model\Transaction;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;
use Accounting\Infrastructure\Database\AuditLogger;

class JournalService
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

        if (!PeriodService::isPeriodOpen(date('Y-m-d'))) {
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

            AuditLogger::log('journal.post', 'transaction', $txn->getId(), null, [
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
<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Repository\CcdcRepositoryInterface;

class CcdcAllocationService
{
    private CcdcRepositoryInterface $ccdcRepo;
    private JournalService $journal;
    private ?\PDO $pdo;
    private ?AuditLoggerInterface $auditLogger;

    public function __construct(
        CcdcRepositoryInterface $ccdcRepo,
        JournalService $journal,
        ?\PDO $pdo = null,
        ?AuditLoggerInterface $auditLogger = null
    ) {
        $this->ccdcRepo = $ccdcRepo;
        $this->journal = $journal;
        $this->pdo = $pdo;
        $this->auditLogger = $auditLogger;
    }

    public function runMonthlyAllocation(string $period, string $createdBy): array
    {
        $items = $this->ccdcRepo->findPendingAllocation();
        $results = [];

        foreach ($items as $ccdc) {
            $startDate = $ccdc->getAllocationStartDate();
            if (!$startDate) continue;

            $startMonth = substr($startDate, 0, 7);
            if ($period < $startMonth) continue;

            $monthsElapsed = $this->monthsBetween($startMonth, $period);
            if ($monthsElapsed < 0) continue;

            $expectedAllocations = $monthsElapsed + 1;
            $monthlyAmount = $ccdc->getTotalCost() / $ccdc->getAllocationMonths();
            $shouldBeAllocated = $monthlyAmount * $expectedAllocations;
            $alreadyAllocated = $ccdc->getAllocated();
            $remainingToAllocate = $shouldBeAllocated - $alreadyAllocated;

            if ($remainingToAllocate <= 100) continue;

            $remainingMonths = $ccdc->getRemainingMonths();
            $expenseAccount = $ccdc->getExpenseAccount();

            try {
                $txn = $this->journal->postEntry(
                    description: "Phân bổ CCDC {$ccdc->getCode()} - {$ccdc->getName()} kỳ {$period}",
                    reference: '',
                    lines: [
                        ['account_code' => $expenseAccount, 'amount' => $remainingToAllocate, 'is_debit' => true],
                        ['account_code' => '242', 'amount' => $remainingToAllocate, 'is_debit' => false],
                    ],
                    createdBy: $createdBy,
                    module: 'ccdc',
                    date: $period . '-01',
                    voucherType: 'JV'
                );

                $newAllocated = $alreadyAllocated + $remainingToAllocate;
                $newRemaining = max(0, $remainingMonths - 1);
                $ccdc->setAllocated($newAllocated);
                $ccdc->setRemainingMonths($newRemaining);
                $this->ccdcRepo->save($ccdc);

                if ($this->pdo) {
                    $stmt = $this->pdo->prepare(
                        'INSERT INTO ccdc_allocations (id, ccdc_id, period, amount, expense_account, transaction_id, status, created_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        uniqid('ccdca_'), $ccdc->getId(), $period, $remainingToAllocate,
                        $expenseAccount, $txn->getId(), 'posted', $createdBy
                    ]);
                }

                $this->auditLogger?->log('ccdc.allocate', 'ccdc', $ccdc->getId(),
                    ['allocated_before' => $alreadyAllocated],
                    ['period' => $period, 'amount' => $remainingToAllocate, 'expense_account' => $expenseAccount],
                    $createdBy);

                $results[] = [
                    'ccdc_id' => $ccdc->getId(),
                    'code' => $ccdc->getCode(),
                    'name' => $ccdc->getName(),
                    'amount' => $remainingToAllocate,
                    'transaction_id' => $txn->getId(),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'ccdc_id' => $ccdc->getId(),
                    'code' => $ccdc->getCode(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function getAllocationHistory(?string $ccdcId = null): array
    {
        if (!$this->pdo) return [];
        if ($ccdcId) {
            $stmt = $this->pdo->prepare(
                'SELECT a.*, c.code, c.name FROM ccdc_allocations a JOIN ccdc c ON c.id = a.ccdc_id WHERE a.ccdc_id = ? ORDER BY a.period DESC'
            );
            $stmt->execute([$ccdcId]);
        } else {
            $stmt = $this->pdo->query(
                'SELECT a.*, c.code, c.name FROM ccdc_allocations a JOIN ccdc c ON c.id = a.ccdc_id ORDER BY a.period DESC, a.created_at DESC LIMIT 200'
            );
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function monthsBetween(string $start, string $end): int
    {
        $s = explode('-', $start);
        $e = explode('-', $end);
        return ((int)$e[0] - (int)$s[0]) * 12 + ((int)$e[1] - (int)$s[1]);
    }
}

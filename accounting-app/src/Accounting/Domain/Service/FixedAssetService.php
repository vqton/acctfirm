<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Model\FixedAsset;
use Accounting\Domain\Repository\FixedAssetRepositoryInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;
use Accounting\Domain\Contract\AuditLoggerInterface;

class FixedAssetService
{
    private FixedAssetRepositoryInterface $faRepo;
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;
    private JournalService $journalService;
    private ?\PDO $pdo;
    private ?AuditLoggerInterface $auditLogger;

    public function __construct(
        FixedAssetRepositoryInterface $faRepo,
        AccountRepositoryInterface $accountRepo,
        TransactionRepositoryInterface $txnRepo,
        JournalService $journalService,
        ?\PDO $pdo = null,
        ?AuditLoggerInterface $auditLogger = null
    ) {
        $this->faRepo = $faRepo;
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
        $this->journalService = $journalService;
        $this->pdo = $pdo;
        $this->auditLogger = $auditLogger;
    }

    public function calculateMonthlyDepreciation(FixedAsset $asset, ?float $actualUnits = null): float
    {
        if ($asset->getStatus() !== 'in_use') return 0;
        $ng = $asset->getOriginalCost();
        $sv = $asset->getSalvageValue();
        $life = $asset->getUsefulLife();
        $depreciable = $ng - $sv;
        $method = $asset->getDepreciationMethod();

        if ($method === 'production') {
            return $this->calcProduction($depreciable, $asset->getTotalEstimatedUnits(), $actualUnits);
        }

        if ($depreciable <= 0 || $life <= 0) return 0;

        $accumulated = $asset->getAccumulatedDepreciation();
        $remaining = $depreciable - $accumulated;
        if ($remaining <= 0) return 0;

        return match ($method) {
            'straight_line' => $this->calcStraightLine($depreciable, $life, $accumulated),
            'declining_balance' => $this->calcDecliningBalance($ng, $depreciable, $life, $accumulated),
            'sum_of_years' => $this->calcSumOfYears($depreciable, $life, $accumulated),
            default => 0,
        };
    }

    public function postMonthlyDepreciation(string $period, string $createdBy): array
    {
        if (!PeriodService::isPeriodOpen($period . '-01', $this->pdo)) {
            throw new \RuntimeException("Period {$period} is closed");
        }

        $assets = $this->faRepo->findAll();
        $results = [];

        $inTransaction = $this->pdo !== null && !$this->pdo->inTransaction();
        if ($inTransaction) $this->pdo->beginTransaction();

        try {
            foreach ($assets as $asset) {
                if ($asset->getStatus() !== 'in_use') continue;
                $amount = $this->calculateMonthlyDepreciation($asset);
                if ($amount <= 0) continue;

                $depAccount = $this->resolveDepreciationAccount($asset);

                $txn = $this->journalService->postEntry(
                    "Trich khau hao TSCD {$asset->getCode()} - {$asset->getName()} thang {$period}",
                    "KH-{$asset->getCode()}-{$period}",
                    [
                        ['account_code' => $depAccount['cost'], 'amount' => $amount, 'is_debit' => true],
                        ['account_code' => $depAccount['accum'], 'amount' => $amount, 'is_debit' => false],
                    ],
                    $createdBy
                );

                $accumBefore = $asset->getAccumulatedDepreciation();
                $nbvBefore = $asset->getNetBookValue();
                $newAccum = $accumBefore + $amount;
                $newNbv = $asset->getOriginalCost() - $newAccum;

                $asset->setAccumulatedDepreciation($newAccum);
                $asset->setNetBookValue($newNbv);
                $asset->setMonthlyDepreciation($amount);
                $this->faRepo->save($asset);

                $depId = uniqid('fad_');
                $this->saveDepreciationRecord($depId, $asset->getId(), $period, $amount,
                    $accumBefore, $newAccum, $nbvBefore, $newNbv, $txn->getId());

                $results[] = [
                    'asset_id' => $asset->getId(),
                    'asset_code' => $asset->getCode(),
                    'amount' => $amount,
                    'transaction_id' => $txn->getId(),
                ];
            }

            if ($inTransaction) $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($inTransaction) $this->pdo->rollBack();
            throw $e;
        }

        $this->auditLogger?->log('depreciation.post', 'fixed_asset_depreciation', $period, null, [
            'period' => $period, 'entries' => count($results), 'total_amount' => array_sum(array_column($results, 'amount')),
        ], $createdBy);

        return $results;
    }

    public function getDepreciationHistory(string $fixedAssetId): array
    {
        if (!$this->pdo) return [];
        $stmt = $this->pdo->prepare(
            'SELECT * FROM fixed_asset_depreciation WHERE fixed_asset_id = ? ORDER BY period ASC'
        );
        $stmt->execute([$fixedAssetId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getDepreciationByPeriod(string $period): array
    {
        if (!$this->pdo) return [];
        $stmt = $this->pdo->prepare(
            'SELECT fad.*, fa.code as asset_code, fa.name as asset_name
             FROM fixed_asset_depreciation fad
             JOIN fixed_assets fa ON fa.id = fad.fixed_asset_id
             WHERE fad.period = ? ORDER BY fa.code'
        );
        $stmt->execute([$period]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function calculateSchedule(FixedAsset $asset): array
    {
        $ng = $asset->getOriginalCost();
        $sv = $asset->getSalvageValue();
        $life = $asset->getUsefulLife();
        $depreciable = $ng - $sv;
        if ($depreciable <= 0 || $life <= 0) return [];

        $schedule = [];
        $accumulated = 0;

        for ($year = 1; $year <= $life; $year++) {
            $yearlyDep = 0.0;
            $remaining = $depreciable - $accumulated;
            if ($remaining <= 0) break;

            $yearlyDep = match ($asset->getDepreciationMethod()) {
                'straight_line' => min($depreciable / $life, $remaining),
                'declining_balance' => $this->calcDecliningBalanceYearly($ng, $depreciable, $life, $accumulated),
                'sum_of_years' => $this->calcSumOfYearsYearly($depreciable, $life, $accumulated, $year),
                'production' => 0,
                default => 0,
            };

            if ($yearlyDep <= 0) break;
            $yearlyDep = min($yearlyDep, $remaining);
            $accumulated += $yearlyDep;

            $schedule[] = [
                'year' => $year,
                'yearly_depreciation' => round($yearlyDep, 2),
                'accumulated_depreciation' => round($accumulated, 2),
                'net_book_value' => round($ng - $accumulated, 2),
            ];
        }

        return $schedule;
    }

    private function calcStraightLine(float $depreciable, int $life, float $accumulated): float
    {
        $monthly = $depreciable / ($life * 12);
        $remaining = $depreciable - $accumulated;
        return min($monthly, $remaining);
    }

    private function calcDecliningBalance(float $ng, float $depreciable, int $life, float $accumulated): float
    {
        $remaining = $ng - $accumulated;
        if ($remaining <= 0) return 0;

        $straightRate = 1 / $life;
        $factor = match (true) {
            $life >= 4 => 2.0,
            $life >= 3 => 2.5,
            default => 3.0,
        };
        $decliningRate = $straightRate * $factor;
        $yearlyDeclining = $remaining * $decliningRate;

        $yearsRemaining = $life - (int)($accumulated / ($depreciable / $life));
        $yearlyStraight = $yearsRemaining > 0 ? ($depreciable - $accumulated) / $yearsRemaining : 0;

        $yearly = max($yearlyDeclining, $yearlyStraight);
        $monthly = $yearly / 12;
        $remaining = $depreciable - $accumulated;
        return min($monthly, $remaining);
    }

    private function calcDecliningBalanceYearly(float $ng, float $depreciable, int $life, float $accumulated): float
    {
        $remaining = $ng - $accumulated;
        if ($remaining <= 0) return 0;

        $straightRate = 1 / $life;
        $factor = match (true) {
            $life >= 4 => 2.0,
            $life >= 3 => 2.5,
            default => 3.0,
        };
        $decliningRate = $straightRate * $factor;
        $yearly = $remaining * $decliningRate;

        $yearsUsed = $accumulated > 0 ? (int)($accumulated / ($depreciable / $life)) : 0;
        $yearsRemaining = $life - $yearsUsed;
        if ($yearsRemaining <= 0) return 0;
        $yearlyStraight = ($depreciable - $accumulated) / $yearsRemaining;

        return max($yearly, $yearlyStraight);
    }

    private function calcSumOfYears(float $depreciable, int $life, float $accumulated): float
    {
        $yearsRemaining = $life - (int)($accumulated / ($depreciable / $life));
        if ($yearsRemaining <= 0) return 0;

        $sumOfYears = $life * ($life + 1) / 2;
        $yearFraction = $yearsRemaining / $sumOfYears;
        $yearly = $depreciable * $yearFraction;

        $remaining = $depreciable - $accumulated;
        $monthly = $yearly / 12;
        return min($monthly, $remaining);
    }

    private function calcSumOfYearsYearly(float $depreciable, int $life, float $accumulated, int $currentYear): float
    {
        $yearInLife = $currentYear;
        $yearsRemaining = $life - $yearInLife + 1;
        if ($yearsRemaining <= 0) return 0;

        $sumOfYears = $life * ($life + 1) / 2;
        $yearFraction = $yearsRemaining / $sumOfYears;
        return $depreciable * $yearFraction;
    }

    private function calcProduction(float $depreciable, ?float $totalUnits, ?float $actualUnits): float
    {
        if (!$totalUnits || $totalUnits <= 0 || !$actualUnits || $actualUnits <= 0) return 0;
        $perUnit = $depreciable / $totalUnits;
        return $perUnit * $actualUnits;
    }

    private function resolveDepreciationAccount(FixedAsset $asset): array
    {
        $category = $asset->getFaCategory() ?? 'tangible';
        $accum = match ($category) {
            'tangible' => '2141',
            'finance_lease' => '2142',
            'intangible' => '2143',
            default => '2141',
        };
        return ['cost' => '627', 'accum' => $accum];
    }

    private function saveDepreciationRecord(
        string $id, string $faId, string $period, float $amount,
        float $accBefore, float $accAfter, float $nbvBefore, float $nbvAfter,
        ?string $txnId
    ): void {
        if (!$this->pdo) return;
        $stmt = $this->pdo->prepare(
            'INSERT INTO fixed_asset_depreciation (id, fixed_asset_id, period, depreciation_amount,
             accumulated_before, accumulated_after, net_book_before, net_book_after, transaction_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $faId, $period, $amount, $accBefore, $accAfter, $nbvBefore, $nbvAfter, $txnId]);
    }
}

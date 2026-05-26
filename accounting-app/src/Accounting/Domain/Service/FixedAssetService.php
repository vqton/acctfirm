<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Model\FixedAsset;
use Accounting\Domain\Repository\FixedAssetRepositoryInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;
use Accounting\Domain\Contract\AuditLoggerInterface;

// Dịch vụ quản lý Tài sản cố định (TSCĐ)
//
// Nghiệp vụ: Quản lý toàn bộ vòng đời TSCĐ theo Thông tư 99, bao gồm:
//   - Theo dõi nguyên giá (TK 211, 213), hao mòn lũy kế (TK 214), giá trị còn lại
//   - Tính và ghi nhận khấu hao hàng tháng theo phương pháp:
//       • Straight-line (đường thẳng): Phân bổ đều trong thời gian sử dụng
//       • Declining balance (số dư giảm dần): Khấu hao nhanh, phù hợp máy móc thiết bị
//       • Sum-of-years (tổng số năm): Khấu hao giảm dần theo thời gian
//       • Production (sản lượng): Theo sản lượng thực tế
//   - postMonthlyDepreciation: Ghi nhận khấu hao vào chi phí SXKD (Nợ 627, 641, 642 / Có 214)
//
// Ảnh hưởng:
//   - BC02 chỉ tiêu 24 (Giá vốn hàng bán) và chỉ tiêu 26 (Chi phí QLDN)
//   - BC01 chỉ tiêu 227 (Nguyên giá), 229 (Hao mòn lũy kế)
//   - Thuế TNDN: Khấu hao là chi phí hợp lý được trừ khi tính thuế
//
// RỦI RO: Tính sai khấu hao → sai chi phí → sai lợi nhuận → sai thuế TNDN
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

    // Tính khấu hao tháng hiện tại cho một TSCĐ
    //
    // Input: FixedAsset object + actualUnits (nếu phương pháp sản lượng)
    // Output: Số tiền khấu hao tháng (float)
    //
    // Quy trình:
    //   1. Kiểm tra status = 'in_use' — TSCĐ ngừng sử dụng không tính khấu hao
    //   2. Xác định nguyên giá (original_cost) và giá trị thanh lý (salvage_value)
    //   3. Tính depreciable amount = nguyên giá - giá trị thanh lý
    //   4. Áp dụng phương pháp khấu hao (match method)
    //   5. Giới hạn bởi remaining (không khấu hao quá depreciable amount)
    //
    // Lưu ý phương pháp declining balance:
    //   - Dùng factor theo thông lệ VAS: 2.0 cho tài sản ≥ 4 năm, 2.5 cho ≥ 3 năm, 3.0 cho < 3 năm
    //   - Chuyển sang straight-line khi khấu hao đường thẳng > khấu hao nhanh
    //   - (tại năm cuối, khấu hao nhanh tạo số dư nhỏ hơn straight-line)
    //
    // RỦI RO: Nếu salvage_value sai → depreciable amount sai → khấu hao cả đời TSCĐ sai
    // Ảnh hưởng: Chi phí mỗi tháng lên BC02 chỉ tiêu chi phí, lợi nhuận, thuế TNDN
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

    // Ghi nhận khấu hao hàng tháng cho TOÀN BỘ TSCĐ đang sử dụng
    //
    // Input: period = 'YYYY-MM', createdBy = user ID
    // Output: Mảng results với asset_id, asset_code, amount, transaction_id
    //
    // Quy trình:
    //   1. PeriodService::isPeriodOpen — từ chối nếu kỳ đã đóng
    //   2. findAll → lấy tất cả TSCĐ đang hoạt động
    //   3. Với mỗi TSCĐ: tính khấu hao → postEntry (Nợ TK chi phí / Có TK 214*)
    //   4. Ghi nhận vào depreciation history table
    //   5. Transaction wrap — rollback nếu bất kỳ TSCĐ nào lỗi
    //
    // IDEMPOTENT? KHÔNG. Nếu gọi 2 lần cho cùng period, TSCĐ sẽ bị khấu hao gấp đôi.
    // Caller phải kiểm tra depreciation record đã tồn tại trước khi gọi.
    //
    // RỦI RO: Nếu 1 TSCĐ trong batch bị lỗi, rollback toàn bộ batch.
    // Concurrent: Nếu 2 request cùng gọi cùng lúc → race condition trên accumulated_depreciation.
    //   Cần pessimistic lock (SELECT ... FOR UPDATE) hoặc queue xử lý tuần tự.
    //
    // Hạch toán:
    //   Nợ 627 (SXC) / 641 (Bán hàng) / 642 (QLDN) — tùy bộ phận sử dụng
    //   Có 2141 (Hao mòn TSCĐ hữu hình) / 2142 (Thuê TC) / 2143 (Vô hình)
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
            // Bắt đầu vòng lặp khấu hao từng TSCĐ
            //
            // Transaction boundary: beginTransaction trước khi loop
            // Nếu bất kỳ TSCĐ nào lỗi → rollback toàn bộ (tránh nửa vời)
            //
            // RỦI RO HIỆU NĂNG: Với 500+ TSCĐ, mỗi lần postEntry tạo 1 transaction riêng
            // → có thể timeout. Cân nhắc batch processing cho doanh nghiệp lớn.
            foreach ($assets as $asset) {
                if ($asset->getStatus() !== 'in_use') continue;
                $amount = $this->calculateMonthlyDepreciation($asset);
                if ($amount <= 0) continue;

                // Xác định TK chi phí khấu hao và TK hao mòn lũy kế
                // Dựa trên fa_category: tangible → 2141, finance_lease → 2142, intangible → 2143
                // TK chi phí cố định là 627 (giả định tất cả là SXC)
                // TODO: Phân biệt 627/641/642 theo bộ phận sử dụng thực tế
                $depAccount = $this->resolveDepreciationAccount($asset);

                // Ghi nhận bút toán khấu hao qua JournalService (GL posting engine)
                //
                // Nợ TK chi phí (627/641/642) — làm tăng chi phí, giảm lợi nhuận
                // Có TK hao mòn (214*) — làm giảm giá trị còn lại của TSCĐ
                //
                // Lưu ý: postEntry sẽ tự kiểm tra Dr = Cr, posting rules, period lock
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

            // Commit chỉ khi TẤT CẢ TSCĐ được khấu hao thành công
            // Nếu bất kỳ TSCĐ nào thất bại → rollback → không TSCĐ nào được ghi nhận
            // Điều này đảm bảo tính nhất quán: không có nửa vời giữa các TSCĐ
            // RỦI RO: Nếu commit lỗi (DB connection drop) → catch không bắt được ở đây
            //   → PHP sẽ throw exception ra ngoài → caller phải xử lý
            if ($inTransaction) $this->pdo->commit();
        } catch (\Throwable $e) {
            // Rollback toàn bộ nếu bất kỳ bước nào trong loop thất bại
            // Đảm bảo không có bút toán khấu hao nào được ghi nhận 1 phần
            if ($inTransaction) $this->pdo->rollBack();
            throw $e;
        }

        // Ghi audit log sau khi commit thành công
        // Log tổng quan: period, số lượng entries, tổng tiền khấu hao
        // Phục vụ kiểm toán: trace được ai đã chạy khấu hao tháng nào, tổng bao nhiêu
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

    // Lập lịch khấu hao dự kiến cho toàn bộ vòng đời TSCĐ
    //
    // Input: FixedAsset object
    // Output: Mảng schedule với các năm và giá trị khấu hao tương ứng
    //
    // Mục đích: Cung cấp bảng tính khấu hao để kế toán viên và kiểm toán viên
    // tham chiếu. Không ghi nhận bút toán — chỉ là dự toán.
    //
    // Lưu ý: Production method → return empty (vì phụ thuộc sản lượng thực tế)
    //
    // GIỚI HẠN: Không xử lý trường hợp thay đổi thời gian sử dụng (useful life)
    // hoặc thay đổi phương pháp khấu hao giữa chừng. Nếu có thay đổi, schedule
    // cũ không còn chính xác → cần tính lại từ đầu.
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

    // Xác định tài khoản kế toán cho bút toán khấu hao
    //
    // Đầu ra: ['cost' => TK chi phí, 'accum' => TK hao mòn]
    //
    // Hạn chế hiện tại:
    //   - cost LUÔN là 627 (Chi phí SXC) — giả định mọi TSCĐ đều phục vụ sản xuất
    //   - THIẾU phân biệt: 641 (bán hàng), 642 (QLDN) cho TSCĐ bộ phận gián tiếp
    //   - Ảnh hưởng: Nếu TSCĐ của phòng kế toán mà vào 627 → sai chỉ tiêu BC02
    //
    // TODO: Cần mapping fa_category → cost_account (có thể từ fixed_asset_categories)
    //   Ví dụ: category 'admin' → 642, 'sales' → 641, 'production' → 627
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

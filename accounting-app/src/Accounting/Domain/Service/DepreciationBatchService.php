<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\FixedAssetRepositoryInterface;
use PDO;

// NGHIỆP VỤ: Sinh Mẫu 06-TSCĐ — Bảng tính và phân bổ khấu hao TSCĐ
//
// Service này tạo cấu trúc dữ liệu cho mẫu 06-TSCĐ theo TT 99/2025/TT-BTC:
//   - Dòng I: Số KH trích tháng trước (carry-forward)
//   - Dòng II: Số KH TSCĐ tăng trong tháng
//   - Dòng III: Số KH TSCĐ giảm trong tháng
//   - Dòng IV: Số KH trích tháng này (I + II - III)
//   - Cột: Phân bổ theo TK (627, 641, 642, 623, 241, 242, 335...)
//
class DepreciationBatchService
{
    private PDO $pdo;
    private FixedAssetRepositoryInterface $faRepo;

    public function __construct(PDO $pdo, FixedAssetRepositoryInterface $faRepo)
    {
        $this->pdo = $pdo;
        $this->faRepo = $faRepo;
    }

    // Sinh dữ liệu Mẫu 06-TSCĐ cho kỳ
    public function generateReport(string $period): array
    {
        $current = $this->loadPeriodDepreciation($period);
        $prevPeriod = $this->getPreviousPeriod($period);
        $prevBatch = $this->loadBatch($prevPeriod);
        $prevTotal = $prevBatch ? (float)$prevBatch['total_company'] : 0;

        // Phân bổ theo tài khoản chi phí
        $byAccount = $this->groupByAccount($current['records']);

        // Tính tăng/giảm: so với tháng trước
        $increaseAmount = max(0, $current['total'] - $prevTotal);
        $decreaseAmount = max(0, $prevTotal - $current['total']);

        // Lấy danh sách tài khoản phân bổ
        $accounts = $this->getAllocationAccounts();

        return [
            'period' => $period,
            'prev_period' => $prevPeriod,
            'rows' => [
                'prev_month' => [
                    'label' => 'I. Số khấu hao trích tháng trước',
                    'total' => $prevTotal,
                    'accounts' => $this->mapAccounts($prevBatch, $accounts),
                ],
                'increase' => [
                    'label' => 'II. Số KH TSCĐ tăng trong tháng',
                    'total' => $increaseAmount,
                    'accounts' => [], // chi tiết từng TSCĐ tăng
                ],
                'decrease' => [
                    'label' => 'III. Số KH TSCĐ giảm trong tháng',
                    'total' => $decreaseAmount,
                    'accounts' => [],
                ],
                'current' => [
                    'label' => 'IV. Số KH trích tháng này (I+II-III)',
                    'total' => $current['total'],
                    'accounts' => $byAccount,
                ],
            ],
            'accounts' => $accounts,
            'asset_details' => $current['records'],
            'asset_count' => $current['count'],
            'batch_id' => $current['batch_id'],
        ];
    }

    // Ghi nhận batch vào DB (sau khi chạy depreciation)
    public function saveBatch(string $period, string $createdBy): string
    {
        $report = $this->generateReport($period);
        $id = uniqid('fab_');

        $accounts = $report['rows']['current']['accounts'];
        $stmt = $this->pdo->prepare(
            "INSERT INTO fa_depreciation_batches
             (id, period, status, total_company, total_627, total_641, total_642,
              total_623, total_241, total_242, total_335, total_other,
              prev_month_total, increase_amount, decrease_amount, asset_count, created_by)
             VALUES (?, ?, 'posted', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
             status='posted', total_company=VALUES(total_company),
             total_627=VALUES(total_627), total_641=VALUES(total_641), total_642=VALUES(total_642),
             total_623=VALUES(total_623), total_241=VALUES(total_241), total_242=VALUES(total_242),
             total_335=VALUES(total_335), total_other=VALUES(total_other),
             prev_month_total=VALUES(prev_month_total), increase_amount=VALUES(increase_amount),
             decrease_amount=VALUES(decrease_amount), asset_count=VALUES(asset_count)"
        );
        $stmt->execute([
            $id, $period,
            $report['rows']['current']['total'],
            $accounts['627'] ?? 0, $accounts['641'] ?? 0, $accounts['642'] ?? 0,
            $accounts['623'] ?? 0, $accounts['241'] ?? 0, $accounts['242'] ?? 0,
            $accounts['335'] ?? 0, $accounts['_other'] ?? 0,
            $report['rows']['prev_month']['total'],
            $report['rows']['increase']['total'],
            $report['rows']['decrease']['total'],
            $report['asset_count'],
            $createdBy,
        ]);

        return $id;
    }

    // Load batch đã lưu
    public function loadBatch(string $period): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM fa_depreciation_batches WHERE period = ?"
        );
        $stmt->execute([$period]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // Load chi tiết khấu hao từ fixed_asset_depreciation cho kỳ
    private function loadPeriodDepreciation(string $period): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT fad.*, fa.code AS asset_code, fa.name AS asset_name,
                    fa.department_id, fa.fa_category, fa.original_cost
             FROM fixed_asset_depreciation fad
             JOIN fixed_assets fa ON fa.id = fad.fixed_asset_id
             WHERE fad.period = ?
             ORDER BY fa.code"
        );
        $stmt->execute([$period]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = 0;
        $count = 0;
        foreach ($records as $r) {
            $total += (float)$r['depreciation_amount'];
            $count++;
        }

        // Tìm batch_id nếu có
        $batch = $this->loadBatch($period);
        $batchId = $batch ? $batch['id'] : null;

        return [
            'records' => $records,
            'total' => $total,
            'count' => $count,
            'batch_id' => $batchId,
        ];
    }

    // Phân nhóm khấu hao theo tài khoản chi phí
    private function groupByAccount(array $records): array
    {
        $grouped = [];
        foreach ($records as $r) {
            $deptId = $r['department_id'] ?? null;
            $account = $this->resolveAccountForDepartment($deptId);
            $amount = (float)$r['depreciation_amount'];
            if (!isset($grouped[$account])) $grouped[$account] = 0;
            $grouped[$account] += $amount;
        }
        return $grouped;
    }

    // Tra cứu TK chi phí theo phòng ban
    private function resolveAccountForDepartment(?string $deptId): string
    {
        if (!$deptId) return '627';
        $stmt = $this->pdo->prepare(
            "SELECT debit_account FROM fa_department_accounts WHERE department_id = ?"
        );
        $stmt->execute([$deptId]);
        $account = $stmt->fetchColumn();
        return $account ?: '627';
    }

    // Danh sách TK phân bổ chuẩn (theo Mẫu 06-TSCĐ)
    private function getAllocationAccounts(): array
    {
        return ['627', '623', '641', '642', '241', '242', '335'];
    }

    // Map batch data vào cấu trúc accounts cho view
    private function mapAccounts(?array $batch, array $accounts): array
    {
        if (!$batch) return [];
        $result = [];
        foreach ($accounts as $acc) {
            $key = 'total_' . $acc;
            if (isset($batch[$key])) {
                $result[$acc] = (float)$batch[$key];
            }
        }
        $result['_other'] = (float)($batch['total_other'] ?? 0);
        return $result;
    }

    private function getPreviousPeriod(string $period): string
    {
        $parts = explode('-', $period);
        $year = (int)$parts[0];
        $month = (int)$parts[1];
        if ($month === 1) {
            return ($year - 1) . '-12';
        }
        return $year . '-' . str_pad((string)($month - 1), 2, '0', STR_PAD_LEFT);
    }
}

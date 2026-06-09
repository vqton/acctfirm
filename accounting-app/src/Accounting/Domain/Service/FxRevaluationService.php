<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\JournalServiceInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;

// Dịch vụ đánh giá lại số dư ngoại tệ cuối kỳ (Foreign Currency Revaluation)
//
// Nghiệp vụ: Theo Thông tư 99, cuối mỗi kỳ kế toán, doanh nghiệp phải đánh giá lại
// các khoản mục tiền tệ có gốc ngoại tệ (TK 1112, 1122, 131, 331, 341, 311...)
// theo tỷ giá cuối kỳ. Chênh lệch tỷ giá được hạch toán:
//   - Lỗ: Nợ 635 / Có các TK tiền tệ
//   - Lãi: Nợ các TK tiền tệ / Có 515
//
// Process flow:
//   1. Xác thực kỳ kế toán còn mở
//   2. Lấy tỷ giá cuối kỳ từ exchange_rates (rate gần nhất <= end_date)
//   3. Với mỗi TK tiền tệ, tính số dư ngoại tệ ròng và số dư VND
//   4. Tính lại giá trị VND theo tỷ giá cuối kỳ → chênh lệch unrealized
//   5. Bỏ qua chênh lệch < 100 VND (materiality threshold)
//   6. Gộp tất cả chênh lệch vào một bút toán điều chỉnh duy nhất
//
// Ảnh hưởng thuế:
//   - TK 635 tăng → LN kế toán giảm → Thuế TNDN giảm
//   - TK 515 tăng → LN kế toán tăng → Thuế TNDN tăng
//   - CHƯA THỰC HIỆN (unrealized): chỉ điều chỉnh BCTC, không tính vào TN chịu thuế
    //     cho đến khi thực tế phát sinh (TT 20/2026/TT-BTC, Điều 6)
//   - ĐÃ THỰC HIỆN (realized): ghi nhận vào TN chịu thuế ngay
//
// RỦI RO:
//   - Dùng sai tỷ giá cuối kỳ (mua/bán/liên ngân hàng) → số dư TK sai → BC01/BC02 sai
//   - Bỏ sót TK tiền tệ (138, 3388 có gốc ngoại tệ) → số dư không chính xác
//   - Nếu không đánh giá lại, BC02 chỉ tiêu 26 và chỉ tiêu 10 sai → ảnh hưởng TNDN
//   - Sai tỷ giá → lãi/lỗ ảo (unrealized) → kết quả KD sai → quyết định quản trị sai
class FxRevaluationService
{
    private \PDO $pdo;
    private AccountRepositoryInterface $accountRepo;
    private JournalServiceInterface $journal;

    // Danh sách tài khoản tiền tệ cần đánh giá lại
    private const MONETARY_ACCOUNTS = ['1112', '1122', '131', '331', '341', '311', '315', '338'];

    public function __construct(
        \PDO $pdo,
        AccountRepositoryInterface $accountRepo,
        JournalServiceInterface $journal
    ) {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
        $this->journal = $journal;
    }

    // Đánh giá lại tất cả tài khoản tiền tệ trong kỳ
    public function revaluate(int $periodId): array
    {
        $period = $this->getPeriod($periodId);
        $results = [];
        $adjustmentLines = [];
        $totalLoss = 0;
        $totalGain = 0;

        // Bước 2: Lấy tỷ giá cuối kỳ cho từng loại ngoại tệ
        // Lấy rate gần nhất <= end_date cho mỗi currency_code từ bảng exchange_rates
        // Edge case: Nếu không có tỷ giá cho ngày cuối kỳ → dùng tỷ giá ngày gần nhất
        // Edge case: Nếu không có tỷ giá nào → skip (cảnh báo người dùng nhập tỷ giá)
        //
        // TÍCH HỢP: Tỷ giá cuối kỳ thường được nhập thủ công hoặc từ NHNN/VCB API
        // RỦI RO: Nếu tỷ giá nhập sai (ví dụ nhập 23.000 thay vì 23.500) → chênh lệch
        // đánh giá lại sai toàn bộ → ảnh hưởng đến tất cả TK tiền tệ
        $endRates = $this->getPeriodEndRates($period['end_date']);
        if (empty($endRates)) {
            return ['status' => 'skipped', 'message' => 'Không tìm thấy tỷ giá cho ngày cuối kỳ', 'results' => []];
        }

        // Bước 3: Duyệt từng tài khoản tiền tệ, tính số dư ngoại tệ ròng (Dr - Cr) theo từng loại tiền
        // Query chỉ lấy giao dịch có currency != VND để tránh nhiễu
        // HAVING ABS(fc_balance) > 0.01: bỏ qua các loại tiền có số dư gần 0 (nhiễu)
        //
        // RỦI RO: Query này không phân biệt số dư Dr hay Cr — nếu TK 131 có số dư bên Có
        // (tạm ứng của KH), phép tính SUM sẽ sai. Cần xem xét dùng SIGN() để xác định
        // bản chất số dư trước khi đánh giá lại.
        foreach (self::MONETARY_ACCOUNTS as $accCode) {
            $account = $this->accountRepo->findByCode($accCode);
            if (!$account) continue;

            // Lấy số dư ngoại tệ chi tiết theo loại tiền
            $stmt = $this->pdo->prepare("
                SELECT le.currency AS fc_currency,
                       SUM(CASE WHEN le.is_debit = 1 THEN le.fc_amount ELSE -le.fc_amount END) AS fc_balance,
                       SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE -le.amount END) AS vnd_balance
                FROM ledger_entries le
                JOIN transactions t ON t.id = le.transaction_id
                WHERE le.account_id = ?
                  AND t.date <= ?
                  AND le.currency IS NOT NULL
                  AND le.currency != ''
                  AND le.currency != 'VND'
                GROUP BY le.currency
                HAVING ABS(fc_balance) > 0.01
            ");
            $stmt->execute([$account->getId(), $period['end_date']]);
            $fcRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($fcRows as $fcRow) {
                $fcCurrency = $fcRow['fc_currency'];
                $fcBalance = (float)$fcRow['fc_balance'];
                $vndBalance = (float)$fcRow['vnd_balance'];
                $endRate = $endRates[$fcCurrency] ?? null;
                if (!$endRate) continue;

                // Tính tỷ giá bình quân gia quyền của các giao dịch trong kỳ (chỉ để báo cáo)
                // Công thức: Tỷ giá BQGQ = Tổng VND / Tổng ngoại tệ
                // Giá trị đánh giá lại = Số dư ngoại tệ * Tỷ giá cuối kỳ
                // Chênh lệch = Giá trị đánh giá lại - Số dư VND hiện tại
                //
                // THẬN TRỌNG: Phép nhân $fcBalance * $endRate giả định $fcBalance đã được
                // định hướng đúng (dương = Dr, âm = Cr). Nếu $fcBalance âm (số dư Có),
                // giá trị đánh giá lại sẽ âm → chênh lệch ngược dấu → sai hướng hạch toán
                $avgRate = abs($fcBalance) > 0.01 ? $vndBalance / $fcBalance : 0;
                $revaluedVnd = round($fcBalance * $endRate);
                $unrealizedGainLoss = $revaluedVnd - $vndBalance;

                // Ngưỡng trọng yếu: bỏ qua chênh lệch < 100 VND
                // Lý do: sai số làm tròn và chênh lệch nhỏ không ảnh hưởng đáng kể đến BCTC
                // Edge case: Nếu materiality threshold quá lớn (VD 1.000.000), nhiều kỳ tích
                // lũy → sai số đáng kể. Ngưỡng này cần được Kế toán trưởng phê duyệt.
                if (abs($unrealizedGainLoss) < 100) continue;

                if ($unrealizedGainLoss > 0) {
                    // Lãi tỷ giá: Nợ TK tiền tệ / Có 515
                    $adjustmentLines[] = ['account_code' => $accCode, 'amount' => $unrealizedGainLoss, 'is_debit' => true];
                    $adjustmentLines[] = ['account_code' => '515', 'amount' => $unrealizedGainLoss, 'is_debit' => false];
                    $totalGain += $unrealizedGainLoss;
                } else {
                    $loss = abs($unrealizedGainLoss);
                    // Lỗ tỷ giá: Nợ 6352 (Chi phí tài chính - Chênh lệch tỷ giá) / Có TK tiền tệ
                    $adjustmentLines[] = ['account_code' => '6352', 'amount' => $loss, 'is_debit' => true];
                    $adjustmentLines[] = ['account_code' => $accCode, 'amount' => $loss, 'is_debit' => false];
                    $totalLoss += $loss;
                }

                $results[] = [
                    'account_code' => $accCode,
                    'fc_currency' => $fcCurrency,
                    'fc_balance' => $fcBalance,
                    'vnd_balance' => $vndBalance,
                    'avg_rate' => round($avgRate, 4),
                    'end_rate' => $endRate,
                    'revalued_vnd' => $revaluedVnd,
                    'unrealized_gain_loss' => $unrealizedGainLoss,
                ];
            }
        }

        // Bước 4: Tạo bút toán điều chỉnh tỷ giá tổng hợp
        // Tất cả chênh lệch của các TK tiền tệ được gộp vào MỘT bút toán duy nhất
        // nhằm giảm số lượng bút toán cuối kỳ, dễ kiểm soát và dễ audit.
        //
        // THẬN TRỌNG:
        //   - $allowControl = true: cho phép post trực tiếp vào TK tổng hợp (111, 112)
        //     vì đây là bút toán tổng hợp cuối kỳ, không thể đi qua TK con
        //   - Module = 'fx_revaluation': để posting rules kiểm tra và cho phép
        //   - Ngày chứng từ (date) = ngày cuối kỳ, không phải ngày chạy batch
        //
        // TRANSACTION: JournalService::postEntry tự quản lý beginTransaction/commit/rollback
        // RỦI RO: Nếu bút toán này fail sau khi đã tính toán xong, toàn bộ chênh lệch
        // tỷ giá kỳ này bị mất → phải chạy lại revaluate() từ đầu
        // RỦI RO: Nếu kỳ đã đóng (closed period) → JournalService sẽ throw RuntimeException
        // → không thể post. Phải mở lại kỳ hoặc tạo bút toán điều chỉnh hồi tố.
        if (count($adjustmentLines) > 0) {
            $this->journal->postEntry(
                'FX revaluation adjustment for period ' . $period['period_code'],
                'FX-REVAL-' . date('Ymd'),
                $adjustmentLines,
                'system',
                true,
                'fx_revaluation',
                $period['end_date']
            );
        }

        return [
            'status' => count($results) > 0 ? 'adjusted' : 'no_adjustment',
            'period_code' => $period['period_code'],
            'total_gain' => $totalGain,
            'total_loss' => $totalLoss,
            'net_fx_impact' => $totalGain - $totalLoss,
            'results' => $results,
        ];
    }

    // Báo cáo đánh giá lại chi tiết (READ-ONLY — không tạo bút toán)
    //
    // Mục đích: Cho phép Kế toán viên xem trước kết quả đánh giá lại trước khi thực hiện
    // Giống revaluate() nhưng không postEntry — dùng để kiểm tra, đối chiếu.
    //
    // Quy trình: Kế toán viên gọi getRevaluationReport → Kế toán trưởng kiểm tra
    // → Nếu OK, gọi revaluate() để tạo bút toán.
    //
    // Khác biệt so với revaluate(): báo cáo này bao gồm thêm txn_count để đánh giá
    // mức độ chi tiết của số dư ngoại tệ.
    public function getRevaluationReport(int $periodId): array
    {
        $period = $this->getPeriod($periodId);
        $endRates = $this->getPeriodEndRates($period['end_date']);
        $items = [];

        foreach (self::MONETARY_ACCOUNTS as $accCode) {
            $account = $this->accountRepo->findByCode($accCode);
            if (!$account) continue;

            $stmt = $this->pdo->prepare("
                SELECT le.currency AS fc_currency,
                       SUM(CASE WHEN le.is_debit = 1 THEN le.fc_amount ELSE -le.fc_amount END) AS fc_balance,
                       SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE -le.amount END) AS vnd_balance,
                       COUNT(*) AS txn_count
                FROM ledger_entries le
                JOIN transactions t ON t.id = le.transaction_id
                WHERE le.account_id = ?
                  AND t.date <= ?
                  AND le.currency IS NOT NULL AND le.currency != '' AND le.currency != 'VND'
                GROUP BY le.currency
                HAVING ABS(fc_balance) > 0.01
            ");
            $stmt->execute([$account->getId(), $period['end_date']]);
            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $fc = $r['fc_currency'];
                $fcBal = (float)$r['fc_balance'];
                $vndBal = (float)$r['vnd_balance'];
                $avgRate = abs($fcBal) > 0.01 ? $vndBal / $fcBal : 0;
                $endRate = $endRates[$fc] ?? 0;
                $reval = round($fcBal * $endRate);
                $diff = $reval - $vndBal;

                $items[] = [
                    'account_code' => $accCode,
                    'fc_currency' => $fc,
                    'fc_balance' => $fcBal,
                    'vnd_balance' => $vndBal,
                    'avg_rate' => round($avgRate, 4),
                    'end_rate' => $endRate,
                    'revalued_vnd' => $reval,
                    'unrealized_gain_loss' => $diff,
                    'txn_count' => (int)$r['txn_count'],
                ];
            }
        }

        return [
            'period_code' => $period['period_code'],
            'end_date' => $period['end_date'],
            'items' => $items,
        ];
    }

    private function getPeriod(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM accounting_periods WHERE id = ?');
        $stmt->execute([$id]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$r) throw new \InvalidArgumentException("Không tìm thấy kỳ kế toán: {$id}");
        return $r;
    }

    // Lấy tỷ giá cuối kỳ cho tất cả các loại tiền tệ
    //
    // Process: SELECT tất cả tỷ giá <= end_date, ORDER BY rate_date DESC
    // → Chỉ lấy rate đầu tiên (gần nhất) cho mỗi currency_code
    //
    // Edge case: Nếu không có tỷ giá ngày cuối kỳ (VD cuối tuần, ngày lễ),
    // dùng tỷ giá ngày gần nhất (cơ sở: tỷ giá không thay đổi đột ngột)
    //
    // Edge case: Nếu có nhiều tỷ giá trong cùng 1 ngày (VD sáng/chiều),
    // lấy tỷ giá cuối ngày (rate_date DESC). Cần đảm bảo exchange_rates
    // chỉ lưu 1 rate/ngày/currency để tránh nhập nhằng.
    //
    // RỦI RO: Nếu bảng exchange_rates không có dữ liệu cho currency nào đó
    // → $endRates[$fcCurrency] = null → continue (bỏ qua). Hậu quả: TK đó
    // không được đánh giá lại → số dư VND sai → BC01 sai.
    private function getPeriodEndRates(string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT currency_code, rate
            FROM exchange_rates
            WHERE rate_date <= ?
            ORDER BY rate_date DESC
        ");
        $stmt->execute([$endDate]);
        $rates = [];
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $code = $r['currency_code'];
            if (!isset($rates[$code])) {
                $rates[$code] = (float)$r['rate'];
            }
        }
        return $rates;
    }
}

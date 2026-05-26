<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;

//
// BÁO CÁO TÀI CHÍNH: Service sinh số liệu BC01 (Cân đối kế toán), BC02 (KQKD), BC03 (Lưu chuyển tiền tệ)
// Tuân thủ Thông tư 99/2025/TT-BTC về chỉ tiêu báo cáo tài chính doanh nghiệp
// Mỗi BC có danh sách chỉ tiêu với mã số, công thức tính, dấu hiệu — cấu hình qua bảng fs_line_items
// BC01 = số dư TK tại thời điểm cuối kỳ, BC02 = số phát sinh lũy kế trong kỳ, BC03 = chênh lệch số dư
// Rủi ro: Sai số liệu → ảnh hưởng BC01/02/03 → sai tờ khai thuế TNDN và báo cáo kiểm toán
//
class FsService
{
    private \PDO $pdo;
    private AccountRepositoryInterface $accountRepo;
    private ?AuditLoggerInterface $auditLogger;

    public function __construct(\PDO $pdo, AccountRepositoryInterface $accountRepo, ?AuditLoggerInterface $auditLogger = null)
    {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
        $this->auditLogger = $auditLogger;
    }

    //
    // Lấy danh mục chỉ tiết của một báo cáo tài chính (BC01/BC02/BC03)
    // Dữ liệu từ bảng fs_line_items — có thể thêm/sửa chỉ tiêu mà không cần sửa code backend
    //
    public function getLineItems(string $statement): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM fs_line_items WHERE statement = ? ORDER BY display_order');
        $stmt->execute([$statement]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    //
    // BC01 — Bảng Cân đối kế toán
    // Phản ánh toàn bộ tài sản (MS 100-280) và nguồn hình thành tài sản (MS 300-440) tại thời điểm cuối kỳ
    // Nguyên tắc nền tảng: TỔNG TÀI SẢN (MS 280) = NỢ PHẢI TRẢ (MS 330) + VỐN CHỦ SỞ HỮU (MS 440)
    // Số liệu = số dư các tài khoản tại ngày kết thúc kỳ kế toán, sign convention đảo dấu cho TK nguồn vốn
    //
    public function generateBC01(?string $periodCode = null): array
    {
        return $this->generateStatement('BC01', $periodCode);
    }

    //
    // BC02 — Báo cáo Kết quả hoạt động kinh doanh
    // Phản ánh doanh thu, chi phí và kết quả kinh doanh phát sinh trong kỳ
    // Cấu trúc: MS 01-20 (Doanh thu, giảm trừ), MS 21-29 (Chi phí), MS 30 (LN gộp),
    //   MS 40 (LN từ HĐTC+thu nhập khác), MS 50 (LN trước thuế), MS 60 (LN sau thuế)
    // Số liệu = số phát sinh lũy kế từ đầu kỳ đến ngày kết thúc kỳ (không phải số dư)
    //
    public function generateBC02(?string $periodCode = null): array
    {
        return $this->generateStatement('BC02', $periodCode);
    }

    //
    // BC03 — Báo cáo Lưu chuyển tiền tệ (phương pháp gián tiếp)
    // Phản ánh dòng tiền thuần từ 3 hoạt động: Kinh doanh (MS 01-30), Đầu tư (MS 31-50), Tài chính (MS 51-60)
    // Nguyên tắc: MS 70 (Tiền cuối kỳ) = MS 20 (Tiền đầu kỳ) + MS 30 + MS 50 + MS 60 (Lưu chuyển thuần)
    // Phương pháp gián tiếp: Bắt đầu từ LN trước thuế (BC02 MS 50) rồi điều chỉnh các khoản không phải tiền
    // Công thức cốt lõi: account_delta = chênh lệch số dư cuối kỳ - đầu kỳ của TK liên quan
    // Rủi ro: Nếu thiếu chỉ tiêu → BC03 không khớp với BC01 MS 110 (Tiền cuối kỳ)
    //
    public function generateBC03(?string $periodCode = null): array
    {
        $periodCode = $periodCode ?? date('Y');
        $prevPeriod = (string)((int)$periodCode - 1);

        $items = $this->getLineItems('BC03');

        // Get supporting data
        $bc02 = $this->generateBC02($periodCode);
        $bc02Values = [];
        foreach ($bc02 as $r) $bc02Values[$r['ma_so']] = $r['value'];

        $bc01 = $this->generateBC01($periodCode);
        $bc01Prev = $this->getPriorPeriodValues('BC01', $periodCode);

        $bc01Values = [];
        foreach ($bc01 as $r) $bc01Values[$r['ma_so']] = $r['value'];

        $values = [];

        foreach ($items as $item) {
            $maSo = $item['ma_so'];

            // Tính từng chỉ tiêu BC03 theo loại công thức:
            // - from_bc02 / from_bc02_24: lấy số liệu từ BC02 (lợi nhuận, khấu hao...)
            // - account_delta: chênh lệch số dư cuối kỳ - đầu kỳ (phương pháp gián tiếp)
            // - investment_adjust: điều chỉnh lãi/lỗ đầu tư từ BC02
            // - delta_neg / delta_pos: chênh lệch một chiều (dòng tiền giảm/tăng)
            // - delta_neg_only: chỉ lấy giá trị âm (VD: trả nợ vay)
            // - cash_begin: tiền đầu kỳ từ BC01 kỳ trước (MS 110)
            // - sum: tổng hợp các chỉ tiêu con
            // - calculated: biểu thức số học tùy chỉnh
            switch ($item['formula_type']) {
                case 'from_bc02':
                    $values[$maSo] = $bc02Values['50'] ?? 0;
                    break;

                case 'from_bc02_24':
                    $values[$maSo] = $bc02Values['24'] ?? 0;
                    break;

                //
                // account_delta: Chênh lệch số dư cuối kỳ - đầu kỳ của tài khoản (cốt lõi của PP gián tiếp)
                // VD: MS 01 = delta các TK 131, 331... — phải thu tăng → dòng tiền giảm
                // Số liệu đầu kỳ lấy từ BC01 snapshot kỳ trước
                //
                case 'account_delta':
                    $accounts = array_map('trim', explode(',', $item['formula_detail'] ?? ''));
                    $total = 0;
                    foreach ($accounts as $code) {
                        $a = $this->accountRepo->findByCode($code);
                        if ($a) {
                            $endBal = $a->getBalance();
                            $startBal = 0;
                            if ($bc01Prev && isset($bc01Prev[$code])) {
                                $startBal = $bc01Prev[$code];
                            }
                            $total += ($endBal - $startBal);
                        }
                    }
                    $values[$maSo] = round($total);
                    break;

                case 'investment_adjust':
                    $ms22 = $bc02Values['22'] ?? 0;
                    $ms23 = $bc02Values['23'] ?? 0;
                    $ms24 = $bc02Values['24'] ?? 0;
                    $ms21 = $bc02Values['21'] ?? 0;
                    $values[$maSo] = round(-($ms22 - ($ms23 - $ms24) + $ms21));
                    break;

                case 'delta_neg':
                    $accounts = array_map('trim', explode(',', $item['formula_detail'] ?? ''));
                    $total = 0;
                    foreach ($accounts as $code) {
                        $a = $this->accountRepo->findByCode($code);
                        if ($a) {
                            $endBal = $a->getBalance();
                            $startBal = 0;
                            if ($bc01Prev && isset($bc01Prev[$code])) {
                                $startBal = $bc01Prev[$code];
                            }
                            $total += ($endBal - $startBal);
                        }
                    }
                    $values[$maSo] = round(-$total);
                    break;

                case 'delta_pos':
                    $accounts = array_map('trim', explode(',', $item['formula_detail'] ?? ''));
                    $total = 0;
                    foreach ($accounts as $code) {
                        $a = $this->accountRepo->findByCode($code);
                        if ($a) {
                            $endBal = $a->getBalance();
                            $startBal = 0;
                            if ($bc01Prev && isset($bc01Prev[$code])) {
                                $startBal = $bc01Prev[$code];
                            }
                            $total += ($endBal - $startBal);
                        }
                    }
                    $values[$maSo] = round($total);
                    break;

                case 'delta_neg_only':
                    $accounts = array_map('trim', explode(',', $item['formula_detail'] ?? ''));
                    $total = 0;
                    foreach ($accounts as $code) {
                        $a = $this->accountRepo->findByCode($code);
                        if ($a) {
                            $endBal = $a->getBalance();
                            $startBal = 0;
                            if ($bc01Prev && isset($bc01Prev[$code])) {
                                $startBal = $bc01Prev[$code];
                            }
                            $total += ($endBal - $startBal);
                        }
                    }
                    $values[$maSo] = round(min(0, $total));
                    break;

                //
                // MS 20 — Tiền đầu kỳ: lấy số dư TK 110 (Tiền mặt + tiền gửi + tương đương tiền) từ BC01 kỳ trước
                // Nếu chưa có snapshot kỳ trước → mặc định = 0 (kỳ đầu tiên của hệ thống)
                // Ảnh hưởng: Sai số liệu đầu kỳ → sai toàn bộ BC03 → sai tiền cuối kỳ (MS 70)
                //
                case 'cash_begin':
                    if ($bc01Prev && isset($bc01Prev['110'])) {
                        $values[$maSo] = round($bc01Prev['110']);
                    } else {
                        $values[$maSo] = 0;
                    }
                    break;

                case 'sum':
                    $children = array_map('trim', explode(',', $item['formula_detail'] ?? ''));
                    $total = 0;
                    foreach ($children as $c) {
                        $total += $values[$c] ?? 0;
                    }
                    $values[$maSo] = round($total);
                    break;

                case 'calculated':
                    $expr = $item['formula_detail'] ?? '';
                    $result = $this->evaluateExpression($expr, $values);
                    $values[$maSo] = round($result);
                    break;

                case 'manual':
                default:
                    $values[$maSo] = 0;
                    break;
            }
        }

        // Build result rows
        $result = [];
        foreach ($items as $item) {
            $maSo = $item['ma_so'];
            $val = $values[$maSo] ?? 0;
            $result[] = [
                'ma_so' => $maSo,
                'parent_ma_so' => $item['parent_ma_so'],
                'name_vi' => $item['name_vi'],
                'value' => $val,
                'is_control' => (bool)$item['is_control'],
                'is_total' => (bool)$item['is_total'],
                'display_order' => (int)$item['display_order'],
            ];
        }

        // Save snapshot
        $data = json_encode($values);
        $this->pdo->prepare(
            'INSERT INTO fs_snapshots (statement, period_code, period_end_date, data, created_by)
             VALUES (?, ?, CURDATE(), ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), created_at = NOW()'
        )->execute(['BC03', $periodCode, $data, $_SESSION['user']['username'] ?? 'system']);

        $this->auditLogger?->log('fs.generate', 'fs_statement', "BC03_{$periodCode}",
            null, ['statement' => 'BC03', 'period' => $periodCode, 'items' => count($result)],
            $_SESSION['user']['username'] ?? 'system');

        return $result;
    }

    public function validateBC03(array $bc03Data): array
    {
        $values = [];
        foreach ($bc03Data as $r) $values[$r['ma_so']] = $r['value'];

        $errors = [];
        // 70 = 50+60+61
        $calc70 = ($values[50] ?? 0) + ($values[60] ?? 0) + ($values[61] ?? 0);
        if (abs(($values[70] ?? 0) - $calc70) > 1) {
            $errors[] = "Closing cash (70) should be {$calc70}, got {$values[70]}";
        }
        // BC 01 cross-check: MS70 should equal BC01 MS110 (cash + equivalents)
        // This validation happens in controller level since we need BC01 data
        return $errors;
    }

    private function generateStatement(string $statement, ?string $periodCode = null): array
    {
        $periodCode = $periodCode ?? date('Y');
        $items = $this->getLineItems($statement);
        $values = [];
        $signs = [];

        foreach ($items as $item) {
            $signs[$item['ma_so']] = $item['sign_convention'];
            $maSo = $item['ma_so'];

            switch ($item['formula_type']) {
                case 'account':
                    $accounts = array_map('trim', explode(',', $item['formula_detail'] ?? ''));
                    $total = 0;
                    foreach ($accounts as $code) {
                        $a = $this->accountRepo->findByCode($code);
                        if ($a) {
                            $bal = $a->getBalance();
                            if ($item['sign_convention'] === 'positive') $total += $bal;
                            else $total -= $bal;
                        }
                    }
                    $values[$maSo] = round($total);
                    break;

                case 'sum':
                    $children = array_map('trim', explode(',', $item['formula_detail'] ?? ''));
                    $total = 0;
                    foreach ($children as $c) {
                        $total += $values[$c] ?? 0;
                    }
                    $values[$maSo] = round($total);
                    break;

                case 'calculated':
                    $expr = $item['formula_detail'] ?? '';
                    $result = $this->evaluateExpression($expr, $values);
                    $values[$maSo] = round($result);
                    break;

                case 'manual':
                    $values[$maSo] = 0;
                    break;
            }
        }

        // Build result rows
        $result = [];
        foreach ($items as $item) {
            $maSo = $item['ma_so'];
            $val = $values[$maSo] ?? 0;
            $result[] = [
                'ma_so' => $maSo,
                'parent_ma_so' => $item['parent_ma_so'],
                'name_vi' => $item['name_vi'],
                'value' => $val,
                'is_control' => (bool)$item['is_control'],
                'is_total' => (bool)$item['is_total'],
                'display_order' => (int)$item['display_order'],
            ];
        }

        // LƯU TRỮ SNAPSHOT: Ghi lại số liệu BC tại thời điểm tạo — dùng để:
        //   - Đối chiếu với kỳ sau (BC03 cần số dư đầu kỳ từ BC01 kỳ trước)
        //   - Kiểm toán viên đối chiếu số liệu đã nộp với snapshot tại thời điểm đóng kỳ
        //   - Phục hồi khi có sự cố (nếu cần tính lại BC mà không ảnh hưởng dữ liệu gốc)
        // ON DUPLICATE KEY: Cho phép tạo lại BC nhiều lần, snapshot cuối cùng được giữ.
        // RỦI RO: Dữ liệu snapshot có thể sai nếu sinh BC trước khi kết chuyển cuối kỳ.
        // Biện pháp: PeriodService::canClose kiểm tra "FS snapshots generated" (check #7).
        $data = json_encode($values);
        $this->pdo->prepare(
            'INSERT INTO fs_snapshots (statement, period_code, period_end_date, data, created_by)
             VALUES (?, ?, CURDATE(), ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), created_at = NOW()'
        )->execute([$statement, $periodCode, $data, $_SESSION['user']['username'] ?? 'system']);

        $this->auditLogger?->log('fs.generate', 'fs_statement', "{$statement}_{$periodCode}",
            null, ['statement' => $statement, 'period' => $periodCode, 'items' => count($result)],
            $_SESSION['user']['username'] ?? 'system');

        return $result;
    }

    public function getPriorPeriodValues(string $statement, string $currentPeriodCode): ?array
    {
        // Determine prior period
        $year = (int)$currentPeriodCode;
        $priorYear = $year - 1;

        // HẠN CHẾ: Chỉ hỗ trợ kỳ năm (periodCode = year number). Với kỳ tháng/quý,
        // prior period không đơn giản là year-1.
        // RỦI RO: Nếu gọi với periodCode không phải năm (VD: '2026-Q1'), (int) cast
        // cho kết quả không xác định → sai số liệu đầu kỳ BC03.
        // TODO: Hỗ trợ quý/tháng: priorPeriod = getPreviousPeriod(currentPeriodCode).
        $stmt = $this->pdo->prepare('SELECT data FROM fs_snapshots WHERE statement = ? AND period_code = ?');
        $stmt->execute([$statement, (string)$priorYear]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;

        return json_decode($row['data'], true);
    }

    //
    // Kiểm tra cân đối BC01: TỔNG TÀI SẢN (MS 280) phải bằng NỢ PHẢI TRẢ + VCSH (MS 440)
    // Đây là nguyên tắc kế toán cơ bản nhất — nếu sai → toàn bộ sổ sách mất cân đối
    // Sai lệch cho phép ±1 (do làm tròn số). Sai > 1 → audit fail, phải kiểm tra lại tất cả bút toán
    //
    // TOLERANCE ±1: Sai số làm tròn từ việc tính toán số lẻ trên nhiều chỉ tiêu con.
    // Nếu tolerance = 0, các BC có thể không bao giờ cân đối được do rounding error
    // (VD: 1.000.000/3 = 333.333,33 → 3×333.333,33 = 999.999,99, chênh lệch 0,01đ).
    // Nếu lệch > 1đ, đó là lỗi ghi nhận → phải truy tìm bút toán sai.
    //
    public function validateBC01(array $bc01Data): array
    {
        $values = [];
        foreach ($bc01Data as $r) $values[$r['ma_so']] = $r['value'];

        $errors = [];
        if (abs($values[280] - $values[440]) > 1) {
            $errors[] = "Assets ({$values[280]}) != Liabilities + Equity ({$values[440]}). Diff: " . ($values[280] - $values[440]);
        }
        return $errors;
    }

    //
    // Kiểm tra cấu trúc BC02 theo Thông tư 99:
    // MS 50 (LN trước thuế) = MS 30 (LN gộp từ HĐKD) + MS 40 (LN từ HĐTC + thu nhập khác)
    // MS 60 (LN sau thuế) = MS 50 - MS 51 (Thuế TNDN hiện hành) - MS 52 (Thuế TNDN hoãn lại)
    // Sai lệch > 1 → rà soát lại số phát sinh các TK doanh thu, chi phí, thuế
    //
    public function validateBC02(array $bc02Data): array
    {
        $values = [];
        foreach ($bc02Data as $r) $values[$r['ma_so']] = $r['value'];

        $errors = [];
        // 50 = 30+40
        $calc50 = ($values[30] ?? 0) + ($values[40] ?? 0);
        if (abs(($values[50] ?? 0) - $calc50) > 1) {
            $errors[] = "Pre-tax profit (50) should be {$calc50}, got {$values[50]}";
        }
        // 60 = 50-(51+52)
        $calc60 = ($values[50] ?? 0) - (($values[51] ?? 0) + ($values[52] ?? 0));
        if (abs(($values[60] ?? 0) - $calc60) > 1) {
            $errors[] = "Net profit (60) should be {$calc60}, got {$values[60]}";
        }
        return $errors;
    }

    private function evaluateExpression(string $expr, array $values): float
    {
        $evalStr = $expr;
        foreach ($values as $k => $v) {
            $evalStr = preg_replace('/\b' . preg_quote((string)$k, '/') . '\b/', (string)$v, $evalStr);
        }
        return $this->safeEval($evalStr);
    }

    // AN TOÀN BIỂU THỨC: safeEval chỉ cho phép ký tự số, +, *, ., /, (, ), -, space.
    // Regex lọc bỏ mọi ký tự nguy hiểm (letter, $, #, @, `, v.v.) để chống injection.
    // RỦI RO: Nếu regex không cover được tất cả ký tự nguy hiểm (VD: %00 null byte,
    // Unicode normalization bypass), attacker có thể inject mã độc.
    // Biện pháp: Dữ liệu formula_detail đến từ DB (fs_line_items), chỉ admin mới sửa được.
    // Nếu formula_detail cho phép user nhập, cần tăng cường bảo mật (VD: không dùng eval).
    private function safeEval(string $expression): float
    {
        $expression = preg_replace('/[^0-9+*.\/( )-]/', '', $expression);
        $expression = trim($expression);
        if ($expression === '') return 0;
        if (preg_match('~^[*/]|[*/\-+]$|\(\)|\(\*|\(/|[*]{2}|[\\/]{2}|\.\d*\.~', $expression)) {
            return 0;
        }
        if (substr_count($expression, '(') !== substr_count($expression, ')')) {
            return 0;
        }
        $result = $this->parseExpression($expression);
        return round($result, 2);
    }

    private function parseExpression(string &$expr): float
    {
        $result = $this->parseTerm($expr);
        while (strlen($expr) > 0) {
            $op = $expr[0];
            if ($op !== '+' && $op !== '-') break;
            $expr = substr($expr, 1);
            $term = $this->parseTerm($expr);
            if ($op === '+') $result += $term;
            else $result -= $term;
        }
        return $result;
    }

    private function parseTerm(string &$expr): float
    {
        $result = $this->parseFactor($expr);
        while (strlen($expr) > 0) {
            $op = $expr[0];
            if ($op !== '*' && $op !== '/') break;
            $expr = substr($expr, 1);
            $factor = $this->parseFactor($expr);
            if ($op === '*') $result *= $factor;
            elseif ($factor != 0) $result /= $factor;
            else $result = 0;
        }
        return $result;
    }

    private function parseFactor(string &$expr): float
    {
        $expr = ltrim($expr);
        if (strlen($expr) === 0) return 0;
        if ($expr[0] === '(') {
            $expr = substr($expr, 1);
            $result = $this->parseExpression($expr);
            $expr = ltrim($expr);
            if (strlen($expr) > 0 && $expr[0] === ')') {
                $expr = substr($expr, 1);
            }
            return $result;
        }
        $numStr = '';
        while (strlen($expr) > 0 && (ctype_digit($expr[0]) || $expr[0] === '.')) {
            $numStr .= $expr[0];
            $expr = substr($expr, 1);
        }
        return $numStr === '' ? 0 : (float)$numStr;
    }

    public function getPeriods(): array
    {
        $rows = $this->pdo->query(
            "SELECT DISTINCT period_code, period_end_date FROM fs_snapshots WHERE statement = 'BC01' ORDER BY period_code DESC"
        )->fetchAll(\PDO::FETCH_ASSOC);
        return $rows;
    }
}

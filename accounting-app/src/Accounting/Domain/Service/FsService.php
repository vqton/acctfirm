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
    // Cấu trúc: MS 01-20 (Doanh thu/Giá vốn → LN gộp), MS 21 (Lãi/lỗ BĐS ĐT),
    //   MS 22-26 (Doanh thu TC, Chi phí TC, CP đi vay, CP BH, CP QLDN),
    //   MS 30 (LN thuần từ HĐKD), MS 31-40 (Thu nhập/Chi phí khác → LN khác),
    //   MS 50 (LN trước thuế), MS 51-52 (Thuế TNDN), MS 60 (LN sau thuế),
    //   MS 70-71 (EPS)
    // Số liệu = số phát sinh lũy kế từ đầu kỳ đến ngày kết thúc kỳ (không phải số dư)
    //
    public function generateBC02(?string $periodCode = null, array $manualValues = []): array
    {
        return $this->generateStatement('BC02', $periodCode, $manualValues);
    }

    //
    // BC03 — Báo cáo Lưu chuyển tiền tệ (phương pháp gián tiếp)
    // Phản ánh dòng tiền thuần từ 3 hoạt động: Kinh doanh (MS 01-30), Đầu tư (MS 31-50), Tài chính (MS 51-60)
    // Nguyên tắc: MS 70 (Tiền cuối kỳ) = MS 20 (Tiền đầu kỳ) + MS 30 + MS 50 + MS 60 (Lưu chuyển thuần)
    // Phương pháp gián tiếp: Bắt đầu từ LN trước thuế (BC02 MS 50) rồi điều chỉnh các khoản không phải tiền
    // Công thức cốt lõi: account_delta = chênh lệch số dư cuối kỳ - đầu kỳ của TK liên quan
    // Rủi ro: Nếu thiếu chỉ tiêu → BC03 không khớp với BC01 MS 110 (Tiền cuối kỳ)
    //
    public function generateBC03(?string $periodCode = null, array $manualValues = []): array
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
                    $values[$maSo] = $manualValues[$maSo] ?? 0;
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
                'is_manual' => $item['formula_type'] === 'manual',
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
            $errors[] = "Tiền cuối kỳ (70) phải là {$calc70}, hiện tại là {$values[70]}";
        }
        // BC 01 cross-check: MS70 should equal BC01 MS110 (cash + equivalents)
        // This validation happens in controller level since we need BC01 data

        return $errors;
    }

    //
    // BC03 — PHƯƠNG PHÁP TRỰC TIẾP (Direct Method)
    // Phân loại dòng tiền thu/chi từ tài khoản 111, 112 theo tài khoản đối ứng
    // Định lượng: mỗi khoản thu/chi được xác định bằng đối ứng của bút toán tiền
    //
    // Ràng buộc: Kết quả MS 70 (Tiền cuối kỳ) phải khớp với BC01 MS 110
    // và khớp với BC03 phương pháp gián tiếp MS 70
    //
    public function generateBC03Direct(?string $periodCode = null): array
    {
        $periodCode = $periodCode ?? date('Y');
        $year = (int)$periodCode;
        $fromDate = "{$year}-01-01";
        $toDate = "{$year}-12-31";

        $items = $this->getLineItems('BC03D');
        if (empty($items)) {
            throw new \RuntimeException('Chưa có chỉ tiêu BC03 phương pháp trực tiếp. Vui lòng chạy migration 080.');
        }

        // Lấy prior period đầu kỳ cho MS 60
        $prior = $this->getPriorPeriodValues('BC01', $periodCode);
        $cashBegin = $prior ? (float)($prior['110'] ?? 0) : 0;

        // Xây index cho line items theo loại
        $byType = []; // type => [ma_so => [accounts]]
        $sumItems = [];
        // Phân biệt receipt/payment maps vì 1 account code có thể vừa là thu vừa là chi
        $receiptClassified = []; // account_code => ma_so (khi netCash > 0)
        $paymentClassified = []; // account_code => ma_so (khi netCash < 0)
        $otherReceipt = null;
        $otherPayment = null;

        foreach ($items as $item) {
            $t = $item['formula_type'];
            if ($t === 'direct_receipt') {
                $codes = array_map('trim', explode(',', $item['formula_detail'] ?? ''));
                foreach ($codes as $c) {
                    if ($c !== '') $receiptClassified[$c] = $item['ma_so'];
                }
            } elseif ($t === 'direct_payment') {
                $codes = array_map('trim', explode(',', $item['formula_detail'] ?? ''));
                foreach ($codes as $c) {
                    if ($c !== '') $paymentClassified[$c] = $item['ma_so'];
                }
            } elseif ($t === 'direct_receipt_other') {
                $otherReceipt = $item['ma_so'];
            } elseif ($t === 'direct_payment_other') {
                $otherPayment = $item['ma_so'];
            } elseif ($t === 'sum') {
                $sumItems[$item['ma_so']] = array_map('trim', explode(',', $item['formula_detail'] ?? ''));
            }
        }

        // Query tất cả bút toán tiền (111/112) trong kỳ
        // Mỗi transaction có ít nhất 1 entry cash + 1 entry opponent
        $stmt = $this->pdo->prepare(
            "SELECT t.id, le.account_id, a.code as acct_code, a.name as acct_name,
                    le.is_debit, le.amount, t.description
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE t.status = 'posted'
               AND DATE(t.date) BETWEEN ? AND ?
               AND EXISTS (
                   SELECT 1 FROM ledger_entries le2
                   JOIN accounts a2 ON a2.id = le2.account_id
                   WHERE le2.transaction_id = t.id AND a2.code IN ('111','112')
               )
             ORDER BY t.id"
        );
        $stmt->execute([$fromDate, $toDate]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Nhóm theo transaction: tìm cash entry và opponent entry
        $byTxn = [];
        foreach ($rows as $r) {
            $byTxn[$r['id']][] = $r;
        }

        $amounts = []; // ma_so => total
        $unclassifiedReceipt = 0;
        $unclassifiedPayment = 0;

        foreach ($byTxn as $txnId => $entries) {
            $cashIn = 0;   // 111/112 debit → receipt
            $cashOut = 0;  // 111/112 credit → payment
            $opponents = []; // [acct_code => amount]
            $isCash = false;

            foreach ($entries as $e) {
                $isCashAcc = in_array($e['acct_code'], ['111','112','1111','1112','1113','1121','1122']);
                if ($isCashAcc) {
                    if ($e['is_debit']) $cashIn += $e['amount'];
                    else $cashOut += $e['amount'];
                    $isCash = true;
                } else {
                    $opponents[$e['acct_code']] = ($opponents[$e['acct_code']] ?? 0) + $e['amount'];
                }
            }

            if (!$isCash) continue;

            $netCash = $cashIn - $cashOut;
            if (abs($netCash) < 1) continue;

            // Xác định opponent chính (tài khoản có số tiền lớn nhất)
            $mainOpponent = null;
            $mainOppAmount = 0;
            foreach ($opponents as $code => $amt) {
                if ($amt > $mainOppAmount) {
                    $mainOppAmount = $amt;
                    $mainOpponent = $code;
                }
            }

            if ($mainOpponent === null) continue;

            if ($netCash > 0) {
                // Thu tiền: tìm trong receipt map
                $maSo = $receiptClassified[$mainOpponent] ?? null;
                if ($maSo) {
                    $amounts[$maSo] = ($amounts[$maSo] ?? 0) + $netCash;
                } else {
                    $unclassifiedReceipt += $netCash;
                }
            } else {
                // Chi tiền: tìm trong payment map
                $absCash = abs($netCash);
                $maSo = $paymentClassified[$mainOpponent] ?? null;
                if ($maSo) {
                    $amounts[$maSo] = ($amounts[$maSo] ?? 0) - $absCash;
                } else {
                    $unclassifiedPayment += $absCash;
                }
            }
        }

        if ($otherReceipt) $amounts[$otherReceipt] = $unclassifiedReceipt;
        if ($otherPayment) $amounts[$otherPayment] = -$unclassifiedPayment;

        // Tính items sum và cash_begin
        $values = $amounts;
        $values['60'] = $cashBegin;

        foreach ($sumItems as $maSo => $children) {
            $total = 0;
            foreach ($children as $c) {
                $total += $values[$c] ?? 0;
            }
            $values[$maSo] = round($total);
        }

        // Build result rows
        $result = [];
        foreach ($items as $item) {
            $maSo = $item['ma_so'];
            $result[] = [
                'ma_so' => $maSo,
                'parent_ma_so' => $item['parent_ma_so'],
                'name_vi' => $item['name_vi'],
                'value' => $values[$maSo] ?? 0,
                'is_control' => (bool)$item['is_control'],
                'is_total' => (bool)$item['is_total'],
                'is_manual' => false,
                'display_order' => (int)$item['display_order'],
            ];
        }

        // Save snapshot
        $data = json_encode($values);
        $this->pdo->prepare(
            'INSERT INTO fs_snapshots (statement, period_code, period_end_date, data, created_by)
             VALUES (?, ?, CURDATE(), ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), created_at = NOW()'
        )->execute(['BC03D', $periodCode, $data, $_SESSION['user']['username'] ?? 'system']);

        $this->auditLogger?->log('fs.generate', 'fs_statement', "BC03_DIRECT_{$periodCode}",
            null, ['statement' => 'BC03D', 'period' => $periodCode, 'items' => count($result)],
            $_SESSION['user']['username'] ?? 'system');

        return $result;
    }

    public function validateBC03Direct(array $bc03Data): array
    {
        $values = [];
        foreach ($bc03Data as $r) $values[$r['ma_so']] = $r['value'];
        $errors = [];
        $calc70 = ($values[50] ?? 0) + ($values[60] ?? 0);
        if (abs(($values[70] ?? 0) - $calc70) > 1) {
            $errors[] = "Tiền cuối kỳ (70) phải là {$calc70}, hiện tại là {$values[70]}";
        }
        return $errors;
    }

    private function generateStatement(string $statement, ?string $periodCode = null, array $manualValues = []): array
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

                case 'account_tree':
                    $codes = array_map('trim', explode(',', $item['formula_detail'] ?? ''));
                    $total = 0;
                    foreach ($codes as $code) {
                        $bal = $this->accountRepo->getTreeBalance($code);
                        if ($item['sign_convention'] === 'positive') $total += $bal;
                        else $total -= $bal;
                    }
                    $values[$maSo] = round($total);
                    break;

                case 'manual':
                    $values[$maSo] = (float)($manualValues[$maSo] ?? 0);
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

    //
    // Lưu giá trị nhập tay cho chỉ tiêu BC (VD: MS 21, 70, 71 của BC02)
    // Dùng business_config table với key = {statement}.manual.{periodCode}
    // Trả về mảng [ma_so => value] hoặc mảng rỗng nếu chưa có
    //
    public function getManualValues(string $statement, string $periodCode): array
    {
        $stmt = $this->pdo->prepare("SELECT config_value FROM business_config WHERE config_key = ?");
        $stmt->execute(["{$statement}.manual.{$periodCode}"]);
        $row = $stmt->fetchColumn();
        return $row ? json_decode($row, true) : [];
    }

    //
    // Lưu giá trị nhập tay cho chỉ tiêu BC
    // $values = [ma_so => value] (VD: ['21' => 500000, '70' => 2000])
    // Idempotent: INSERT ON DUPLICATE KEY UPDATE
    //
    public function saveManualValues(string $statement, string $periodCode, array $values, string $updatedBy): void
    {
        $this->pdo->prepare(
            "INSERT INTO business_config (config_key, config_value, config_type, description, is_active, updated_by)
             VALUES (?, ?, 'json', ?, 1, ?)
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_by = VALUES(updated_by), updated_at = NOW()"
        )->execute([
            "{$statement}.manual.{$periodCode}",
            json_encode($values),
            "Giá trị nhập tay {$statement} kỳ {$periodCode}",
            $updatedBy,
        ]);
    }

    public function getPriorPeriodValues(string $statement, string $currentPeriodCode): ?array
    {
        $priorCode = $this->_computePriorPeriodCode($currentPeriodCode);
        if (!$priorCode) return null;

        $stmt = $this->pdo->prepare('SELECT data FROM fs_snapshots WHERE statement = ? AND period_code = ?');
        $stmt->execute([$statement, $priorCode]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            $this->auditLogger?->log('fs.prior_period.missing', 'fs_snapshot',
                "{$statement}_{$priorCode}",
                null, ['statement' => $statement, 'period' => $currentPeriodCode, 'prior' => $priorCode],
                'system');
            return null;
        }
        return json_decode($row['data'], true);
    }

    // Xác định mã kỳ trước dựa trên định dạng period code:
    //   '2026'     → '2025'  (năm)
    //   '2026-05'  → '2026-04'  (tháng, lùi 1 tháng)
    //   '2026-Q1'  → '2025-Q4'  (quý, lùi 1 quý)
    private function _computePriorPeriodCode(string $code): ?string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $code, $m)) {
            $y = (int)$m[1]; $mo = (int)$m[2];
            if ($mo <= 1) { $y--; $mo = 12; }
            else { $mo--; }
            return sprintf('%04d-%02d', $y, $mo);
        }
        if (preg_match('/^(\d{4})-Q(\d)$/', $code, $m)) {
            $y = (int)$m[1]; $q = (int)$m[2];
            if ($q <= 1) { $y--; $q = 4; }
            else { $q--; }
            return "{$y}-Q{$q}";
        }
        // Mặc định: coi như năm
        if (preg_match('/^\d{4}$/', $code)) {
            return (string)((int)$code - 1);
        }
        return null;
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
            $errors[] = "Tổng tài sản ({$values[280]}) không bằng Nợ phải trả + VCSH ({$values[440]}). Chênh lệch: " . ($values[280] - $values[440]);
        }
        return $errors;
    }

    //
    // Kiểm tra cấu trúc BC02 theo Thông tư 99:
    // MS 30 (LN thuần từ HĐKD) = MS 20 (LN gộp) + MS 21 (Lãi/lỗ BĐS ĐT) + MS 22 (DT TC) - (MS 23 (CP TC) + MS 25 (CP BH) + MS 26 (CP QLDN))
    // MS 50 (LN trước thuế) = MS 30 (LN từ HĐKD) + MS 40 (LN khác)
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
            $errors[] = "Lợi nhuận trước thuế (50) phải là {$calc50}, hiện tại là {$values[50]}";
        }
        // 60 = 50-(51+52)
        $calc60 = ($values[50] ?? 0) - (($values[51] ?? 0) + ($values[52] ?? 0));
        if (abs(($values[60] ?? 0) - $calc60) > 1) {
            $errors[] = "Lợi nhuận sau thuế (60) phải là {$calc60}, hiện tại là {$values[60]}";
        }
        return $errors;
    }

    //
    // Kiểm tra các cảnh báo nghiệp vụ BC02:
    // BR18: Nếu có doanh thu (MS 01 > 0) nhưng giá vốn > doanh thu (MS 11 > MS 01) → cảnh báo lỗ gộp
    // BR19: Nếu MS 50 (LN trước thuế) < 0 → cảnh báo lỗ
    //
    public function getBC02Warnings(array $bc02Data): array
    {
        $values = [];
        foreach ($bc02Data as $r) $values[$r['ma_so']] = $r['value'];

        $warnings = [];
        // BR18: Gross loss — giá vốn > doanh thu
        $ms01 = $values['01'] ?? 0;
        $ms11 = $values['11'] ?? 0;
        if (abs($ms01) > 1 && $ms11 > $ms01) {
            $warnings[] = "CẢNH BÁO: Giá vốn hàng bán ({$ms11}) lớn hơn doanh thu thuần ({$ms01}). Doanh nghiệp đang lỗ gộp — cần rà soát chính sách giá và giá vốn.";
        }
        // BR19: Lỗ 2 năm (chỉ check year hiện tại)
        if (($values['50'] ?? 0) < -1) {
            $warnings[] = "Lưu ý: Lợi nhuận trước thuế âm ({$values[50]}). Doanh nghiệp đang lỗ, cần theo dõi khả năng hoạt động liên tục.";
        }
        return $warnings;
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

    //
    // BC09 — Thuyết minh Báo cáo tài chính
    // Tuân thủ Thông tư 99/2025/TT-BTC — Mẫu B09-DN
    // Cung cấp thông tin bổ sung cho BC01, BC02, BC03:
    //   - Chính sách kế toán áp dụng
    //   - Doanh thu theo tháng
    //   - Chi phí theo yếu tố
    //   - Tình hình tăng giảm TSCĐ
    //   - Giao dịch bên liên quan (placeholder)
    //   - Nợ tiềm tàng (placeholder)
    //
    public function tt99(array $params): array
    {
        $year = $params['year'] ?? date('Y');
        $period = $params['period'] ?? $year;
        $fromDate = $params['fromDate'] ?? $year . '-01-01';
        $toDate = $params['toDate'] ?? $year . '-12-31';

        // I. Chính sách kế toán
        $policy = [
            'accounting_policy' => 'Áp dụng chế độ kế toán theo Thông tư 99/2025/TT-BTC',
            'currency' => 'VNĐ',
            'fiscal_year' => $year,
            'inventory_method' => 'Bình quân gia quyền',
            'depreciation_method' => 'Đường thẳng',
            'vat_method' => 'Khấu trừ',
        ];

        // II. Doanh thu theo tháng (TK 511)
        $revenueBreakdown = [];
        for ($m = 1; $m <= 12; $m++) {
            $ms = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
            $monthStart = $year . '-' . $ms . '-01';
            $monthEnd = date('Y-m-d', strtotime('+1 month -1 day', strtotime($monthStart)));

            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(le.amount), 0)
                 FROM ledger_entries le
                 JOIN transactions t ON t.id = le.transaction_id
                 JOIN accounts a ON a.id = le.account_id
                 WHERE a.code = '511' AND t.status = 'posted'
                 AND le.is_debit = 0
                 AND t.transaction_date BETWEEN ? AND ?"
            );
            $stmt->execute([$monthStart, $monthEnd]);
            $revenueBreakdown[] = [
                'month' => $ms,
                'amount' => (float)$stmt->fetchColumn(),
            ];
        }

        // III. Chi phí theo yếu tố
        $expenseAccounts = [
            ['code' => '632', 'name' => 'Giá vốn hàng bán'],
            ['code' => '641', 'name' => 'Chi phí bán hàng'],
            ['code' => '642', 'name' => 'Chi phí quản lý doanh nghiệp'],
            ['code' => '635', 'name' => 'Chi phí tài chính'],
        ];
        $expenseByNature = [];
        foreach ($expenseAccounts as $acct) {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(le.amount), 0)
                 FROM ledger_entries le
                 JOIN transactions t ON t.id = le.transaction_id
                 JOIN accounts a ON a.id = le.account_id
                 WHERE a.code = ? AND t.status = 'posted'
                 AND le.is_debit = 1
                 AND t.transaction_date BETWEEN ? AND ?"
            );
            $stmt->execute([$acct['code'], $fromDate, $toDate]);
            $expenseByNature[] = [
                'name' => $acct['name'],
                'amount' => (float)$stmt->fetchColumn(),
            ];
        }

        // IV. Tình hình TSCĐ
        $assetMovements = [];
        try {
            $assetStmt = $this->pdo->query(
                "SELECT
                    COALESCE(SUM(original_cost), 0) as total_original_cost,
                    COALESCE(SUM(accumulated_depreciation), 0) as total_accumulated_depreciation
                 FROM fixed_assets"
            );
            $assetData = $assetStmt->fetch(\PDO::FETCH_ASSOC);
            if ($assetData) {
                $assetMovements[] = [
                    'name' => 'TSCĐ hữu hình',
                    'original_cost' => (float)$assetData['total_original_cost'],
                    'accumulated_depreciation' => (float)$assetData['total_accumulated_depreciation'],
                ];
            }
        } catch (\Exception $e) {
            // fixed_assets table may not exist yet
        }

        return [
            'accounting_policy' => $policy['accounting_policy'],
            'currency' => $policy['currency'],
            'fiscal_year' => $policy['fiscal_year'],
            'inventory_method' => $policy['inventory_method'],
            'depreciation_method' => $policy['depreciation_method'],
            'vat_method' => $policy['vat_method'],
            'revenue_breakdown' => $revenueBreakdown,
            'expense_by_nature' => $expenseByNature,
            'asset_movements' => $assetMovements,
            'related_party_transactions' => [],
            'contingencies' => [],
        ];
    }

    public function getPeriods(): array
    {
        $rows = $this->pdo->query(
            "SELECT DISTINCT period_code, period_end_date FROM fs_snapshots WHERE statement = 'BC01' ORDER BY period_code DESC"
        )->fetchAll(\PDO::FETCH_ASSOC);
        return $rows;
    }

    //
    // KIỂM TRA TÍNH HỢP LỆ CỦA LINE ITEMS: Rà soát tất cả công thức `account` và `account_tree`
    // để phát hiện tài khoản không tồn tại hoặc control account bị dùng sai loại công thức.
    // Đây là lớp phòng vệ chống lại lỗi class BC01-tax-line-items (control account balance = 0).
    //
    // Chiến lược phát hiện:
    // 1. Mã tài khoản không tồn tại → ERROR (sửa formula_detail)
    // 2. Control account dùng formula_type = 'account' → WARN (nên dùng account_tree để auto sum)
    // 3. Mã tài khoản không tồn tại có dấu hiệu là control account (viết hoa, 3 số) → WARN + gợi ý account_tree
    //
    public function validateLineItems(string $statement): array
    {
        $items = $this->getLineItems($statement);
        $errors = [];
        $warnings = [];

        foreach ($items as $item) {
            if (!in_array($item['formula_type'], ['account', 'account_tree'], true)) {
                continue;
            }
            $codes = array_map('trim', explode(',', $item['formula_detail'] ?? ''));
            foreach ($codes as $code) {
                if ($code === '') continue;
                $a = $this->accountRepo->findByCode($code);
                if (!$a) {
                    $errors[] = "Chỉ tiêu {$item['ma_so']} ({$item['name_vi']}): TK $code không tồn tại";
                } elseif ($a->isControl() && $item['formula_type'] === 'account') {
                    $warnings[] = "Chỉ tiêu {$item['ma_so']} ({$item['name_vi']}): TK $code là TK tổng hợp. Nên dùng formula_type = 'account_tree' để tự động tính tổng các TK con.";
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }
}

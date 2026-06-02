<?php
namespace Accounting\Domain\Service;

//
// CÔNG CỤ LẬP TỜ KHAI QUYẾT TOÁN THUẾ TNDN (03/TNDN)
//
// Tự động điền các chỉ tiêu trên mẫu 03/TNDN từ dữ liệu kế toán.
// Tuân thủ TT 78/2014, TT 96/2015, Luật TNDN 67/2025.
//
// Chỉ tiêu chính (01-25):
//   [01] Doanh thu bán hàng và cung cấp dịch vụ (TK 511)
//   [02] Các khoản giảm trừ doanh thu
//   [03] Doanh thu thuần ([01]-[02])
//   [04] Giá vốn hàng bán (TK 632)
//   [05] Lợi nhuận gộp ([03]-[04])
//   [06] Doanh thu hoạt động tài chính (TK 515)
//   [07] Chi phí tài chính (TK 635)
//   [08] Chi phí bán hàng (TK 641)
//   [09] Chi phí QLDN (TK 642)
//   [10] Lợi nhuận thuần từ HĐKD ([05]+[06]-[07]-[08]-[09])
//   [11] Thu nhập khác (TK 711)
//   [12] Chi phí khác (TK 811)
//   [13] Lợi nhuận khác ([11]-[12])
//   [14] Tổng lợi nhuận kế toán trước thuế ([10]+[13])
//   [15] Điều chỉnh tăng TNCT: chi phí không được trừ
//   [16] Điều chỉnh giảm TNCT: ưu đãi, miễn giảm
//   [17] Thu nhập chịu thuế ([14]+[15]-[16]) nếu >= 0
//   [18] Thu nhập miễn thuế
//   [19] Lỗ từ các kỳ trước chuyển sang
//   [20] Thu nhập tính thuế ([17]-[18]-[19])
//   [21] Thuế suất (%)
//   [22] Thuế TNDN phải nộp ([20]*[21]/100)
//   [23] Thuế TNDN tạm nộp trong kỳ
//   [24] Thuế TNDN còn phải nộp ([22]-[23])
//   [25] Chi phí thuế TNDN hoãn lại
//
class CitDeclarationEngine
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo) { $this->pdo = $pdo; }

    public function calculateIndicators(string $period): array
    {
        $periodStart = $period . '-01';
        $nextPeriod = date('Y-m', strtotime('+1 month', strtotime($periodStart)));
        $periodEnd = date('Y-m-d', strtotime('-1 day', strtotime($nextPeriod . '-01')));

        // [01] Doanh thu bán hàng (511)
        $r01 = $this->sumLedger('511', true, $periodStart, $periodEnd);

        // [02] Các khoản giảm trừ (521)
        $r02 = $this->sumLedger('521', true, $periodStart, $periodEnd);

        // [03] Doanh thu thuần
        $r03 = $r01 - $r02;

        // [04] Giá vốn hàng bán (632)
        $r04 = $this->sumLedger('632', true, $periodStart, $periodEnd);

        // [05] Lợi nhuận gộp
        $r05 = $r03 - $r04;

        // [06] Doanh thu tài chính (515)
        $r06 = $this->sumLedger('515', true, $periodStart, $periodEnd);

        // [07] Chi phí tài chính (635)
        $r07 = $this->sumLedger('635', true, $periodStart, $periodEnd);

        // [08] Chi phí bán hàng (641)
        $r08 = $this->sumLedger('641', true, $periodStart, $periodEnd);

        // [09] Chi phí QLDN (642)
        $r09 = $this->sumLedger('642', true, $periodStart, $periodEnd);

        // [10] Lợi nhuận thuần
        $r10 = $r05 + $r06 - $r07 - $r08 - $r09;

        // [11] Thu nhập khác (711)
        $r11 = $this->sumLedger('711', true, $periodStart, $periodEnd);

        // [12] Chi phí khác (811)
        $r12 = $this->sumLedger('811', true, $periodStart, $periodEnd);

        // [13] Lợi nhuận khác
        $r13 = $r11 - $r12;

        // [14] Tổng lợi nhuận kế toán trước thuế
        $r14 = $r10 + $r13;

        // [15] Điều chỉnh tăng: chi phí không được trừ + chênh lệch khấu hao
        $r15 = $this->calculateNonDeductibleAdjustments($periodStart, $periodEnd);

        // [16] Điều chỉnh giảm: ưu đãi đầu tư, miễn giảm
        $r16 = $this->calculateDeductionAdjustments($periodStart, $periodEnd);

        // [17] Thu nhập chịu thuế
        $r17 = max(0, $r14 + $r15 - $r16);

        // [18] Thu nhập miễn thuế
        $r18 = $this->sumTaxExemptIncome($periodStart, $periodEnd);

        // [19] Lỗ chuyển sang
        $r19 = $this->getLossCarryforward($period);

        // [20] Thu nhập tính thuế
        $r20 = max(0, $r17 - $r18 - $r19);

        // [21] Thuế suất
        $r21 = $this->getTaxRate($period);

        // [22] Thuế TNDN phải nộp
        $r22 = round($r20 * $r21 / 100, 0);

        // [23] Tạm nộp trong kỳ
        $r23 = $this->getQuarterlyPaid($period);

        // [24] Còn phải nộp
        $r24 = max(0, $r22 - $r23);

        // [25] Thuế hoãn lại
        $r25 = 0;

        return [
            '01' => $r01, '02' => $r02, '03' => $r03, '04' => $r04, '05' => $r05,
            '06' => $r06, '07' => $r07, '08' => $r08, '09' => $r09, '10' => $r10,
            '11' => $r11, '12' => $r12, '13' => $r13, '14' => $r14,
            '15' => $r15, '16' => $r16, '17' => $r17, '18' => $r18,
            '19' => $r19, '20' => $r20, '21' => $r21,
            '22' => $r22, '23' => $r23, '24' => $r24, '25' => $r25,
            'period' => $period, 'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function saveToDeclaration(string $id, array $indicators): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE cit_calculations SET indicators = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([json_encode($indicators, JSON_UNESCAPED_UNICODE), $id]);
    }

    public function exportToXml(string $id): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM cit_calculations WHERE id = ?"
        );
        $stmt->execute([$id]);
        $decl = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$decl) throw new \RuntimeException("Không tìm thấy quyết toán TNDN mã {$id}.");

        $ind = json_decode($decl['indicators'] ?? '{}', true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElementNS('', 'TKN');
        $root->setAttribute('xmlns', 'https://gdt.gov.vn/cit/declaration/03-tndn');
        $dom->appendChild($root);

        $add = fn(string $name, string $val) => $root->appendChild($dom->createElement($name, $val));

        $add('TBAN', '03/TNDN');
        $add('TChuc', htmlspecialchars($decl['organization'] ?? ''));
        $add('MST', htmlspecialchars($decl['tax_code'] ?? ''));
        $add('Ky', htmlspecialchars($decl['period'] ?? ''));
        $add('LanDau', $decl['is_first'] ?? '1');
        $add('DoanhThu', (string)($ind['01'] ?? 0));
        $add('GiamTruDT', (string)($ind['02'] ?? 0));
        $add('DTThuan', (string)($ind['03'] ?? 0));
        $add('GiaVon', (string)($ind['04'] ?? 0));
        $add('LoiNhuanGop', (string)($ind['05'] ?? 0));
        $add('DTTaiChinh', (string)($ind['06'] ?? 0));
        $add('CPTaiChinh', (string)($ind['07'] ?? 0));
        $add('CPBanHang', (string)($ind['08'] ?? 0));
        $add('CPQLDN', (string)($ind['09'] ?? 0));
        $add('LoiNhuanThuan', (string)($ind['10'] ?? 0));
        $add('TNKhac', (string)($ind['11'] ?? 0));
        $add('CPKhac', (string)($ind['12'] ?? 0));
        $add('LoiNhuanKhac', (string)($ind['13'] ?? 0));
        $add('TongLNKT', (string)($ind['14'] ?? 0));
        $add('DieuChinhTang', (string)($ind['15'] ?? 0));
        $add('DieuChinhGiam', (string)($ind['16'] ?? 0));
        $add('TNChiuThue', (string)($ind['17'] ?? 0));
        $add('TNMienThue', (string)($ind['18'] ?? 0));
        $add('LoChuyen', (string)($ind['19'] ?? 0));
        $add('TNTinhThue', (string)($ind['20'] ?? 0));
        $add('ThueSuat', (string)($ind['21'] ?? 20));
        $add('ThueTNDNPhaiNop', (string)($ind['22'] ?? 0));
        $add('TamNop', (string)($ind['23'] ?? 0));
        $add('ConPhaiNop', (string)($ind['24'] ?? 0));
        $add('ThueHoanLai', (string)($ind['25'] ?? 0));

        return $dom->saveXML();
    }

    private function sumLedger(string $accountCode, bool $isDebit, string $start, string $end): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(le.amount), 0)
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE a.code = ? AND t.status = 'posted'
             AND t.transaction_date BETWEEN ? AND ?
             AND le.is_debit = ?"
        );
        $stmt->execute([$accountCode, $start, $end, $isDebit ? 1 : 0]);
        return (float)$stmt->fetchColumn();
    }

    private function calculateNonDeductibleAdjustments(string $start, string $end): float
    {
        $adj = 0.0;

        // Chi phí quảng cáo > 10% doanh thu (TT 78/2014)
        $revenue = $this->sumLedger('511', true, $start, $end);
        $advExp = $this->sumLedger('6417', true, $start, $end); // 6417 = CP quảng cáo
        $cap = $revenue * 0.1;
        if ($advExp > $cap) { $adj += $advExp - $cap; }

        // Chi phí lãi vay > 30% EBITDA (TT 132/2020)
        $interest = $this->sumLedger('635', true, $start, $end);
        $ebitda = $this->sumLedger('511', true, $start, $end)
                - $this->sumLedger('632', true, $start, $end)
                - $this->sumLedger('641', true, $start, $end)
                - $this->sumLedger('642', true, $start, $end)
                + $interest
                + $this->getDepreciation($start, $end);
        $interestCap = $ebitda * 0.3;
        if ($interest > $interestCap) { $adj += $interest - $interestCap; }

        return $adj;
    }

    private function getDepreciation(string $start, string $end): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM depreciation_schedules
             WHERE schedule_date BETWEEN ? AND ? AND status = 'posted'"
        );
        $stmt->execute([$start, $end]);
        return (float)$stmt->fetchColumn();
    }

    private function calculateDeductionAdjustments(string $start, string $end): float
    {
        // Chênh lệch do ưu đãi đầu tư, KH nhanh,...
        // Hiện tại mặc định 0
        return 0.0;
    }

    private function sumTaxExemptIncome(string $start, string $end): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(le.amount), 0)
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE a.code = '7112' AND t.status = 'posted'
             AND t.transaction_date BETWEEN ? AND ?
             AND le.is_debit = 0"
        );
        $stmt->execute([$start, $end]);
        return (float)$stmt->fetchColumn();
    }

    private function getLossCarryforward(string $period): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(loss_amount - utilized_amount), 0)
             FROM loss_carryforwards WHERE expiry_period >= ?"
        );
        $stmt->execute([$period]);
        return (float)$stmt->fetchColumn();
    }

    private function getTaxRate(string $period): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(rate, 20) as rate
             FROM tax_rates WHERE tax_type = 'CIT' AND effective_from <= ?
             ORDER BY effective_from DESC LIMIT 1"
        );
        $stmt->execute([$period . '-01']);
        return (float)$stmt->fetchColumn();
    }

    private function getQuarterlyPaid(string $period): float
    {
        $year = substr($period, 0, 4);
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount_paid), 0)
             FROM cit_installments WHERE fiscal_year = ? AND period <= ?"
        );
        $stmt->execute([$year, $period]);
        return (float)$stmt->fetchColumn();
    }
}

<?php
namespace Accounting\Domain\Service;

//
// CÔNG CỤ TÍNH TOÁN 43 CHỈ TIÊU TỜ KHAI 01/GTGT
//
// Tự động điền 43 chỉ tiêu trên mẫu 01/GTGT từ dữ liệu kế toán.
// Tuân thủ TT 80/2021 (sửa bởi TT 40/2025) + NQ 204/2025 (giảm thuế 8%).
//
// QUY TRÌNH:
//   calculateIndicators(period) → array of 43 indicator values
//   saveToDeclaration(declarationId, indicators) → lưu vào DB
//   exportToXml(declarationId) → XML cho cổng TĐT
//
// NGUỒN DỮ LIỆU:
//   - ledger_entries: TK 1331 (đầu vào), TK 33311 (đầu ra), TK 511 (doanh thu)
//   - ap_invoices: VAT đầu vào
//   - ar_invoices: VAT đầu ra
//   - vat_declarations: kỳ trước (số dư chuyển sang)
//
// CHỈ TIÊU (43 indicators):
//   [21] Không phát sinh hoạt động SXKD
//   [22] Thuế GTGT còn được khấu trừ kỳ trước chuyển sang
//   [23] Giá trị HHDV mua vào (chưa VAT)
//   [24] Thuế GTGT đầu vào phát sinh
//   [23a] Giá trị hàng nhập khẩu
//   [24a] Thuế GTGT hàng nhập khẩu
//   [25] Tổng số thuế GTGT đầu vào được khấu trừ
//   [26] Doanh thu HHDV không chịu thuế GTGT
//   [29] Doanh thu HHDV chịu thuế suất 0%
//   [30] Doanh thu HHDV chịu thuế suất 5%
//   [31] Thuế GTGT đầu ra 5%
//   [32] Doanh thu HHDV chịu thuế suất 10%
//   [32a] Doanh thu HHDV không phải kê khai
//   [33] Thuế GTGT đầu ra 10%
//   [28a] Doanh thu HHDV chịu thuế suất 8% (NQ 204/2025)
//   [33a] Thuế GTGT đầu ra 8% (NQ 204/2025)
//   [37] Điều chỉnh tăng thuế GTGT đầu vào
//   [38] Điều chỉnh giảm thuế GTGT đầu vào
//   [39] Tổng số thuế GTGT được khấu trừ ([25]+[37]-[38])
//   [39a] Thuế GTGT còn được khấu trừ chuyển kỳ sau
//   [40] Tổng số thuế GTGT đầu ra
//   [40a] Điều chỉnh tăng thuế GTGT đầu ra
//   [40b] Tổng thuế GTGT khai bổ sung từ 02/GTGT
//   [41] Tổng số thuế GTGT phải nộp ([40]+[40a]+[40b])
//   [42] Thuế GTGT đề nghị hoàn
//   [43] Thuế GTGT còn được khấu trừ chuyển kỳ sau ([39]-[41]-[42]+[39a])
//
class VatDeclarationEngine
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    //
    // TÍNH TOÁN 43 CHỈ TIÊU CHO MỘT KỲ
    //
    // Input: period (Y-m)
    // Output: array [ind_21..ind_43] + metadata
    //
    public function calculateIndicators(string $period): array
    {
        $periodStart = $period . '-01';
        $nextPeriod = date('Y-m', strtotime('+1 month', strtotime($periodStart)));
        $periodEnd = date('Y-m-d', strtotime('-1 day', strtotime($nextPeriod . '-01')));

        //
        // PHẦN B — THUẾ GTGT ĐẦU VÀO
        //

        // [22] Kỳ trước chuyển sang
        $prevPeriod = date('Y-m', strtotime('-1 month', strtotime($periodStart)));
        $ind22 = $this->getCarryforwardFrom($prevPeriod);

        // [23] Giá trị HHDV mua vào (từ ledger_entries, TK 152, 153, 156, 611...)
        $ind23 = $this->sumLedgerInput($periodStart, $periodEnd, 'purchase_value');

        // [24] Thuế GTGT đầu vào phát sinh (TK 1331)
        $ind24 = $this->sumLedgerInput($periodStart, $periodEnd, 'vat_input');

        // [23a] Giá trị hàng nhập khẩu (TK 152 NK, hoặc import_value từ ap_invoices)
        $ind23a = $this->sumImportValue($periodStart, $periodEnd);

        // [24a] Thuế GTGT hàng nhập khẩu (TK 1332)
        $ind24a = $this->sumLedgerInput($periodStart, $periodEnd, 'vat_import');

        // [25] Tổng số thuế GTGT đầu vào được khấu trừ = [24] + [24a]
        $ind25 = $ind24 + $ind24a;

        //
        // PHẦN C — THUẾ GTGT ĐẦU RA
        //

        // [26] Doanh thu không chịu thuế (TK 511, ledger_entries không có VAT)
        $ind26 = $this->sumRevenueByVatRate($periodStart, $periodEnd, 0);

        // [29] Doanh thu 0%
        $ind29 = $this->sumRevenueByVatRate($periodStart, $periodEnd, 0, true);

        // [30] Doanh thu 5%
        $ind30 = $this->sumRevenueByVatRate($periodStart, $periodEnd, 5);
        // [31] Thuế GTGT 5%
        $ind31 = $this->sumOutputVatByRate($periodStart, $periodEnd, 5);

        // [32] Doanh thu 10%
        $ind32 = $this->sumRevenueByVatRate($periodStart, $periodEnd, 10);
        // [33] Thuế GTGT 10%
        $ind33 = $this->sumOutputVatByRate($periodStart, $periodEnd, 10);

        // [28a] Doanh thu 8% (NQ 204/2025)
        $ind28a = $this->sumRevenueByVatRate($periodStart, $periodEnd, 8);
        // [33a] Thuế GTGT 8% (NQ 204/2025)
        $ind33a = $this->sumOutputVatByRate($periodStart, $periodEnd, 8);

        // [32a] Doanh thu không phải kê khai
        $ind32a = $this->sumRevenueByVatRate($periodStart, $periodEnd, 0, false, true);

        //
        // PHẦN D — XÁC ĐỊNH NGHĨA VỤ THUẾ
        //

        // [37] Điều chỉnh tăng VAT đầu vào (bút toán điều chỉnh tăng 1331)
        $ind37 = $this->sumAdjustmentInput($periodStart, $periodEnd, 'increase');

        // [38] Điều chỉnh giảm VAT đầu vào
        $ind38 = $this->sumAdjustmentInput($periodStart, $periodEnd, 'decrease');

        // [39] Tổng số thuế GTGT được khấu trừ = [25] + [37] - [38]
        $ind39 = $ind25 + $ind37 - $ind38;

        // [39a] Thuế GTGT còn được khấu trừ chuyển đi (từ kỳ này)
        // Nếu [40] + [40a] + [40b] < [39] → chênh lệch là [39a]
        // (sẽ tính sau khi tính [40][40a][40b])

        // [40] Tổng số thuế GTGT đầu ra (auto)
        $ind40 = $ind31 + $ind33 + $ind33a;

        // [40a] Điều chỉnh tăng VAT đầu ra
        $ind40a = $this->sumAdjustmentOutput($periodStart, $periodEnd, 'increase');

        // [40b] Tổng thuế từ 02/GTGT
        $ind40b = $this->getFrom02Gtgt($period);

        // [41] Tổng số thuế GTGT phải nộp
        $ind41 = $ind40 + $ind40a + $ind40b;

        // [42] Thuế GTGT đề nghị hoàn (mặc định 0, kế toán nhập tay)
        $ind42 = 0;

        // [39a] Thuế GTGT còn được khấu trừ chuyển kỳ sau
        // Nếu [39] > [41] — dư khấu trừ
        $ind39a = max(0, $ind39 - $ind41);

        // [43] Thuế GTGT còn được khấu trừ chuyển kỳ sau
        // Tính lại = [39] + [39a] - [41] - [42]
        $ind43 = $ind39 + $ind39a - $ind41 - $ind42;

        // [21] Không phát sinh
        $ind21 = ($ind23 == 0 && $ind26 == 0 && $ind29 == 0 && $ind30 == 0
                  && $ind32 == 0 && $ind28a == 0) ? 1 : 0;

        return [
            'ind_21_no_activity' => $ind21,
            'ind_22_carryforward_from_prior' => $ind22,
            'ind_23_purchase_value' => $ind23,
            'ind_24_vat_input_incurred' => $ind24,
            'ind_23a_import_value' => $ind23a,
            'ind_24a_vat_import' => $ind24a,
            'ind_25_deductible_vat' => $ind25,

            'ind_26_non_taxable' => $ind26,
            'ind_29_zero_pct' => $ind29,
            'ind_30_five_pct_value' => $ind30,
            'ind_31_five_pct_vat' => $ind31,
            'ind_32_ten_pct_value' => $ind32,
            'ind_33_ten_pct_vat' => $ind33,
            'ind_28a_eight_pct_value' => $ind28a,
            'ind_33a_eight_pct_vat' => $ind33a,
            'ind_32a_no_declare' => $ind32a,

            'ind_37_adjust_input_increase' => $ind37,
            'ind_38_adjust_input_decrease' => $ind38,
            'ind_39_total_deductible' => $ind39,
            'ind_39a_transferred_out' => $ind39a,
            'ind_40_total_output_vat' => $ind40,
            'ind_40a_adjust_output_increase' => $ind40a,
            'ind_40b_total_from_02gtgt' => $ind40b,
            'ind_41_total_payable' => $ind41,
            'ind_42_refund_requested' => $ind42,
            'ind_43_carryforward_to_next' => $ind43,

            'has_reduction_appendix' => ($ind28a > 0 || $ind33a > 0) ? 1 : 0,

            // Metadata
            'period' => $period,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'calculated_at' => date('Y-m-d H:i:s'),
        ];
    }

    //
    // LƯU CHỈ TIÊU VÀO BẢNG vat_declarations
    //
    public function saveToDeclaration(string $declarationId, array $indicators): void
    {
        $sql = "UPDATE vat_declarations SET
            ind_21_no_activity = ?,
            ind_22_carryforward_from_prior = ?,
            ind_23_purchase_value = ?,
            ind_24_vat_input_incurred = ?,
            ind_23a_import_value = ?,
            ind_24a_vat_import = ?,
            ind_25_deductible_vat = ?,
            ind_26_non_taxable = ?,
            ind_29_zero_pct = ?,
            ind_30_five_pct_value = ?,
            ind_31_five_pct_vat = ?,
            ind_32_ten_pct_value = ?,
            ind_33_ten_pct_vat = ?,
            ind_28a_eight_pct_value = ?,
            ind_33a_eight_pct_vat = ?,
            ind_32a_no_declare = ?,
            ind_37_adjust_input_increase = ?,
            ind_38_adjust_input_decrease = ?,
            ind_39_total_deductible = ?,
            ind_39a_transferred_out = ?,
            ind_40_total_output_vat = ?,
            ind_40a_adjust_output_increase = ?,
            ind_40b_total_from_02gtgt = ?,
            ind_41_total_payable = ?,
            ind_42_refund_requested = ?,
            ind_43_carryforward_to_next = ?,
            has_reduction_appendix = ?
            WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $indicators['ind_21_no_activity'],
            $indicators['ind_22_carryforward_from_prior'],
            $indicators['ind_23_purchase_value'],
            $indicators['ind_24_vat_input_incurred'],
            $indicators['ind_23a_import_value'],
            $indicators['ind_24a_vat_import'],
            $indicators['ind_25_deductible_vat'],
            $indicators['ind_26_non_taxable'],
            $indicators['ind_29_zero_pct'],
            $indicators['ind_30_five_pct_value'],
            $indicators['ind_31_five_pct_vat'],
            $indicators['ind_32_ten_pct_value'],
            $indicators['ind_33_ten_pct_vat'],
            $indicators['ind_28a_eight_pct_value'],
            $indicators['ind_33a_eight_pct_vat'],
            $indicators['ind_32a_no_declare'],
            $indicators['ind_37_adjust_input_increase'],
            $indicators['ind_38_adjust_input_decrease'],
            $indicators['ind_39_total_deductible'],
            $indicators['ind_39a_transferred_out'],
            $indicators['ind_40_total_output_vat'],
            $indicators['ind_40a_adjust_output_increase'],
            $indicators['ind_40b_total_from_02gtgt'],
            $indicators['ind_41_total_payable'],
            $indicators['ind_42_refund_requested'],
            $indicators['ind_43_carryforward_to_next'],
            $indicators['has_reduction_appendix'],
            $declarationId,
        ]);
    }

    //
    // XUẤT XML TỜ KHAI THEO CHUẨN TĐT
    //
    public function exportToXml(string $declarationId): string
    {
        $stmt = $this->pdo->prepare("SELECT * FROM vat_declarations WHERE id = ?");
        $stmt->execute([$declarationId]);
        $decl = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$decl) throw new \RuntimeException("Không tìm thấy tờ khai: {$declarationId}");

        $period = $decl['period'];
        $year = substr($period, 0, 4);
        $month = substr($period, 5, 2);

        $ind = function($key) use ($decl) {
            return number_format((float)($decl[$key] ?? 0), 0, '.', '');
        };

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<TKhai xmlns="http://www.gdt.gov.vn/2025/01gtgt">
  <TTChung>
    <PBan>2.0.0</PBan>
    <LTKhai>01/GTGT</LTKhai>
    <Nam>{$year}</Nam>
    <Ky>{$month}</Ky>
    <LoaiKy>1</LoaiKy>
    <LanDau>1</LanDau>
  </TTChung>
  <NDKhai>
    <ChiTieu>
      <Ma>21</Ma>
      <GiaTri>{$decl['ind_21_no_activity']}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>22</Ma>
      <GiaTri>{$ind('ind_22_carryforward_from_prior')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>23</Ma>
      <GiaTri>{$ind('ind_23_purchase_value')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>24</Ma>
      <GiaTri>{$ind('ind_24_vat_input_incurred')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>25</Ma>
      <GiaTri>{$ind('ind_25_deductible_vat')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>26</Ma>
      <GiaTri>{$ind('ind_26_non_taxable')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>28a</Ma>
      <GiaTri>{$ind('ind_28a_eight_pct_value')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>29</Ma>
      <GiaTri>{$ind('ind_29_zero_pct')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>30</Ma>
      <GiaTri>{$ind('ind_30_five_pct_value')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>31</Ma>
      <GiaTri>{$ind('ind_31_five_pct_vat')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>32</Ma>
      <GiaTri>{$ind('ind_32_ten_pct_value')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>33</Ma>
      <GiaTri>{$ind('ind_33_ten_pct_vat')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>33a</Ma>
      <GiaTri>{$ind('ind_33a_eight_pct_vat')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>37</Ma>
      <GiaTri>{$ind('ind_37_adjust_input_increase')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>38</Ma>
      <GiaTri>{$ind('ind_38_adjust_input_decrease')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>39</Ma>
      <GiaTri>{$ind('ind_39_total_deductible')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>40</Ma>
      <GiaTri>{$ind('ind_40_total_output_vat')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>41</Ma>
      <GiaTri>{$ind('ind_41_total_payable')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>42</Ma>
      <GiaTri>{$ind('ind_42_refund_requested')}</GiaTri>
    </ChiTieu>
    <ChiTieu>
      <Ma>43</Ma>
      <GiaTri>{$ind('ind_43_carryforward_to_next')}</GiaTri>
    </ChiTieu>
  </NDKhai>
</TKhai>
XML;

        return $xml;
    }

    // === HÀM TRUY VẤN DỮ LIỆU ===

    // Lấy số dư khấu trừ kỳ trước (từ vat_declarations.ind_43)
    private function getCarryforwardFrom(string $period): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT ind_43_carryforward_to_next
             FROM vat_declarations
             WHERE period = ? AND status = 'finalised'
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$period]);
        return (float)$stmt->fetchColumn();
    }

    // Tổng từ ledger_entries theo account code
    private function sumLedgerByAccount(string $start, string $end, string $accountCode, bool $isDebit = true): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(le.amount), 0)
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE a.code LIKE ? AND t.status = 'posted'
             AND t.transaction_date BETWEEN ? AND ?
             AND le.is_debit = ?"
        );
        $stmt->execute([$accountCode . '%', $start, $end, $isDebit ? 1 : 0]);
        return (float)$stmt->fetchColumn();
    }

    // Tổng VAT input đầu vào theo loại
    private function sumLedgerInput(string $start, string $end, string $type): float
    {
        switch ($type) {
            case 'vat_input':
                // TK 1331 — VAT đầu vào được khấu trừ (Nợ)
                return $this->sumLedgerByAccount($start, $end, '1331', true);
            case 'vat_import':
                // TK 1332 — VAT hàng nhập khẩu (Nợ)
                return $this->sumLedgerByAccount($start, $end, '1332', true);
            case 'purchase_value':
                // Giá trị mua vào (TK 152, 153, 156, 611, 241...) — Nợ
                $stmt = $this->pdo->prepare(
                    "SELECT COALESCE(SUM(le.amount), 0)
                     FROM ledger_entries le
                     JOIN transactions t ON t.id = le.transaction_id
                     JOIN accounts a ON a.id = le.account_id
                     WHERE (a.code LIKE '152%' OR a.code LIKE '153%' OR a.code LIKE '156%'
                            OR a.code LIKE '155%' OR a.code LIKE '157%'
                            OR a.code LIKE '611%' OR a.code LIKE '241%'
                            OR a.code LIKE '211%')
                     AND t.status = 'posted'
                     AND t.transaction_date BETWEEN ? AND ?
                     AND le.is_debit = 1"
                );
                $stmt->execute([$start, $end]);
                return (float)$stmt->fetchColumn();
            default:
                return 0;
        }
    }

    // Tổng giá trị nhập khẩu
    private function sumImportValue(string $start, string $end): float
    {
        // TK 152 NK qua account code hoặc import tags
        // Fallback: TK 1332 * 10 (giả sử thuế NK = 10%)
        $vatImport = $this->sumLedgerByAccount($start, $end, '1332', true);
        return $vatImport * 10; // approximate
    }

    // Tổng doanh thu theo thuế suất
    private function sumRevenueByVatRate(string $start, string $end, int $vatRate, bool $isZeroRated = false, bool $noDeclare = false): float
    {
        // Sử dụng ledger_entries: TK 511 (Credit)
        // Kết hợp với ar_invoices để lấy vat_rate
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(le.amount), 0)
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             LEFT JOIN ar_invoices ari ON ari.transaction_id = t.id
             WHERE a.code LIKE '511%' AND t.status = 'posted'
             AND t.transaction_date BETWEEN ? AND ?
             AND le.is_debit = 0"
        );
        $stmt->execute([$start, $end]);
        $totalRevenue = (float)$stmt->fetchColumn();

        if ($totalRevenue <= 0) return 0;

        // Phân bổ doanh thu theo tỷ lệ VAT rate từ ar_invoices
        $stmt2 = $this->pdo->prepare(
            "SELECT COALESCE(SUM(ari.net_amount), 0) as revenue, ari.vat_rate
             FROM ar_invoices ari
             JOIN transactions t ON t.id = ari.transaction_id
             WHERE t.status = 'posted'
             AND t.transaction_date BETWEEN ? AND ?
             GROUP BY ari.vat_rate"
        );
        $stmt2->execute([$start, $end]);
        $revenueByRate = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

        $totalAr = array_sum(array_column($revenueByRate, 'revenue'));
        if ($totalAr <= 0) return 0;

        foreach ($revenueByRate as $row) {
            $rate = (int)$row['vat_rate'];
            if ($isZeroRated && $rate === 0) {
                // Chỉ lấy zero-rated (xuất khẩu)
                return (float)$row['revenue'];
            }
            if (!$isZeroRated && !$noDeclare && $rate === $vatRate && $vatRate > 0) {
                return (float)$row['revenue'];
            }
            if ($noDeclare && $rate === 999) {
                // 999 = không phải kê khai (mã đặc biệt)
                return (float)$row['revenue'];
            }
        }

        // Fallback: phân bổ đều
        $rates = array_filter($revenueByRate, fn($r) => (int)$r['vat_rate'] === $vatRate);
        if (!empty($rates)) {
            return (float)reset($rates)['revenue'];
        }

        return 0;
    }

    // Tổng VAT đầu ra theo thuế suất (TK 33311)
    private function sumOutputVatByRate(string $start, string $end, int $vatRate): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(le.amount), 0)
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             LEFT JOIN ar_invoices ari ON ari.transaction_id = t.id
             WHERE a.code LIKE '33311%' AND t.status = 'posted'
             AND t.transaction_date BETWEEN ? AND ?
             AND le.is_debit = 0
             AND (ari.vat_rate = ? OR ari.vat_rate IS NULL)"
        );
        $stmt->execute([$start, $end, $vatRate]);
        return (float)$stmt->fetchColumn();
    }

    // Điều chỉnh VAT đầu vào (bút toán correction)
    private function sumAdjustmentInput(string $start, string $end, string $direction): float
    {
        // Bút toán điều chỉnh: is_correction = 1, liên quan 1331
        $sign = ($direction === 'increase') ? 1 : -1;
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(le.amount * ?), 0)
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE a.code LIKE '1331%' AND t.status = 'posted'
             AND t.is_correction = 1
             AND t.transaction_date BETWEEN ? AND ?
             AND le.is_debit = 1"
        );
        $stmt->execute([$sign, $start, $end]);
        return max(0, (float)$stmt->fetchColumn());
    }

    // Điều chỉnh VAT đầu ra
    private function sumAdjustmentOutput(string $start, string $end, string $direction): float
    {
        $sign = ($direction === 'increase') ? 1 : -1;
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(le.amount * ?), 0)
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE a.code LIKE '33311%' AND t.status = 'posted'
             AND t.is_correction = 1
             AND t.transaction_date BETWEEN ? AND ?
             AND le.is_debit = 0"
        );
        $stmt->execute([$sign, $start, $end]);
        return max(0, (float)$stmt->fetchColumn());
    }

    // Thuế từ khai bổ sung 02/GTGT
    private function getFrom02Gtgt(string $period): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(total_tax), 0)
             FROM vat_declarations_supplemental
             WHERE period = ? AND status = 'submitted'"
        );
        $stmt->execute([$period]);
        return (float)$stmt->fetchColumn();
    }
}

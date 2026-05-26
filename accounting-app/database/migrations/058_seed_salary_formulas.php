<?php
// NGHIEP VU: Seed cac cong thuc tinh luong mac dinh
//
// Cac cong thuc:
//   1. GROSS_TO_NET: Tinh luong thuc lanh = gross - BHXH - BHYT - BHTN - thue TNCN
//   2. PIT_2026: Thue TNCN theo luy tien tung phan (5 bac) — Luat 109/2025/QH15
//   3. INSURANCE_CEILING: Tran dong BHXH/BHYT/BHTN
//   4. OVERTIME_NORMAL: Tang ca ngay thuong (150%)
//   5. OVERTIME_WEEKEND: Tang ca ngay nghi (200%)
//   6. OVERTIME_HOLIDAY: Tang ca ngay le (300%)
//   7. NIGHT_OVERTIME: Tang ca ban dem (130%)
//
// Tham so tinh thue 2026:
//   - Giam tru ban than: 15.500.000 VND/thang
//   - Giam tru nguoi phu thuoc: 6.200.000 VND/thang/NPT
//   - Bac 1: den 20tr (5%), Bac 2: 20-40tr (10%), Bac 3: 40-70tr (15%),
//   - Bac 4: 70-100tr (20%), Bac 5: tren 100tr (25%)
return function (PDO $pdo) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM salary_formulas');
    $stmt->execute();
    if ((int)$stmt->fetchColumn() > 0) { echo "[SKIP] salary_formulas already seeded\n"; return; }

    $pdo->exec("INSERT IGNORE INTO salary_formulas (id, code, name, type, description, formula_expression) VALUES
        ('f_gross_to_net', 'GROSS_TO_NET', 'Tinh luong thuc lanh', 'gross_to_net',
         'Luong net = Gross - BHXH NLĐ (8%) - BHYT NLĐ (1.5%) - BHTN NLĐ (1%) - Thue TNCN',
         'gross - insurance_ee - tax'),
        ('f_pit_2026', 'PIT_2026', 'Thue TNCN luy tien tung phan 2026', 'tax',
         '5 bac: 0-20tr(5%), 20-40tr(10%), 40-70tr(15%), 70-100tr(20%), >100tr(25%). Giam tru ban than 15.5tr, NPT 6.2tr',
         'pit_2026'),
        ('f_insurance_ceiling', 'INSURANCE_CEILING', 'Tran dong BHXH/BHYT/BHTN', 'insurance',
         'BHXH: 46.800.000 (20 thang luong toi thieu). BHYT: 32.760.000 (14 luong toi thieu). BHTN: theo vung',
         'min(insurance_salary, ceiling)'),
        ('f_overtime_normal', 'OVERTIME_NORMAL', 'Tang ca ngay thuong 150%', 'overtime',
         'Luong tang ca ngay thuong = luong gio * 150% * so gio',
         'base_hourly * 1.5 * hours'),
        ('f_overtime_weekend', 'OVERTIME_WEEKEND', 'Tang ca ngay nghi 200%', 'overtime',
         'Luong tang ca ngay nghi = luong gio * 200% * so gio',
         'base_hourly * 2.0 * hours'),
        ('f_overtime_holiday', 'OVERTIME_HOLIDAY', 'Tang ca ngay le 300%', 'overtime',
         'Luong tang ca ngay le = luong gio * 300% * so gio',
         'base_hourly * 3.0 * hours'),
        ('f_night_overtime', 'NIGHT_OVERTIME', 'Tang ca ban dem 130%', 'overtime',
         'Luong tang ca ban dem = luong gio * 130% * so gio',
         'base_hourly * 1.3 * hours')");
    echo "[OK] salary_formulas seeded\n";
};

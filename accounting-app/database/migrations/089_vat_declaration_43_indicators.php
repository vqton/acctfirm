<?php
return function (PDO $pdo) {
    // Thêm 43 chỉ tiêu tờ khai 01/GTGT vào bảng vat_declarations
    // Tuân thủ TT 80/2021 (sửa bởi TT 40/2025)
    // Cập nhật NQ 204/2025 (giảm thuế 8% đến hết 2026)

    // Phần A — Thông tin chung
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS activity_type VARCHAR(50) DEFAULT 'SXKD'");
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS tax_period_type VARCHAR(10) DEFAULT 'month'");
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS subsidiary_tax_code VARCHAR(20) DEFAULT NULL");
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ward_code VARCHAR(50) DEFAULT NULL");

    // Phần B — Thuế GTGT đầu vào (Input VAT)
    // [21] Không phát sinh (boolean)
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_21_no_activity TINYINT(1) NOT NULL DEFAULT 0");
    // [22] Kỳ trước chuyển sang
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_22_carryforward_from_prior DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [23] Giá trị HHDV mua vào
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_23_purchase_value DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [24] Thuế GTGT đầu vào phát sinh
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_24_vat_input_incurred DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [23a] Giá trị hàng NK
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_23a_import_value DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [24a] Thuế GTGT hàng NK
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_24a_vat_import DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [25] Thuế GTGT đầu vào được khấu trừ
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_25_deductible_vat DECIMAL(15,2) NOT NULL DEFAULT 0");

    // Phần C — Thuế GTGT đầu ra (Output VAT)
    // [26] KCT (Không chịu thuế)
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_26_non_taxable DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [29] 0%
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_29_zero_pct DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [30] 5% — giá trị
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_30_five_pct_value DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [31] 5% — thuế (auto)
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_31_five_pct_vat DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [32] 10%
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_32_ten_pct_value DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [32a] KKK (Không kê khai)
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_32a_no_declare DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [33] Thuế GTGT 10%
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_33_ten_pct_vat DECIMAL(15,2) NOT NULL DEFAULT 0");

    // [28a] 8% — giá trị (NQ 204/2025)
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_28a_eight_pct_value DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [33a] 8% — thuế (auto)
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_33a_eight_pct_vat DECIMAL(15,2) NOT NULL DEFAULT 0");

    // Phần D — Xác định nghĩa vụ thuế
    // [37] Điều chỉnh tăng thuế đầu vào
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_37_adjust_input_increase DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [38] Điều chỉnh giảm thuế đầu vào
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_38_adjust_input_decrease DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [39] Tổng số thuế GTGT được khấu trừ (auto = [25] + [37] - [38])
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_39_total_deductible DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [39a] Thuế GTGT còn được khấu trừ chuyển đi
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_39a_transferred_out DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [40] Tổng số thuế GTGT đầu ra (auto)
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_40_total_output_vat DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [40a] Điều chỉnh tăng thuế đầu ra
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_40a_adjust_output_increase DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [40b] Tổng thuế khai từ 02/GTGT
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_40b_total_from_02gtgt DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [41] Tổng số thuế GTGT phải nộp (auto = [40] + [40a] - [40b])
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_41_total_payable DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [42] Thuế GTGT đề nghị hoàn
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_42_refund_requested DECIMAL(15,2) NOT NULL DEFAULT 0");
    // [43] Kỳ sau chuyển sang (auto = [39] + [39a] - [41] - [42])
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS ind_43_carryforward_to_next DECIMAL(15,2) NOT NULL DEFAULT 0");

    // PL 204/2025 — Giảm thuế GTGT
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS has_reduction_appendix TINYINT(1) NOT NULL DEFAULT 0");
};

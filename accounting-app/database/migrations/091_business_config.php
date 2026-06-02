<?php
// Bảng cấu hình nghiệp vụ — tập trung toàn bộ tham số rule engine
// Tuân thủ OCP: Thay đổi rule = UPDATE row, không sửa code
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS business_config (
        config_key VARCHAR(96) NOT NULL PRIMARY KEY,
        config_value TEXT NOT NULL,
        config_type VARCHAR(20) NOT NULL DEFAULT 'decimal' COMMENT 'decimal|int|string|json|percent',
        description TEXT,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(255) DEFAULT 'system'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed tất cả hardcoded values hiện tại — migration không thay đổi behavior
    $pdo->exec("INSERT IGNORE INTO business_config (config_key, config_value, config_type, description) VALUES
        -- === THUẾ GTGT (VAT) ===
        ('vat.default_rate', '10', 'percent', 'Thuế suất GTGT mặc định (Luật VAT 48/2024)'),
        ('vat.reduction_rate', '8', 'percent', 'Thuế suất GTGT giảm (NQ 204/2025, hiệu lực đến 31/12/2026)'),
        ('vat.export_rate', '0', 'percent', 'Thuế suất hàng xuất khẩu (0%)'),
        ('vat.non_deductible_threshold', '5000000', 'int', 'Ngưỡng thanh toán không dùng tiền mặt >=5tr (TT 69/2025)'),

        -- === THUẾ TNDN (CIT) ===
        ('cit.default_rate', '20', 'percent', 'Thuế suất TNDN mặc định (20%, TT 78/2021)'),
        ('cit.advertising_cap', '10', 'percent', '% doanh thu tối đa chi phí quảng cáo (TT 78/2014)'),
        ('cit.interest_ebitda_cap', '30', 'percent', '% EBITDA tối đa chi phí lãi vay (TT 132/2020, BEPS 2.0)'),

        -- === THUẾ TNCN (PIT) ===
        ('pit.resident_deduction_monthly', '11000000', 'int', 'Giảm trừ gia cảnh bản thân/tháng (Luật TNCN 109/2025)'),
        ('pit.resident_deduction_annual', '132000000', 'int', 'Giảm trừ gia cảnh bản thân/năm'),
        ('pit.dependent_deduction_monthly', '4400000', 'int', 'Giảm trừ người phụ thuộc/tháng'),
        ('pit.dependent_deduction_annual', '52800000', 'int', 'Giảm trừ người phụ thuộc/năm'),
        ('pit.non_resident_rate', '20', 'percent', 'Thuế suất TNCN người không cư trú (20%)'),
        ('pit.resident_brackets', '[{\"bound\":5000000,\"rate\":0.05,\"baseTax\":0},{\"bound\":10000000,\"rate\":0.10,\"baseTax\":250000},{\"bound\":18000000,\"rate\":0.15,\"baseTax\":750000},{\"bound\":32000000,\"rate\":0.20,\"baseTax\":1950000},{\"bound\":52000000,\"rate\":0.25,\"baseTax\":4750000},{\"bound\":80000000,\"rate\":0.30,\"baseTax\":9750000},{\"bound\":9999999999,\"rate\":0.35,\"baseTax\":18150000}]', 'json', 'Biểu thuế lũy tiến 5-35%'),

        -- === BẢO HIỂM (Insurance) ===
        ('insurance.bhxh_ee', '8', 'percent', 'BHXH người lao động (8%, Luật BHXH 2024)'),
        ('insurance.bhyt_ee', '1.5', 'percent', 'BHYT người lao động (1.5%)'),
        ('insurance.bhtn_ee', '1', 'percent', 'BHTN người lao động (1%)'),
        ('insurance.bhxh_er', '17.5', 'percent', 'BHXH người sử dụng lao động (17.5%)'),
        ('insurance.bhyt_er', '3', 'percent', 'BHYT người sử dụng lao động (3%)'),
        ('insurance.bhtn_er', '1', 'percent', 'BHTN người sử dụng lao động (1%)'),
        ('insurance.salary_ceiling', '99360000', 'int', 'Trần lương đóng BHXH/BHYT/BHTN (99.36tr, NQ 595)'),

        -- === CÔNG NỢ (Debt Collection) ===
        ('debt.write_off_days', '365', 'int', 'Số ngày quá hạn tối thiểu đề xuất xóa nợ'),
        ('debt.min_activities_180d', '3', 'int', 'Số hoạt động đòi nợ tối thiểu trong 180 ngày'),
        ('debt.max_holds', '3', 'int', 'Số lần tạm dừng tối đa'),
        ('debt.settlement_discount_max', '70', 'percent', '% giảm tối đa khi thỏa thuận thanh toán'),
        ('debt.settlement_due_days', '14', 'int', 'Số ngày tối đa cho thỏa thuận thanh toán'),
        ('debt.promise_min_pct', '10', 'percent', '% dư nợ tối thiểu cho cam kết thanh toán'),
        ('debt.promise_max_days', '60', 'int', 'Số ngày tối đa cho cam kết thanh toán'),
        ('debt.promise_max_active', '3', 'int', 'Số cam kết đang hoạt động tối đa'),
        ('debt.escalation_promise_breaks', '3', 'int', 'Số lần thất hứa để tự động leo thang'),

        -- === KỲ KẾ TOÁN (Period) ===
        ('period.max_reopen', '3', 'int', 'Số lần tối đa mở lại kỳ kế toán đã đóng'),

        -- === TÀI KHOẢN MẶC ĐỊNH (mapping sẵn cho các controller) ===
        ('account.default_expense', '642', 'string', 'TK chi phí QLDN mặc định'),
        ('account.default_payable', '334', 'string', 'TK phải trả NLĐ mặc định'),
        ('account.default_cogs', '632', 'string', 'TK giá vốn hàng bán mặc định'),
        ('account.default_ap', '331', 'string', 'TK phải trả NCC mặc định'),
        ('account.default_ar', '131', 'string', 'TK phải thu KH mặc định'),
        ('account.default_cash', '111', 'string', 'TK tiền mặt mặc định'),
        ('account.default_bank', '112', 'string', 'TK tiền gửi NH mặc định'),
        ('account.default_revenue', '511', 'string', 'TK doanh thu mặc định'),
        ('account.default_inventory', '152', 'string', 'TK hàng tồn kho mặc định'),
        ('account.default_fa', '211', 'string', 'TK TSCĐ hữu hình mặc định'),
        ('account.default_other_income', '711', 'string', 'TK thu nhập khác mặc định'),
        ('account.default_other_expense', '811', 'string', 'TK chi phí khác mặc định'),
        ('account.default_finance_income', '515', 'string', 'TK doanh thu tài chính mặc định'),
        ('account.default_finance_expense', '635', 'string', 'TK chi phí tài chính mặc định'),
        ('account.default_selling_expense', '641', 'string', 'TK chi phí bán hàng mặc định'),
        ('account.default_tax_payable', '333', 'string', 'TK thuế phải nộp mặc định'),
        ('account.default_retained_earnings', '421', 'string', 'TK lợi nhuận chưa phân phối mặc định')"
    );
};

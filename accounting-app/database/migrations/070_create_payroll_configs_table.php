<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS payroll_configs (
        config_key VARCHAR(50) PRIMARY KEY,
        config_value VARCHAR(255) NOT NULL,
        description VARCHAR(255),
        category VARCHAR(50) NOT NULL DEFAULT "general",
        effective_from DATE DEFAULT NULL,
        effective_to DATE DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $stmt = $pdo->query('SELECT COUNT(*) FROM payroll_configs');
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("INSERT IGNORE INTO payroll_configs (config_key, config_value, description, category) VALUES
            ('bhxh_rate_ee', '0.08', 'BHXH nguoi lao dong (8%)', 'insurance'),
            ('bhyt_rate_ee', '0.015', 'BHYT nguoi lao dong (1.5%)', 'insurance'),
            ('bhtn_rate_ee', '0.01', 'BHTN nguoi lao dong (1%)', 'insurance'),
            ('bhxh_rate_er', '0.175', 'BHXH doanh nghiep (17.5%)', 'insurance'),
            ('bhyt_rate_er', '0.03', 'BHYT doanh nghiep (3%)', 'insurance'),
            ('bhtn_rate_er', '0.01', 'BHTN doanh nghiep (1%)', 'insurance'),
            ('kpcd_rate_er', '0.02', 'Kinh phi cong doan (2%)', 'insurance'),
            ('bhxh_ceiling_months', '20', 'So thang lam co so tran BHXH', 'insurance'),
            ('base_salary', '2340000', 'Luong co so (2.340.000)', 'wage'),
            ('region_min_wage_i', '4960000', 'Luong toi thieu vung I', 'wage'),
            ('region_min_wage_ii', '4410000', 'Luong toi thieu vung II', 'wage'),
            ('region_min_wage_iii', '3860000', 'Luong toi thieu vung III', 'wage'),
            ('region_min_wage_iv', '3450000', 'Luong toi thieu vung IV', 'wage'),
            ('tax_personal_deduction', '15500000', 'Giam tru ban than (15.500.000)', 'tax'),
            ('tax_dependent_deduction', '6200000', 'Giam tru nguoi phu thuoc (6.200.000)', 'tax'),
            ('default_working_days', '26', 'So ngay cong chuan trong thang', 'attendance'),
            ('ot_rate_weekday', '1.5', 'He so tang ca ngay thuong (150%)', 'overtime'),
            ('ot_rate_weekend', '2.0', 'He so tang ca cuoi tuan (200%)', 'overtime'),
            ('ot_rate_holiday', '3.0', 'He so tang ca ngay le (300%)', 'overtime'),
            ('ot_rate_night', '1.3', 'He so tang ca ban dem (130%)', 'overtime')");
    }
};

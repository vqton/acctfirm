<?php
// NGHIEP VU: Module Tien luong — quan ly bang luong, tinh luong, BHXH, thue TNCN
//
// Cau truc bang:
//   1. salary_components: Danh muc cac khoan luong (luong co ban, phu cap, BH, thue)
//   2. salary_formulas: Cong thuc tinh luong (tong hop, BH, thue)
//   3. payroll_periods: Ky luong theo thang
//   4. payroll_entries: Bang luong tong hop theo ky
//   5. payroll_details: Chi tiet bang luong tung nhan vien
//   6. payroll_detail_lines: Chi tiet tung khoan trong bang luong cua nhan vien
//
// Moi quan he:
//   payroll_periods 1->N payroll_entries 1->N payroll_details 1->N payroll_detail_lines
//   payroll_details N->1 employees
//   payroll_detail_lines N->1 salary_components
//
// Anh huong bao cao tai chinh:
//   - But toan luong: No 641/642/622/627 / Co 334
//   - But toan BHXH: No 334 / Co 3383
//   - But toan BHYT: No 334 / Co 3384
//   - But toan BHTN: No 334 / Co 3386
//   - But toan thue TNCN: No 334 / Co 3335
//   - Chi phi luong (tong gross): hach toan vao chi phi theo phong ban
return function (PDO $pdo) {
    // 1. Danh muc khoan luong
    $pdo->exec('CREATE TABLE IF NOT EXISTS salary_components (
        id VARCHAR(36) PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        type ENUM("earning", "allowance", "deduction", "insurance_ee", "insurance_er", "tax", "overtime") NOT NULL DEFAULT "earning",
        calculation_type ENUM("fixed", "percent_gross", "percent_basic", "formula") NOT NULL DEFAULT "fixed",
        value DECIMAL(15,2) NOT NULL DEFAULT 0,
        account_code_debit VARCHAR(20) DEFAULT NULL,
        account_code_credit VARCHAR(20) DEFAULT NULL,
        priority INT NOT NULL DEFAULT 0,
        is_mandatory TINYINT(1) NOT NULL DEFAULT 0,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // 2. Cong thuc tinh luong
    $pdo->exec('CREATE TABLE IF NOT EXISTS salary_formulas (
        id VARCHAR(36) PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        type ENUM("gross_to_net", "insurance", "tax", "overtime") NOT NULL DEFAULT "gross_to_net",
        description TEXT,
        formula_expression TEXT NOT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // 3. Ky luong
    $pdo->exec('CREATE TABLE IF NOT EXISTS payroll_periods (
        id VARCHAR(36) PRIMARY KEY,
        period_code VARCHAR(20) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status ENUM("open", "processing", "closed") NOT NULL DEFAULT "open",
        created_by VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // 4. Bang luong tong hop
    $pdo->exec('CREATE TABLE IF NOT EXISTS payroll_entries (
        id VARCHAR(36) PRIMARY KEY,
        period_id VARCHAR(36) NOT NULL,
        status ENUM("draft", "approved", "posted") NOT NULL DEFAULT "draft",
        total_employees INT NOT NULL DEFAULT 0,
        total_gross DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_allowances DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_deductions DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_insurance_ee DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_insurance_er DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_tax DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_net DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
        posted_at TIMESTAMP NULL DEFAULT NULL,
        created_by VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // 5. Chi tiet bang luong theo nhan vien
    $pdo->exec('CREATE TABLE IF NOT EXISTS payroll_details (
        id VARCHAR(36) PRIMARY KEY,
        payroll_entry_id VARCHAR(36) NOT NULL,
        employee_id VARCHAR(36) NOT NULL,
        gross_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_allowances DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_deductions DECIMAL(15,2) NOT NULL DEFAULT 0,
        insurance_ee DECIMAL(15,2) NOT NULL DEFAULT 0,
        insurance_er DECIMAL(15,2) NOT NULL DEFAULT 0,
        tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        net_pay DECIMAL(15,2) NOT NULL DEFAULT 0,
        overtime_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
        working_days DECIMAL(5,2) NOT NULL DEFAULT 0,
        status ENUM("active", "inactive") NOT NULL DEFAULT "active",
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (payroll_entry_id) REFERENCES payroll_entries(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // 6. Chi tiet tung khoan trong bang luong nhan vien
    $pdo->exec('CREATE TABLE IF NOT EXISTS payroll_detail_lines (
        id VARCHAR(36) PRIMARY KEY,
        payroll_detail_id VARCHAR(36) NOT NULL,
        salary_component_id VARCHAR(36) NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        account_code VARCHAR(20) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (payroll_detail_id) REFERENCES payroll_details(id) ON DELETE CASCADE,
        FOREIGN KEY (salary_component_id) REFERENCES salary_components(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Seed du lieu mac dinh cho cac khoan luong
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM salary_components');
    $stmt->execute();
    $count = (int)$stmt->fetchColumn();

    if ($count === 0) {
        $pdo->exec("INSERT IGNORE INTO salary_components (id, code, name, type, calculation_type, value, account_code_debit, account_code_credit, priority, is_mandatory) VALUES
            ('sc_basic', 'BASIC_SALARY', 'Luong co ban', 'earning', 'fixed', 0, '642', '334', 1, 1)");
        $pdo->exec("INSERT IGNORE INTO salary_components (id, code, name, type, calculation_type, value, account_code_debit, account_code_credit, priority, is_mandatory) VALUES
            ('sc_position', 'POSITION_ALLOWANCE', 'Phu cap chuc vu', 'allowance', 'fixed', 0, '642', '334', 10, 0)");
        $pdo->exec("INSERT IGNORE INTO salary_components (id, code, name, type, calculation_type, value, account_code_debit, account_code_credit, priority, is_mandatory) VALUES
            ('sc_meal', 'MEAL_ALLOWANCE', 'Phu cap an trua', 'allowance', 'fixed', 0, '642', '334', 11, 0)");
        $pdo->exec("INSERT IGNORE INTO salary_components (id, code, name, type, calculation_type, value, account_code_debit, account_code_credit, priority, is_mandatory) VALUES
            ('sc_phone', 'PHONE_ALLOWANCE', 'Phu cap dien thoai', 'allowance', 'fixed', 0, '642', '334', 12, 0)");
        $pdo->exec("INSERT IGNORE INTO salary_components (id, code, name, type, calculation_type, value, account_code_debit, account_code_credit, priority, is_mandatory) VALUES
            ('sc_bhxh_ee', 'BHXH_EE', 'BHXH nguoi lao dong (8%)', 'insurance_ee', 'percent_gross', 8, '334', '3383', 50, 1)");
        $pdo->exec("INSERT IGNORE INTO salary_components (id, code, name, type, calculation_type, value, account_code_debit, account_code_credit, priority, is_mandatory) VALUES
            ('sc_bhyt_ee', 'BHYT_EE', 'BHYT nguoi lao dong (1.5%)', 'insurance_ee', 'percent_gross', 1.5, '334', '3384', 51, 1)");
        $pdo->exec("INSERT IGNORE INTO salary_components (id, code, name, type, calculation_type, value, account_code_debit, account_code_credit, priority, is_mandatory) VALUES
            ('sc_bhtn_ee', 'BHTN_EE', 'BHTN nguoi lao dong (1%)', 'insurance_ee', 'percent_gross', 1, '334', '3386', 52, 1)");
        $pdo->exec("INSERT IGNORE INTO salary_components (id, code, name, type, calculation_type, value, account_code_debit, account_code_credit, priority, is_mandatory) VALUES
            ('sc_bhxh_er', 'BHXH_ER', 'BHXH doanh nghiep (17.5%)', 'insurance_er', 'percent_gross', 17.5, '642', '3383', 60, 1)");
        $pdo->exec("INSERT IGNORE INTO salary_components (id, code, name, type, calculation_type, value, account_code_debit, account_code_credit, priority, is_mandatory) VALUES
            ('sc_bhyt_er', 'BHYT_ER', 'BHYT doanh nghiep (3%)', 'insurance_er', 'percent_gross', 3, '642', '3384', 61, 1)");
        $pdo->exec("INSERT IGNORE INTO salary_components (id, code, name, type, calculation_type, value, account_code_debit, account_code_credit, priority, is_mandatory) VALUES
            ('sc_bhtn_er', 'BHTN_ER', 'BHTN doanh nghiep (1%)', 'insurance_er', 'percent_gross', 1, '642', '3386', 62, 1)");
        $pdo->exec("INSERT IGNORE INTO salary_components (id, code, name, type, calculation_type, value, account_code_debit, account_code_credit, priority, is_mandatory) VALUES
            ('sc_tax', 'PIT', 'Thue TNCN', 'tax', 'formula', 0, '334', '3335', 70, 1)");
        $pdo->exec("INSERT IGNORE INTO salary_components (id, code, name, type, calculation_type, value, account_code_debit, account_code_credit, priority, is_mandatory) VALUES
            ('sc_advance', 'ADVANCE_DEDUCTION', 'Tam ung tru luong', 'deduction', 'fixed', 0, '334', '141', 80, 0)");
    }
};

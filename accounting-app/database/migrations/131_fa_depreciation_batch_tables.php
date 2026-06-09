<?php
// NGHIỆP VỤ: Bảng phụ trợ cho Mẫu 06-TSCĐ — Bảng tính và phân bổ khấu hao TSCĐ
//
// 1. fa_department_accounts: Mapping phòng ban → TK chi phí khấu hao
//    - Giúp phân bổ KH vào đúng TK (627 SXC, 641 BH, 642 QL, 241 XDCB...)
//    - Thay thế hardcode '627' trong resolveDepreciationAccount()
//
// 2. fa_depreciation_batches: Monthly batch header
//    - Lưu tổng KH tháng trước (carry-forward cho dòng I)
//    - Lưu tổng KH tăng/giảm (dòng II, III)
//    - Snapshot cho carry-forward tháng sau
//
return function (PDO $pdo) {
    // 1. Mapping phòng ban → tài khoản chi phí khấu hao
    $pdo->exec("CREATE TABLE IF NOT EXISTS fa_department_accounts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        department_id VARCHAR(50) NOT NULL,
        debit_account VARCHAR(10) NOT NULL COMMENT 'TK Nợ phân bổ KH (627, 641, 642, 241...)',
        accum_account VARCHAR(10) NOT NULL DEFAULT '2141' COMMENT 'TK Có hao mòn (2141, 2142, 2143)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_dept (department_id),
        INDEX idx_dept (department_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Mapping phòng ban → TK khấu hao'");

    // 2. Monthly batch header — carry-forward cho Mẫu 06-TSCĐ
    $pdo->exec("CREATE TABLE IF NOT EXISTS fa_depreciation_batches (
        id VARCHAR(50) PRIMARY KEY,
        period VARCHAR(7) NOT NULL COMMENT 'Kỳ tính KH (YYYY-MM)',
        status VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft/posted/adjusted',
        total_company DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Tổng KH toàn DN',
        total_627 DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Phân bổ TK 627',
        total_641 DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Phân bổ TK 641',
        total_642 DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Phân bổ TK 642',
        total_623 DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Phân bổ TK 623',
        total_241 DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Phân bổ TK 241',
        total_242 DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Phân bổ TK 242',
        total_335 DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Phân bổ TK 335',
        total_other DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Phân bổ TK khác',
        prev_month_total DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Tổng KH tháng trước (dòng I)',
        increase_amount DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'KH tăng trong tháng (dòng II)',
        decrease_amount DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'KH giảm trong tháng (dòng III)',
        asset_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Số TSCĐ được tính KH',
        created_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_period (period),
        INDEX idx_period (period),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Batch header khấu hao tháng — Mẫu 06-TSCĐ'");

    // Seed department mappings nếu departments đã có
    $count = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    if ($count > 0) {
        $pdo->exec("INSERT IGNORE INTO fa_department_accounts (department_id, debit_account)
            SELECT id, '642' FROM departments WHERE code = 'PBKT'");
        $pdo->exec("INSERT IGNORE INTO fa_department_accounts (department_id, debit_account)
            SELECT id, '641' FROM departments WHERE code = 'PBHC'");
    }
};

<?php
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS opening_balances (
        id VARCHAR(50) PRIMARY KEY,
        account_code VARCHAR(20) NOT NULL COMMENT 'Mã tài khoản',
        period VARCHAR(7) NOT NULL COMMENT 'Kỳ mở sổ (YYYY-MM)',
        debit_balance DECIMAL(15,2) DEFAULT 0 COMMENT 'Số dư Nợ',
        credit_balance DECIMAL(15,2) DEFAULT 0 COMMENT 'Số dư Có',
        is_verified TINYINT(1) DEFAULT 0 COMMENT 'Đã đối chiếu',
        verified_by VARCHAR(100) DEFAULT NULL,
        verified_at TIMESTAMP NULL DEFAULT NULL,
        created_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_ob_account_period (account_code, period),
        INDEX idx_ob_period (period)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};

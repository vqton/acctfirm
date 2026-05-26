<?php
// Bảng quy tắc hạch toán — kiểm tra Dr/Cr hợp lệ cho từng module
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS posting_rules (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        debit_account_code VARCHAR(50) NOT NULL,
        credit_account_code VARCHAR(50) NOT NULL,
        module VARCHAR(50) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        severity ENUM("block","warn") DEFAULT "warn",
        max_amount DECIMAL(15,2) DEFAULT NULL,
        created_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_pair (debit_account_code, credit_account_code, module)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

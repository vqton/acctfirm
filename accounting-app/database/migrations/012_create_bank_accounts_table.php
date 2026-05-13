<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS bank_accounts (
        id VARCHAR(50) PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, bank_name VARCHAR(200) NOT NULL,
        account_number VARCHAR(50) NOT NULL, account_holder VARCHAR(200) NOT NULL,
        branch VARCHAR(200), currency VARCHAR(10) DEFAULT "VND", opening_balance DECIMAL(15,2) DEFAULT 0,
        status TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_code (code), INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

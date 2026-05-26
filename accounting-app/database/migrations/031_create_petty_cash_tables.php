<?php
// Bảng quỹ tiền mặt tạm ứng — quản lý tiền mặt tại bộ phận
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS petty_cash_funds (
        id VARCHAR(50) PRIMARY KEY,
        fund_name VARCHAR(100) NOT NULL,
        imprest_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        current_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
        status VARCHAR(20) DEFAULT "active",
        created_by VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS petty_cash_transactions (
        id VARCHAR(50) PRIMARY KEY,
        fund_id VARCHAR(50) NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        type VARCHAR(30) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        reference VARCHAR(100) DEFAULT NULL,
        expense_account VARCHAR(10) DEFAULT NULL,
        created_by VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (fund_id) REFERENCES petty_cash_funds(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

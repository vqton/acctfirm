<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS fc_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(50) NOT NULL,
        account_code VARCHAR(10) NOT NULL,
        currency_code VARCHAR(3) NOT NULL,
        fc_amount DECIMAL(15,2) NOT NULL,
        exchange_rate DECIMAL(15,4) NOT NULL,
        vnd_amount DECIMAL(15,2) NOT NULL,
        type VARCHAR(20) NOT NULL DEFAULT "receipt",
        description VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_account (account_code),
        INDEX idx_currency (currency_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

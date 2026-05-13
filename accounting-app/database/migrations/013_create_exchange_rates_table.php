<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS exchange_rates (
        id VARCHAR(50) PRIMARY KEY, currency_code VARCHAR(10) NOT NULL, currency_name VARCHAR(100),
        rate DECIMAL(15,4) NOT NULL, rate_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_currency (currency_code), INDEX idx_date (rate_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

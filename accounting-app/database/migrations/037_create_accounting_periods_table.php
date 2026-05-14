<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS accounting_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_type ENUM("month","quarter","year") NOT NULL,
        period_code VARCHAR(10) NOT NULL,
        name VARCHAR(100) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status VARCHAR(20) DEFAULT "open",
        is_first TINYINT(1) DEFAULT 0,
        is_last TINYINT(1) DEFAULT 0,
        opened_by VARCHAR(50) DEFAULT NULL,
        opened_at TIMESTAMP NULL DEFAULT NULL,
        closed_by VARCHAR(50) DEFAULT NULL,
        closed_at TIMESTAMP NULL DEFAULT NULL,
        re_open_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_period_code (period_type, period_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

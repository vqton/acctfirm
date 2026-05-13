<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS depreciation_policies (
        id VARCHAR(50) PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(200) NOT NULL,
        method ENUM("straight_line","declining_balance","sum_of_years","production") NOT NULL DEFAULT "straight_line",
        default_life INT DEFAULT 0, default_salvage_rate DECIMAL(5,2) DEFAULT 0,
        status TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

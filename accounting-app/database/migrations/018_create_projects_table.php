<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS projects (
        id VARCHAR(50) PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(200) NOT NULL,
        customer_id VARCHAR(50), start_date DATE, end_date DATE,
        budget DECIMAL(15,2) DEFAULT 0, status VARCHAR(20) DEFAULT "planning",
        notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_code (code), INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

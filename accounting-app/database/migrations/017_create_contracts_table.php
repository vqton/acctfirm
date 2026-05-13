<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS contracts (
        id VARCHAR(50) PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(200) NOT NULL,
        contract_type ENUM("sales","purchase") NOT NULL DEFAULT "sales",
        party_id VARCHAR(50) NOT NULL, party_name VARCHAR(200),
        contract_date DATE, total_amount DECIMAL(15,2) DEFAULT 0,
        currency VARCHAR(10) DEFAULT "VND", status VARCHAR(20) DEFAULT "active",
        notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_code (code), INDEX idx_type (contract_type), INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

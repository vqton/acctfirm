<?php
// Bảng công cụ dụng cụ — tài sản ngắn hạn (TK 153)
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS ccdc (
        id VARCHAR(50) PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(200) NOT NULL,
        unit VARCHAR(50), quantity DECIMAL(15,2) DEFAULT 0,
        allocation_type ENUM("once","installment") NOT NULL DEFAULT "once",
        total_cost DECIMAL(15,2) DEFAULT 0, allocated DECIMAL(15,2) DEFAULT 0,
        status TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_code (code), INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

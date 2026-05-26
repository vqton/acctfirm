<?php
// Bảng danh mục tài khoản kế toán theo Thông tư 99

return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS accounts (
        id VARCHAR(50) PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type ENUM("asset", "liability", "equity", "revenue", "expense") NOT NULL,
        balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_type (type),
        INDEX idx_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
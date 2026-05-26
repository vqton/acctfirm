<?php
// Bảng đơn vị tính — quản lý đơn vị đo lường hàng hóa
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS uoms (
        id VARCHAR(50) PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(200) NOT NULL,
        status TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

<?php
// Bảng tiền đang chuyển — theo dõi tiền chưa về tài khoản ngân hàng
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS cash_transit (
        id VARCHAR(50) PRIMARY KEY,
        amount DECIMAL(15,2) NOT NULL,
        source_account VARCHAR(10) NOT NULL,
        destination_account VARCHAR(10) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        reference VARCHAR(100) DEFAULT NULL,
        status VARCHAR(20) DEFAULT "in_transit",
        transit_date DATE NOT NULL,
        confirm_date DATE DEFAULT NULL,
        created_by VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

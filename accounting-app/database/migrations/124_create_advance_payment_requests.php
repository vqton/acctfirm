<?php
// Bảng đề nghị tạm ứng — Mẫu số 03-TT theo Thông tư 99/2025/TT-BTC
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS advance_payment_requests (
        id VARCHAR(50) PRIMARY KEY,
        request_number VARCHAR(30) NOT NULL,
        request_date DATE NOT NULL,
        requester_name VARCHAR(100) NOT NULL,
        requester_department VARCHAR(100) DEFAULT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        amount_in_words VARCHAR(500) DEFAULT NULL,
        reason TEXT DEFAULT NULL,
        repayment_term VARCHAR(200) DEFAULT NULL,
        status VARCHAR(20) DEFAULT "draft",
        notes TEXT DEFAULT NULL,
        entity_id INT UNSIGNED DEFAULT 1,
        created_by VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

<?php
// Thêm cột code vào accounts — chuẩn hóa mã tài khoản
return function (PDO $pdo) {
    $r = $pdo->query("SHOW COLUMNS FROM accounts LIKE 'code'");
    if (!$r->fetch()) {
        $pdo->exec('ALTER TABLE accounts
            ADD COLUMN code VARCHAR(50) AFTER id,
            ADD COLUMN parent_id VARCHAR(50) DEFAULT NULL AFTER type,
            ADD COLUMN normal_balance ENUM("D","C") NOT NULL DEFAULT "D" AFTER parent_id,
            ADD COLUMN account_class VARCHAR(2) AFTER normal_balance,
            ADD COLUMN description TEXT AFTER balance,
            ADD COLUMN status TINYINT(1) DEFAULT 1 AFTER description,
            ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
            ADD INDEX idx_code (code),
            ADD INDEX idx_parent (parent_id),
            ADD INDEX idx_class (account_class)');
    }
};

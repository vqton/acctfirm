<?php
// Thêm trường mở rộng cho transactions — hỗ trợ thông tin tóm tắt chứng từ
return function (PDO $pdo) {
    $pdo->exec("ALTER TABLE transactions
        ADD COLUMN voucher_type VARCHAR(50) DEFAULT NULL AFTER reference,
        ADD COLUMN source_module VARCHAR(50) DEFAULT NULL AFTER voucher_type,
        ADD COLUMN currency VARCHAR(10) DEFAULT 'VND' AFTER source_module,
        ADD COLUMN exchange_rate DECIMAL(15,4) DEFAULT 1.0000 AFTER currency
    ");

    $pdo->exec("ALTER TABLE ledger_entries
        ADD COLUMN currency VARCHAR(10) DEFAULT 'VND' AFTER note,
        ADD COLUMN exchange_rate DECIMAL(15,4) DEFAULT 1.0000 AFTER currency,
        ADD COLUMN fc_amount DECIMAL(15,2) DEFAULT 0.00 AFTER exchange_rate,
        ADD COLUMN line_order INT DEFAULT 0 AFTER fc_amount
    ");
};

<?php
return function (PDO $pdo) {
    $pdo->exec("ALTER TABLE einvoice_imports
        ADD COLUMN prepay_amount DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Đã tạm ứng' AFTER paid_amount,
        ADD COLUMN prepay_transaction_id VARCHAR(50) DEFAULT NULL COMMENT 'Giao dịch tạm ứng' AFTER prepay_amount,
        ADD KEY idx_prepay (prepay_transaction_id)");
};

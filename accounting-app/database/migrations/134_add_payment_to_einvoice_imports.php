<?php
return function (PDO $pdo) {
    $pdo->exec("ALTER TABLE einvoice_imports
        ADD COLUMN payment_status VARCHAR(20) DEFAULT 'unpaid' COMMENT 'unpaid/partial/paid' AFTER receipt_type,
        ADD COLUMN paid_amount DECIMAL(15,2) DEFAULT 0.00 AFTER payment_status,
        ADD KEY idx_payment_status (payment_status)");
};

<?php
return function (PDO $pdo) {
    $pdo->exec("ALTER TABLE einvoice_imports
        ADD COLUMN goods_receipt_id VARCHAR(50) DEFAULT NULL AFTER transaction_id,
        ADD COLUMN warehouse_id VARCHAR(50) DEFAULT NULL AFTER supplier_id,
        ADD COLUMN receipt_type VARCHAR(20) DEFAULT 'purchase' AFTER warehouse_id,
        ADD KEY idx_goods_receipt (goods_receipt_id)");
};

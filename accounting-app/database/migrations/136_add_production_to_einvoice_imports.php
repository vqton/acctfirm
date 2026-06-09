<?php
return function (PDO $pdo) {
    $pdo->exec("ALTER TABLE einvoice_imports
        ADD COLUMN production_order_id VARCHAR(50) DEFAULT NULL COMMENT 'Lệnh sản xuất' AFTER prepay_transaction_id,
        ADD COLUMN cost_category ENUM('raw_material','overhead','other') DEFAULT 'raw_material' COMMENT 'Loại chi phí' AFTER production_order_id,
        ADD KEY idx_production (production_order_id)");
};

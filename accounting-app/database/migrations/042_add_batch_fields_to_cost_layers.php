<?php
// Thêm cột batch_code, lot_no vào inventory_cost_layers — quản lý lô hàng
return function (PDO $pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM inventory_cost_layers LIKE 'batch_code'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE inventory_cost_layers
            ADD COLUMN batch_code VARCHAR(100) DEFAULT NULL AFTER warehouse_id,
            ADD COLUMN expiry_date DATE DEFAULT NULL AFTER batch_code,
            ADD INDEX idx_batch (batch_code)");
    }
};

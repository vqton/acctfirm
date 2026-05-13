<?php
return function (PDO $pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM inventory_cost_layers LIKE 'warehouse_id'");
    if (!$stmt->fetch()) {
        $pdo->exec('ALTER TABLE inventory_cost_layers
            ADD COLUMN warehouse_id VARCHAR(50) DEFAULT NULL AFTER item_id,
            ADD INDEX idx_warehouse (warehouse_id)');
    }
};

<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS inventory_consignment (
        id VARCHAR(50) PRIMARY KEY,
        item_id VARCHAR(50) NOT NULL,
        qty DECIMAL(15,2) NOT NULL,
        unit_cost DECIMAL(15,2) NOT NULL,
        addon_per_unit DECIMAL(15,2) DEFAULT 0,
        consignee VARCHAR(200) NOT NULL,
        reference VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_item (item_id),
        INDEX idx_consignee (consignee)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

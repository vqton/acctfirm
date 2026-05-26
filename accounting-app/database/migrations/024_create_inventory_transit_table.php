<?php
// Bảng hàng đang đi đường — theo dõi hàng mua chưa về nhập kho
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS inventory_in_transit (
        id VARCHAR(50) PRIMARY KEY,
        item_id VARCHAR(50) NOT NULL,
        qty DECIMAL(15,2) NOT NULL,
        unit_cost DECIMAL(15,2) NOT NULL,
        addon_per_unit DECIMAL(15,2) DEFAULT 0,
        reference VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_item (item_id),
        INDEX idx_ref (reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

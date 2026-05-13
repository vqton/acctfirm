<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS periodic_inventory (
        id VARCHAR(50) PRIMARY KEY,
        item_id VARCHAR(50) NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        opening_qty DECIMAL(15,2) DEFAULT 0,
        opening_value DECIMAL(15,2) DEFAULT 0,
        purchases_qty DECIMAL(15,2) DEFAULT 0,
        purchases_value DECIMAL(15,2) DEFAULT 0,
        closing_qty DECIMAL(15,2) DEFAULT 0,
        closing_value DECIMAL(15,2) DEFAULT 0,
        cogs DECIMAL(15,2) DEFAULT 0,
        reference VARCHAR(100) NOT NULL,
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_item (item_id),
        INDEX idx_period (period_start, period_end)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

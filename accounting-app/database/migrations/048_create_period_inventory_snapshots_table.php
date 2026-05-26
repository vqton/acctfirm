<?php
// Bảng chốt tồn kho cuối kỳ — snapshot số lượng và giá trị
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS period_inventory_snapshots (
        id VARCHAR(50) PRIMARY KEY,
        period_id INT NOT NULL,
        period_code VARCHAR(10) NOT NULL,
        data JSON NOT NULL,
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_period (period_id),
        INDEX idx_code (period_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
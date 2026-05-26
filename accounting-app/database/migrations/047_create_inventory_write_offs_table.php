<?php
// Bảng xóa sổ hàng tồn kho — ghi nhận hàng hỏng, mất, kém chất lượng
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS inventory_write_offs (
        id VARCHAR(50) PRIMARY KEY,
        item_id VARCHAR(50) NOT NULL,
        qty DECIMAL(15,2) NOT NULL,
        unit_cost DECIMAL(15,2) NOT NULL,
        total_cost DECIMAL(15,2) NOT NULL,
        reason VARCHAR(50) NOT NULL,
        expense_account VARCHAR(20) NOT NULL DEFAULT "632",
        reference VARCHAR(100) NOT NULL,
        notes TEXT,
        approved TINYINT(1) DEFAULT 0,
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_item (item_id),
        INDEX idx_reason (reason)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
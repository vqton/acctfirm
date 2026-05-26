<?php
// Bảng trả lại hàng nhà cung cấp — điều chỉnh công nợ và nhập lại kho
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS supplier_returns (
        id VARCHAR(50) PRIMARY KEY,
        item_id VARCHAR(50) NOT NULL,
        qty DECIMAL(15,2) NOT NULL,
        unit_cost DECIMAL(15,2) NOT NULL,
        total_cost DECIMAL(15,2) NOT NULL,
        reference VARCHAR(100) NOT NULL,
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_item (item_id),
        INDEX idx_ref (reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
<?php
// Bảng giảm giá trị hàng tồn kho — trích lập dự phòng (TK 2294)
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS inventory_impairment (
        id VARCHAR(50) PRIMARY KEY,
        item_id VARCHAR(50) NOT NULL,
        provision_amount DECIMAL(15,2) NOT NULL,
        remaining_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        reference VARCHAR(100) NOT NULL,
        notes TEXT,
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_item (item_id),
        INDEX idx_ref (reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

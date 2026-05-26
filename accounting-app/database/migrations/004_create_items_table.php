<?php
// Bảng danh mục hàng hóa, vật tư, nguyên liệu

return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS items (
        id VARCHAR(50) PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(200) NOT NULL,
        item_type ENUM("material","tool","product","merchandise","other") NOT NULL DEFAULT "material",
        unit VARCHAR(50) NOT NULL DEFAULT "cai",
        purchase_price DECIMAL(15,2) DEFAULT 0,
        sale_price DECIMAL(15,2) DEFAULT 0,
        stock_qty DECIMAL(15,2) DEFAULT 0,
        min_stock DECIMAL(15,2) DEFAULT 0,
        description TEXT,
        status TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_code (code),
        INDEX idx_type (item_type),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
<?php
// Bảng tài sản cố định — quản lý TSCĐ hữu hình (TK 211)
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS fixed_assets (
        id VARCHAR(50) PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(200) NOT NULL,
        purchase_date DATE, original_cost DECIMAL(15,2) DEFAULT 0,
        depreciation_method ENUM("straight_line","declining_balance","sum_of_years","production") NOT NULL DEFAULT "straight_line",
        useful_life INT DEFAULT 0, salvage_value DECIMAL(15,2) DEFAULT 0,
        monthly_depreciation DECIMAL(15,2) DEFAULT 0, accumulated_depreciation DECIMAL(15,2) DEFAULT 0,
        net_book_value DECIMAL(15,2) DEFAULT 0, department_id VARCHAR(50),
        employee_id VARCHAR(50), location VARCHAR(200), status VARCHAR(20) DEFAULT "in_use",
        notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_code (code), INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

<?php
// Thêm bảng phân loại TSCĐ và khấu hao chi tiết
return function (PDO $pdo) {
    $pdo->exec('ALTER TABLE fixed_assets
        ADD COLUMN fa_category ENUM("tangible","intangible","finance_lease") NOT NULL DEFAULT "tangible" AFTER status,
        ADD COLUMN fa_type VARCHAR(50) DEFAULT NULL AFTER fa_category,
        ADD COLUMN total_estimated_units DECIMAL(18,2) DEFAULT NULL AFTER salvage_value,
        ADD COLUMN purchase_cost DECIMAL(15,2) DEFAULT 0 AFTER original_cost,
        ADD COLUMN last_depreciation_date DATE DEFAULT NULL AFTER net_book_value');

    $pdo->exec('CREATE TABLE IF NOT EXISTS fixed_asset_depreciation (
        id VARCHAR(50) PRIMARY KEY,
        fixed_asset_id VARCHAR(50) NOT NULL,
        period VARCHAR(7) NOT NULL,
        depreciation_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        accumulated_before DECIMAL(15,2) NOT NULL DEFAULT 0,
        accumulated_after DECIMAL(15,2) NOT NULL DEFAULT 0,
        net_book_before DECIMAL(15,2) NOT NULL DEFAULT 0,
        net_book_after DECIMAL(15,2) NOT NULL DEFAULT 0,
        actual_units DECIMAL(18,2) DEFAULT NULL,
        transaction_id VARCHAR(50) DEFAULT NULL,
        notes VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_fa_period (fixed_asset_id, period),
        INDEX idx_period (period)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

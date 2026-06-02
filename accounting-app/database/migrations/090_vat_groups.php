<?php
return function (PDO $pdo) {
    // Bảng nhóm thuế suất GTGT — định nghĩa các nhóm hàng hóa/dịch vụ và thuế suất tương ứng
    // Tuân thủ NQ 204/2025 (giảm thuế 8% đến 31/12/2026) và Luật VAT 48/2024
    $pdo->exec("CREATE TABLE IF NOT EXISTS vat_groups (
        id VARCHAR(20) NOT NULL PRIMARY KEY,
        code VARCHAR(20) NOT NULL UNIQUE,
        name VARCHAR(200) NOT NULL DEFAULT '',
        default_rate DECIMAL(4,1) NOT NULL DEFAULT 10,
        is_reduction_eligible TINYINT(1) NOT NULL DEFAULT 0,
        reduction_rate DECIMAL(4,1) DEFAULT 8,
        reduction_end_date DATE DEFAULT '2026-12-31',
        is_exempt TINYINT(1) NOT NULL DEFAULT 0,
        exempt_reason VARCHAR(500) DEFAULT NULL,
        is_zero_rated TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Bảng mapping sản phẩm ↔ nhóm thuế
    $pdo->exec("CREATE TABLE IF NOT EXISTS vat_group_products (
        id VARCHAR(20) NOT NULL PRIMARY KEY,
        vat_group_id VARCHAR(20) NOT NULL,
        item_id VARCHAR(20) DEFAULT NULL,
        category_code VARCHAR(50) DEFAULT NULL,
        product_type VARCHAR(100) DEFAULT NULL,
        condition_json TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_vat_group (vat_group_id),
        INDEX idx_item (item_id),
        INDEX idx_category (category_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed dữ liệu nhóm thuế mặc định
    $pdo->exec("INSERT IGNORE INTO vat_groups (id, code, name, default_rate, is_reduction_eligible, reduction_rate, reduction_end_date, is_exempt, is_zero_rated, sort_order) VALUES
        ('vg_10', 'VAT10', 'Hàng hóa/dịch vụ chịu thuế suất 10%', 10, 1, 8, '2026-12-31', 0, 0, 1),
        ('vg_10_no_reduce', 'VAT10NR', 'Hàng hóa/dịch vụ 10% không được giảm (viễn thông, CNTT, NH-BH, CK, BĐS)', 10, 0, NULL, NULL, 0, 0, 2),
        ('vg_5', 'VAT05', 'Hàng hóa/dịch vụ chịu thuế suất 5%', 5, 0, NULL, NULL, 0, 0, 3),
        ('vg_0', 'VAT00', 'Hàng hóa/dịch vụ chịu thuế suất 0% (xuất khẩu)', 0, 0, NULL, NULL, 0, 1, 4),
        ('vg_exempt', 'VATEX', 'Hàng hóa/dịch vụ không chịu thuế GTGT', 0, 0, NULL, NULL, 1, 0, 5),
        ('vg_8', 'VAT08', 'Hàng hóa/dịch vụ thuế suất 8% (giảm từ 10%)', 8, 0, NULL, NULL, 0, 0, 6)");
};

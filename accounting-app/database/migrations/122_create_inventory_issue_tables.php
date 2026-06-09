<?php
// Mẫu 02-VT (Phiếu xuất kho) theo Thông tư 99/2025/TT-BTC
// Header chứa thông tin người nhận, kho, lý do; lines chứa danh sách vật tư
// Quy trình: Draft → Post (tạo bút toán + giảm tồn kho) → Cancelled
// Backward compat: transaction vẫn được tạo qua InventoryService::issueGoods()
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS inventory_issues (
        id VARCHAR(36) PRIMARY KEY,
        issue_number VARCHAR(50) NOT NULL,
        issue_date DATE NOT NULL,
        warehouse_id VARCHAR(36) DEFAULT NULL,
        receiver_name VARCHAR(255) DEFAULT NULL,
        receiver_department VARCHAR(255) DEFAULT NULL,
        issue_reason TEXT DEFAULT NULL,
        issue_type ENUM("sale","production","construction","internal","promotional","other") NOT NULL DEFAULT "sale",
        status ENUM("draft","posted","cancelled") NOT NULL DEFAULT "draft",
        reference VARCHAR(255) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        created_by VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_issue_number (issue_number),
        INDEX idx_issue_date (issue_date),
        INDEX idx_status (status),
        INDEX idx_warehouse (warehouse_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS inventory_issue_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        issue_id VARCHAR(36) NOT NULL,
        item_id VARCHAR(36) NOT NULL,
        item_code VARCHAR(50) NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        uom VARCHAR(20) DEFAULT NULL,
        requested_qty DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        actual_qty DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        line_number INT UNSIGNED NOT NULL DEFAULT 0,
        transaction_id VARCHAR(36) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_issue_id (issue_id),
        INDEX idx_item_id (item_id),
        INDEX idx_transaction (transaction_id),
        CONSTRAINT fk_issue_items_issue FOREIGN KEY (issue_id) REFERENCES inventory_issues(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

<?php
// Bảng phiếu kiểm kê — quản lý số lượng tồn kho thực tế
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS inventory_count_sessions (
        id VARCHAR(50) PRIMARY KEY,
        session_date DATE NOT NULL,
        reference VARCHAR(100) NOT NULL,
        notes TEXT,
        total_items INT DEFAULT 0,
        total_diff DECIMAL(15,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT "draft",
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS inventory_count_lines (
        id VARCHAR(50) PRIMARY KEY,
        session_id VARCHAR(50) NOT NULL,
        item_id VARCHAR(50) NOT NULL,
        system_qty DECIMAL(15,2) NOT NULL DEFAULT 0,
        actual_qty DECIMAL(15,2) NOT NULL DEFAULT 0,
        diff_qty DECIMAL(15,2) NOT NULL DEFAULT 0,
        unit_cost DECIMAL(15,2) DEFAULT 0,
        diff_value DECIMAL(15,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session (session_id),
        INDEX idx_item (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

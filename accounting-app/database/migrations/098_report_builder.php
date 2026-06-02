<?php
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS report_definitions (
        id VARCHAR(50) PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        type VARCHAR(20) NOT NULL DEFAULT 'list',
        source_table VARCHAR(50) NOT NULL,
        fields JSON NOT NULL,
        filters JSON DEFAULT NULL,
        sort_config JSON DEFAULT NULL,
        chart_type VARCHAR(30) DEFAULT NULL,
        chart_config JSON DEFAULT NULL,
        group_by VARCHAR(100) DEFAULT NULL,
        is_public TINYINT(1) DEFAULT 0,
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_type (type),
        INDEX idx_created_by (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};

<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS salary_adjustments (
        id VARCHAR(36) PRIMARY KEY,
        employee_id VARCHAR(36) NOT NULL,
        period_id VARCHAR(36) DEFAULT NULL,
        adjustment_type ENUM("retroactive","correction","bonus","penalty") NOT NULL DEFAULT "correction",
        amount DECIMAL(15,2) NOT NULL,
        reason TEXT NOT NULL,
        status ENUM("draft","approved","posted") NOT NULL DEFAULT "draft",
        approved_by VARCHAR(255) DEFAULT NULL,
        approved_at TIMESTAMP NULL DEFAULT NULL,
        created_by VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        INDEX idx_period (period_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

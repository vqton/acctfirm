<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS employee_allowances (
        id VARCHAR(36) PRIMARY KEY,
        employee_id VARCHAR(36) NOT NULL,
        allowance_config_id VARCHAR(36) NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        effective_from DATE NOT NULL,
        effective_to DATE DEFAULT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (allowance_config_id) REFERENCES allowance_configs(id) ON DELETE CASCADE,
        INDEX idx_employee (employee_id),
        INDEX idx_allowance (allowance_config_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

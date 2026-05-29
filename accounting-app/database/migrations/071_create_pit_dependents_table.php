<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS pit_dependents (
        id VARCHAR(36) PRIMARY KEY,
        employee_id VARCHAR(36) NOT NULL,
        full_name VARCHAR(200) NOT NULL,
        tax_code VARCHAR(20) DEFAULT NULL,
        relationship VARCHAR(50) NOT NULL,
        birth_date DATE DEFAULT NULL,
        effective_from DATE NOT NULL,
        effective_to DATE DEFAULT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        INDEX idx_employee (employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

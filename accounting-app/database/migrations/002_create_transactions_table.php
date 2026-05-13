<?php

return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS transactions (
        id VARCHAR(50) PRIMARY KEY,
        date TIMESTAMP NOT NULL,
        description TEXT,
        reference VARCHAR(100) NOT NULL,
        status ENUM("pending", "posted", "reversed") NOT NULL DEFAULT "pending",
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_date (date),
        INDEX idx_reference (reference),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
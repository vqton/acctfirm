<?php
return function (PDO $pdo) {
    $pdo->exec('ALTER TABLE contracts MODIFY contract_type ENUM("sales","purchase","service","construction") NOT NULL DEFAULT "sales"');
    $pdo->exec('ALTER TABLE contracts ADD COLUMN IF NOT EXISTS partner_code VARCHAR(30) DEFAULT NULL AFTER party_name');
    $pdo->exec('ALTER TABLE contracts ADD COLUMN IF NOT EXISTS start_date DATE DEFAULT NULL AFTER partner_code');
    $pdo->exec('ALTER TABLE contracts ADD COLUMN IF NOT EXISTS end_date DATE DEFAULT NULL AFTER start_date');
    $pdo->exec('ALTER TABLE contracts ADD COLUMN IF NOT EXISTS signed_date DATE DEFAULT NULL AFTER end_date');
    $pdo->exec('ALTER TABLE contracts ADD COLUMN IF NOT EXISTS effective_date DATE DEFAULT NULL AFTER signed_date');
    $pdo->exec('ALTER TABLE contracts ADD COLUMN IF NOT EXISTS reference VARCHAR(30) DEFAULT NULL AFTER id');
    $pdo->exec('ALTER TABLE contracts ADD COLUMN IF NOT EXISTS fulfilled_amount DECIMAL(15,2) DEFAULT 0 AFTER total_amount');
    $pdo->exec('ALTER TABLE contracts ADD COLUMN IF NOT EXISTS paid_amount DECIMAL(15,2) DEFAULT 0 AFTER fulfilled_amount');
    $pdo->exec('ALTER TABLE contracts ADD COLUMN IF NOT EXISTS approved_by VARCHAR(100) DEFAULT NULL AFTER notes');
    $pdo->exec('ALTER TABLE contracts ADD COLUMN IF NOT EXISTS closed_at DATETIME DEFAULT NULL AFTER approved_by');

    $pdo->exec('CREATE TABLE IF NOT EXISTS contract_payment_schedules (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contract_id VARCHAR(50) NOT NULL,
        due_date DATE NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT "pending",
        milestone VARCHAR(255) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
        INDEX idx_contract (contract_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS contract_fulfillment_links (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contract_id VARCHAR(50) NOT NULL,
        linked_type VARCHAR(30) NOT NULL,
        linked_id VARCHAR(100) NOT NULL,
        linked_reference VARCHAR(30) DEFAULT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        description VARCHAR(255) DEFAULT NULL,
        created_by VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
        INDEX idx_contract (contract_id),
        INDEX idx_linked (linked_type, linked_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS contract_amendments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contract_id VARCHAR(50) NOT NULL,
        amendment_no VARCHAR(30) NOT NULL,
        amendment_date DATE NOT NULL,
        type VARCHAR(20) NOT NULL DEFAULT "increase",
        amount_change DECIMAL(15,2) NOT NULL DEFAULT 0,
        description TEXT,
        status VARCHAR(20) NOT NULL DEFAULT "draft",
        created_by VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
        INDEX idx_contract (contract_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

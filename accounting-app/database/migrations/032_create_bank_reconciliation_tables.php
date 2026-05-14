<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS bank_reconciliation_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bank_account_code VARCHAR(10) NOT NULL,
        statement_date DATE NOT NULL,
        statement_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
        book_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
        status VARCHAR(20) DEFAULT "in_progress",
        started_by VARCHAR(50) DEFAULT NULL,
        completed_by VARCHAR(50) DEFAULT NULL,
        completed_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS bank_reconciliation_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        source VARCHAR(20) NOT NULL,
        type VARCHAR(20) NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        reference VARCHAR(100) DEFAULT NULL,
        transaction_date DATE DEFAULT NULL,
        match_status VARCHAR(20) DEFAULT "unmatched",
        matched_item_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES bank_reconciliation_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (matched_item_id) REFERENCES bank_reconciliation_items(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

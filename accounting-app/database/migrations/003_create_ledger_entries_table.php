<?php

return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS ledger_entries (
        id VARCHAR(50) PRIMARY KEY,
        transaction_id VARCHAR(50) NOT NULL,
        account_id VARCHAR(50) NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        is_debit TINYINT(1) NOT NULL,
        note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
        FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
        INDEX idx_transaction_id (transaction_id),
        INDEX idx_account_id (account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
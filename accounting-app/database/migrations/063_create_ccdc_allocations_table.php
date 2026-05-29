<?php
return function (PDO $pdo) {
    $pdo->exec("ALTER TABLE ccdc ADD COLUMN IF NOT EXISTS allocation_months INT DEFAULT 0 AFTER allocation_type");
    $pdo->exec("ALTER TABLE ccdc ADD COLUMN IF NOT EXISTS expense_account VARCHAR(10) DEFAULT '642' AFTER allocation_months");
    $pdo->exec("ALTER TABLE ccdc ADD COLUMN IF NOT EXISTS allocation_start_date DATE DEFAULT NULL AFTER expense_account");
    $pdo->exec("ALTER TABLE ccdc ADD COLUMN IF NOT EXISTS remaining_months INT DEFAULT 0 AFTER allocated");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ccdc_allocations (
        id VARCHAR(50) PRIMARY KEY,
        ccdc_id VARCHAR(50) NOT NULL,
        period VARCHAR(7) NOT NULL COMMENT 'Kỳ phân bổ (YYYY-MM)',
        amount DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Số tiền phân bổ trong kỳ',
        expense_account VARCHAR(10) NOT NULL DEFAULT '642' COMMENT 'TK chi phí',
        transaction_id VARCHAR(50) DEFAULT NULL COMMENT 'Bút toán đã post',
        status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending/posted',
        created_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ccdc_id) REFERENCES ccdc(id) ON DELETE CASCADE,
        INDEX idx_ccdc_alloc_period (ccdc_id, period),
        INDEX idx_ccdc_alloc_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};

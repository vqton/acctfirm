<?php
// Bảng ghi nhận lịch sử gộp/tách tài khoản — audit trail cho COA restructuring
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS account_merge_splits (
        id VARCHAR(50) PRIMARY KEY,
        type ENUM('merge','split') NOT NULL COMMENT 'merge: gộp, split: tách',
        source_codes TEXT NOT NULL COMMENT 'Mã tài khoản nguồn (JSON array)',
        target_codes TEXT NOT NULL COMMENT 'Mã tài khoản đích (JSON array)',
        source_balances TEXT DEFAULT NULL COMMENT 'Số dư nguồn trước khi xử lý (JSON)',
        target_balances TEXT DEFAULT NULL COMMENT 'Số dư đích sau khi xử lý (JSON)',
        transfer_reference VARCHAR(50) DEFAULT NULL COMMENT 'Số chứng từ bút toán chuyển',
        approved_by VARCHAR(100) DEFAULT NULL COMMENT 'Người phê duyệt (KT trưởng/CFO)',
        approved_at DATETIME DEFAULT NULL COMMENT 'Thời điểm phê duyệt',
        reason TEXT DEFAULT NULL COMMENT 'Lý do gộp/tách',
        created_by VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ms_type (type),
        INDEX idx_ms_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Branch entity_id column for accounts
    $r = $pdo->query("SHOW COLUMNS FROM accounts LIKE 'entity_id'");
    if (!$r->fetch()) {
        $pdo->exec("ALTER TABLE accounts ADD COLUMN entity_id INT UNSIGNED DEFAULT NULL COMMENT 'Đơn vị kế toán (branch)' AFTER is_control,
            ADD INDEX idx_entity (entity_id)");
    }
};

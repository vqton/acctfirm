<?php
return function (PDO $pdo) {
    $pdo->exec("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS is_correction TINYINT(1) DEFAULT 0 AFTER exchange_rate");
    $pdo->exec("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS correction_type VARCHAR(20) DEFAULT NULL AFTER is_correction");
    $pdo->exec("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS original_transaction_id VARCHAR(50) DEFAULT NULL AFTER correction_type");
    $pdo->exec("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS correction_reason TEXT DEFAULT NULL AFTER original_transaction_id");
    $pdo->exec("ALTER TABLE transactions ADD INDEX IF NOT EXISTS idx_original_txn (original_transaction_id)");
};

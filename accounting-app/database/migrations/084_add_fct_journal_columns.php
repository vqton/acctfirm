<?php
return function (PDO $pdo) {
    // Thêm cột expense_account_code và journal_id vào fct_contracts
    // expense_account_code: Tài khoản chi phí (641, 642, 635, 627, 241...)
    // journal_id: ID bút toán đã post khi ghi nhận hợp đồng
    $pdo->exec("ALTER TABLE fct_contracts ADD COLUMN IF NOT EXISTS expense_account_code VARCHAR(50) DEFAULT '642' AFTER service_type");
    $pdo->exec("ALTER TABLE fct_contracts ADD COLUMN IF NOT EXISTS journal_id VARCHAR(36) DEFAULT NULL AFTER expense_account_code");
};

<?php
// Bảng phê duyệt bút toán — quy trình duyệt trước khi ghi sổ
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS journal_entry_approvals (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(50) NOT NULL,
        action VARCHAR(20) NOT NULL COMMENT "submit|approve|reject|return",
        actor VARCHAR(100) NOT NULL,
        comment TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_transaction (transaction_id),
        KEY idx_actor (actor)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

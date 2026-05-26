<?php
// Bảng luồng phê duyệt — routing duyệt theo cấp bậc
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS approval_routing (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        min_amount DECIMAL(15,2) DEFAULT NULL,
        max_amount DECIMAL(15,2) DEFAULT NULL,
        account_type VARCHAR(20) DEFAULT NULL,
        module VARCHAR(50) DEFAULT NULL,
        required_role VARCHAR(50) NOT NULL,
        priority INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Seed default rules
    $pdo->exec("INSERT IGNORE INTO approval_routing (min_amount, max_amount, account_type, module, required_role, priority) VALUES
        (NULL, 10000000, NULL, 'petty_cash', 'chief_accountant', 10),
        (10000000, 100000000, NULL, NULL, 'chief_accountant', 20),
        (100000000, NULL, NULL, NULL, 'director', 30),
        (NULL, NULL, NULL, 'intercompany', 'cfo', 40)
    ");
};

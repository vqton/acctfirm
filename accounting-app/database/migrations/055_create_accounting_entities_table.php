<?php
// Bảng đơn vị kế toán — hỗ trợ nhiều cơ sở/công ty con
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS accounting_entities (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        type ENUM('head_office','branch','factory') NOT NULL DEFAULT 'branch',
        tax_code VARCHAR(20) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Thêm entity_id vào transactions nếu chưa có
    try {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN entity_id INT UNSIGNED DEFAULT NULL AFTER id");
    } catch (\PDOException $e) {
        // Column may already exist
    }
    try {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN is_intercompany TINYINT(1) NOT NULL DEFAULT 0 AFTER entity_id");
    } catch (\PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN related_entity_id INT UNSIGNED DEFAULT NULL AFTER is_intercompany");
    } catch (\PDOException $e) {}

    // Seed default head office entity
    $stmt = $pdo->query("SELECT COUNT(*) FROM accounting_entities");
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO accounting_entities (code, name, type) VALUES ('HO', 'Trụ sở chính', 'head_office')");
    }
};

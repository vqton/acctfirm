<?php
return function (PDO $pdo) {
    try {
        $pdo->exec('ALTER TABLE inventory_issues ADD COLUMN entity_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER issue_type');
    } catch (PDOException $e) {
        // Cột đã tồn tại — idempotent
    }
    try {
        $pdo->exec('ALTER TABLE inventory_issue_items ADD COLUMN entity_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER issue_id');
    } catch (PDOException $e) {
        // Cột đã tồn tại — idempotent
    }
};

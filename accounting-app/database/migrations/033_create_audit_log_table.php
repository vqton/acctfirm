<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS audit_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        action VARCHAR(50) NOT NULL,
        resource_type VARCHAR(50) NOT NULL,
        resource_id VARCHAR(50) DEFAULT NULL,
        actor_id VARCHAR(50) DEFAULT NULL,
        actor_email VARCHAR(100) DEFAULT NULL,
        old_values JSON DEFAULT NULL,
        new_values JSON DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        request_id VARCHAR(36) DEFAULT NULL,
        created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
        INDEX idx_action (action, created_at),
        INDEX idx_resource (resource_type, resource_id, created_at),
        INDEX idx_actor (actor_id, created_at),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

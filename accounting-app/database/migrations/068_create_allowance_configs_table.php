<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS allowance_configs (
        id VARCHAR(36) PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        type ENUM("fixed","percentage") NOT NULL DEFAULT "fixed",
        default_value DECIMAL(15,2) NOT NULL DEFAULT 0,
        tax_exempt TINYINT(1) NOT NULL DEFAULT 0,
        insurable TINYINT(1) NOT NULL DEFAULT 1,
        max_exempt_amount DECIMAL(15,2) DEFAULT NULL,
        account_code VARCHAR(20) DEFAULT "642",
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $stmt = $pdo->query('SELECT COUNT(*) FROM allowance_configs');
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("INSERT IGNORE INTO allowance_configs (id, code, name, type, default_value, tax_exempt, insurable, max_exempt_amount, account_code) VALUES
            ('ac_position', 'POSITION', 'Phu cap chuc vu', 'fixed', 0, 0, 1, NULL, '642'),
            ('ac_responsibility', 'RESPONSIBILITY', 'Phu cap trach nhiem', 'fixed', 0, 0, 1, NULL, '642'),
            ('ac_meal', 'MEAL', 'Phu cap an trua', 'fixed', 730000, 1, 0, 730000, '642'),
            ('ac_transport', 'TRANSPORT', 'Phu cap xang xe', 'fixed', 300000, 1, 0, NULL, '642'),
            ('ac_phone', 'PHONE', 'Phu cap dien thoai', 'fixed', 200000, 1, 0, NULL, '642'),
            ('ac_hazardous', 'HAZARDOUS', 'Phu cap doc hai', 'fixed', 0, 1, 0, NULL, '642'),
            ('ac_attractive', 'ATTRACTIVE', 'Phu cap thu hut', 'fixed', 0, 0, 1, NULL, '642'),
            ('ac_housing', 'HOUSING', 'Phu cap nha o', 'fixed', 0, 1, 0, NULL, '642'),
            ('ac_uniform', 'UNIFORM', 'Phu cap trang phuc', 'fixed', 0, 1, 0, 5000000, '642')");
    }
};

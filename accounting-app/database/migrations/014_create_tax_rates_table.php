<?php
// Bảng thuế suất — quản lý thuế GTGT, TNCN, TNDN
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS tax_rates (
        id VARCHAR(50) PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(200) NOT NULL,
        rate DECIMAL(5,2) NOT NULL, tax_type ENUM("vat","excise","import_duty","environment","natural_resource","other") NOT NULL DEFAULT "vat",
        status TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_code (code), INDEX idx_type (tax_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

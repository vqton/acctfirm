<?php
// Bảng sinh số chứng từ tự động — đảm bảo tăng dần, không trùng
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS voucher_sequences (
        prefix VARCHAR(10) NOT NULL,
        year INT NOT NULL DEFAULT 0,
        last_no INT NOT NULL DEFAULT 0,
        PRIMARY KEY (prefix, year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};

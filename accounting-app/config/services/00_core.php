<?php
// TỰ ĐỘNG SINH TỪ services.php — không sửa trực tiếp. Sửa services.php và chạy split script.

use Accounting\Infrastructure\Logging\LoggingPDO;

// === LỚP INFRASTRUCTURE: PDO + Logging ===
// PDO thật (innerPdo) được bọc trong LoggingPDO để log SQL tự động
$dbConfig = require __DIR__ . '/../database.php';
$innerPdo = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
    $dbConfig['username'], $dbConfig['password'], $dbConfig['options']
);
$pdo = new LoggingPDO($innerPdo);

<?php
// database/migrate.php — Runner: load config once, run all migrations

$dbConfig = require __DIR__ . '/../config/database.php';

try {
    $tmp = new PDO("mysql:host={$dbConfig['host']};charset={$dbConfig['charset']}", $dbConfig['username'], $dbConfig['password']);
    $tmp->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['dbname']}` DEFAULT CHARACTER SET utf8mb4");
    $tmp = null;
} catch (PDOException $e) {
    die("DB create failed: {$e->getMessage()}\n");
}

$pdo = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
    $dbConfig['username'],
    $dbConfig['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$files = glob(__DIR__ . '/migrations/*.php');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    echo "Running: {$name}... ";
    try {
        $fn = require $file;
        if (is_callable($fn)) {
            $fn($pdo);
        }
        echo "OK\n";
    } catch (Exception $e) {
        echo "ERROR: {$e->getMessage()}\n";
        exit(1);
    }
}

echo "\nAll migrations complete.\n";
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

// Ensure tracking table exists
$pdo->exec('CREATE TABLE IF NOT EXISTS _migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(200) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

// Fetch already-executed migrations
$executed = [];
$stmt = $pdo->query('SELECT migration FROM _migrations');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $executed[$row['migration']] = true;
}

$files = glob(__DIR__ . '/migrations/*.php');
sort($files);

$count = 0;
foreach ($files as $file) {
    $name = basename($file);

    if (isset($executed[$name])) {
        echo "[SKIP] {$name} (already executed)\n";
        continue;
    }

    echo "Running: {$name}... ";
    try {
        $fn = require $file;
        if (is_callable($fn)) {
            $fn($pdo);
        }
        $pdo->prepare('INSERT INTO _migrations (migration) VALUES (?)')->execute([$name]);
        echo "OK\n";
        $count++;
    } catch (Exception $e) {
        echo "ERROR: {$e->getMessage()}\n";
        exit(1);
    }
}

if ($count === 0) {
    echo "\nNo new migrations to run.\n";
} else {
    echo "\n{$count} migration(s) complete.\n";
}
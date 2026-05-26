<?php
// database/migrate.php — Runner: load config once, run all migrations
// Chạy bằng lệnh: php database/migrate.php
// Yêu cầu: Tất cả migration mới phải được thêm vào database/migrations/ với tên NNN_description.php

// === TẠO DATABASE nếu chưa tồn tại ===
// CREATE DATABASE IF NOT EXISTS — idempotent: an toàn chạy nhiều lần
// Chỉ tạo DB, chưa tạo table — các migration sau sẽ tạo table cụ thể
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

// === BẢNG THEO DÕI MIGRATION — đảm bảo mỗi file chỉ chạy một lần ===
// _migrations: tracking table — ghi lại tất cả migration đã chạy thành công
// migration: tên file (VD: 001_create_users.sql.php) — UNIQUE để chống chạy trùng
// executed_at: thời gian chạy — hỗ trợ debug khi cần
// FORBIDDEN: Không được xóa hoặc sửa _migrations table — sẽ gây chạy lại migration cũ
$pdo->exec('CREATE TABLE IF NOT EXISTS _migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(200) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

// === LẤY DANH SÁCH MIGRATION ĐÃ CHẠY ===
// Đọc tất cả migration đã chạy từ DB — lưu vào mảng $executed để so sánh
// $executed[filename] = true — lookup nhanh, O(1)
$executed = [];
$stmt = $pdo->query('SELECT migration FROM _migrations');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $executed[$row['migration']] = true;
}

// === LẤY DANH SÁCH FILE MIGRATION ===
// glob: tìm tất cả file .php trong thư mục migrations/
// sort: đảm bảo thứ tự chạy theo tên file (001_ trước 002_)
$files = glob(__DIR__ . '/migrations/*.php');
sort($files);

$count = 0;
foreach ($files as $file) {
    $name = basename($file);

    // Bỏ qua nếu đã chạy — dựa trên _migrations table
    if (isset($executed[$name])) {
        echo "[SKIP] {$name} (already executed)\n";
        continue;
    }

    // Mỗi migration là một file PHP trả về closure fn(PDO $pdo)
    // Migration file mẫu:
    // <?php
    // return function (PDO $pdo) {
    //     $pdo->exec('CREATE TABLE IF NOT EXISTS ...');
    // };
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
        // Nếu migration thất bại, không ghi vào _migrations → có thể chạy lại sau khi fix
        echo "ERROR: {$e->getMessage()}\n";
        exit(1);
    }
}

if ($count === 0) {
    echo "\nNo new migrations to run.\n";
} else {
    echo "\n{$count} migration(s) complete.\n";
}
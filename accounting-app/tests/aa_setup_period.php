<?php
// Setup: Tạo kỳ kế toán mặc định cho các test khác
// Chạy đầu tiên (alphabetical order) trước mọi test cần period

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Tạo kỳ kế toán tháng 5/2026 (đang mở) nếu chưa có
$stmt = $pdo->query("SELECT COUNT(*) FROM accounting_periods WHERE period_code = '2026-05'");
if ((int)$stmt->fetchColumn() === 0) {
    $pdo->prepare("INSERT INTO accounting_periods (period_type, period_code, name, start_date, end_date, status, opened_by, opened_at)
        VALUES (?, ?, ?, ?, ?, 'open', ?, NOW())")->execute(['month', '2026-05', 'Tháng 5/2026', '2026-05-01', '2026-05-31', 'setup']);
}

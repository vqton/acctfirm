<?php
// Test: Bảng cân đối tài khoản — tổng Dr = tổng Cr
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Service\TrialBalanceService;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$svc = new TrialBalanceService($pdo);

// Nghiệp vụ: Bảng cân đối tài khoản — tổng hợp số dư tất cả TK
// Kiểm tra cấu trúc trả về: items, grand_total_dr, grand_total_cr, balanced
// Nếu fail → không thể xem bảng cân đối tài khoản → audit fail
// Giả định: Có ít nhất 1 tài khoản có số dư
$tb = $svc->getTrialBalance();
echo "\n";
assertTrue(isset($tb['items']), 'TB has items');
assertTrue(isset($tb['grand_total_dr']), 'TB has grand total dr');
assertTrue(isset($tb['grand_total_cr']), 'TB has grand total cr');
assertTrue(isset($tb['balanced']), 'TB has balanced flag');
assertTrue(count($tb['items']) > 0, 'At least 1 account in TB');

// Verify Dr ≈ Cr
$diff = abs($tb['grand_total_dr'] - $tb['grand_total_cr']);
assertTrue($diff < 10 || $tb['balanced'], "TB balanced: Dr={$tb['grand_total_dr']}, Cr={$tb['grand_total_cr']}, diff={$diff}");

// Test with period filter
$stmt = $pdo->query("SELECT period_code FROM accounting_periods LIMIT 1");
$period = $stmt->fetchColumn();
if ($period) {
    $tb2 = $svc->getTrialBalance($period);
    assertTrue(isset($tb2['items']), 'Period-filtered TB works');
}

results();

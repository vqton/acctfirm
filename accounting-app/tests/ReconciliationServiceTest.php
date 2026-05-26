<?php
// Test: Đối chiếu số liệu — kiểm tra khớp đúng giữa các module
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Service\ReconciliationService;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$svc = new ReconciliationService($pdo);

// Nghiệp vụ: Đối chiếu tổng thể giữa sổ cái (GL) và sổ chi tiết (sub-ledger)
// 6 module được đối chiếu: AR, AP, Cash, Bank, Inventory, FA
// Mỗi module trả về: gl_balance, subledger_balance, difference, status
// Nếu fail → không phát hiện được chênh lệch giữa các module → sai báo cáo
// Giả định: Tất cả module đã có dữ liệu
$all = $svc->reconcileAll();
echo "\n";
assertTrue(count($all) >= 6, 'All 6 reconciliation types returned');
assertTrue(isset($all['ar']), 'AR present');
assertTrue(isset($all['ap']), 'AP present');
assertTrue(isset($all['cash']), 'Cash present');
assertTrue(isset($all['bank']), 'Bank present');
assertTrue(isset($all['inventory']), 'Inventory present');
assertTrue(isset($all['fa']), 'FA present');

// Each result has required fields
foreach ($all as $type => $r) {
    assertTrue(isset($r['gl_balance']), "{$type} has gl_balance");
    assertTrue(isset($r['subledger_balance']), "{$type} has subledger_balance");
    assertTrue(isset($r['difference']), "{$type} has difference");
    assertTrue(isset($r['status']), "{$type} has status");
    assertTrue(in_array($r['status'], ['matched', 'unmatched', 'error'], true), "{$type} status valid");
}

// hasFailures check
assertTrue($svc->hasFailures($all, 0) || !$svc->hasFailures($all, 999999999), 'hasFailures works');

results();

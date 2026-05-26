<?php
// Test: Báo cáo tồn kho — số lượng và giá trị
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Infrastructure\Persistence\PDOItemRepository;
use Accounting\Infrastructure\Persistence\PDOWarehouseRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$itemRepo = new PDOItemRepository($pdo);
$warehouseRepo = new PDOWarehouseRepository($pdo);
$journal = new JournalService($accountRepo, $txnRepo, $pdo);
$svc = new InventoryService($accountRepo, $txnRepo, $itemRepo, $warehouseRepo, $journal, $pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>10){echo"FAIL: {$m} expected {$a} got {$b}\n";$failed++;}
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM inventory_cost_layers');
$pdo->exec('DELETE FROM transactions');
$pdo->exec('DELETE FROM ledger_entries');

$item = $itemRepo->findByCode('VT001');
assertTrue($item !== null, 'Test item VT001 exists');
$item->setStockQty(0);
$itemRepo->save($item);

// Setup: receive goods, issue some
$svc->receiveGoods($item->getId(), 100, 50000, [], 'PO-AGING-001', 'tester');
$svc->receiveGoods($item->getId(), 50, 60000, [], 'PO-AGING-002', 'tester');
$svc->issueGoods($item->getId(), 30, 'sale', 'SALE-AGING', 'tester');

echo "\n=== Test 1: Aging report ===\n";
$aging = $svc->getAgingReport();
assertTrue(count($aging['items']) >= 1, 'Aging has items');
assertTrue($aging['items'][0]['total_qty'] > 0, 'Aging has positive qty');
assertTrue($aging['items'][0]['total_value'] > 0, 'Aging has positive value');

echo "\n=== Test 2: Aging by specific item ===\n";
$agingItem = $svc->getAgingReport($item->getId());
assertEq(1, count($agingItem['items']), 'Aging filtered to 1 item');
assertEq(120, $agingItem['items'][0]['total_qty'], 'Total qty = 120 (100+50-30)');

// Nghiệp vụ: Hệ số vòng quay hàng tồn kho — chỉ số quản trị quan trọng
// Nếu fail → không đánh giá được hiệu quả quản lý tồn kho
echo "\n=== Test 3: Turnover ratio ===\n";
$turnover = $svc->getTurnoverRatio('2026-05-01', '2026-05-31');
assertTrue(isset($turnover['turnover_ratio']), 'Turnover ratio returned');
assertTrue($turnover['total_cogs'] >= 0, 'COGS is non-negative');

echo "\n=== Test 4: Valuation report ===\n";
$valuation = $svc->getValuationReport();
assertTrue(count($valuation['items']) >= 1, 'Valuation has items');
assertTrue(isset($valuation['items'][0]['opening_qty']), 'Valuation has opening qty');
assertTrue(isset($valuation['items'][0]['closing_qty']), 'Valuation has closing qty');

echo "\n=== Test 5: Valuation by item and period ===\n";
$valItem = $svc->getValuationReport($item->getId(), null, '2026-05-01', '2026-05-31');
assertEq(1, count($valItem['items']), 'Valuation filtered to 1 item');
assertEq(120, $valItem['items'][0]['closing_qty'], 'Closing qty = 120');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

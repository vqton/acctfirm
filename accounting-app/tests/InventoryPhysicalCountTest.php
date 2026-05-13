<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\InventoryService;
use Accounting\Infrastructure\Repository\PDOAccountRepository;
use Accounting\Infrastructure\Repository\PDOTransactionRepository;
use Accounting\Infrastructure\Repository\PDOItemRepository;
use Accounting\Infrastructure\Repository\PDOWarehouseRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$itemRepo = new PDOItemRepository($pdo);
$warehouseRepo = new PDOWarehouseRepository($pdo);
$svc = new InventoryService($accountRepo, $txnRepo, $itemRepo, $warehouseRepo);

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
$pdo->exec('DELETE FROM inventory_count_sessions');
$pdo->exec('DELETE FROM inventory_count_lines');

$item = $itemRepo->findByCode('VT001');
if (!$item) { echo "FAIL: VT001 not found\n"; exit(1); }
$item->setStockQty(0);
$itemRepo->save($item);

// Receive goods
$svc->receiveGoods($item->getId(), 100, 50000, [], 'PO-COUNT-001', 'tester');
$svc->calculateAndUpdateUnitCost($item->getId());

echo "\n=== Test 1: Physical count surplus (actual > system) ===\n";
// System says 100, actual count = 120 → surplus 20
$result = $svc->adjustPhysicalCount($item->getId(), 120, 'COUNT-001', 'tester');

$itemAfter = $itemRepo->findById($item->getId());
assertEq(120, $itemAfter->getStockQty(), 'Stock adjusted to 120');

// Surplus: Dr 152 — Cr 711 (other income) = 20 x 50000
$inv = $accountRepo->findByCode('152')->getBalance();
$otherIncome = $accountRepo->findByCode('711')->getBalance();
$expectedSurplus = 20 * 50000;
assertEq(120 * 50000, $inv, "Inventory (152) = 6,000,000");
assertEq($expectedSurplus, $otherIncome, "Other income (711) = {$expectedSurplus}");

echo "\n=== Test 2: Physical count shortage (actual < system) ===\n";
// System says 120, actual count = 90 → shortage 30
$result2 = $svc->adjustPhysicalCount($item->getId(), 90, 'COUNT-002', 'tester');

$itemAfter2 = $itemRepo->findById($item->getId());
assertEq(90, $itemAfter2->getStockQty(), 'Stock adjusted to 90');

// Shortage: Dr 632 — Cr 152 = 30 x 50000
$inv2 = $accountRepo->findByCode('152')->getBalance();
$cogs = $accountRepo->findByCode('632')->getBalance();
$expectedShortage = 30 * 50000;
assertEq(90 * 50000, $inv2, "Inventory (152) = 4,500,000");
assertEq($expectedShortage, $cogs, "COGS (632) = {$expectedShortage}");

echo "\n=== Test 3: Physical count exact match (no adjustment) ===\n";
$result3 = $svc->adjustPhysicalCount($item->getId(), 90, 'COUNT-003', 'tester');

$itemAfter3 = $itemRepo->findById($item->getId());
assertEq(90, $itemAfter3->getStockQty(), 'Stock unchanged at 90');
assertTrue(!isset($result3['adjusted']), 'No adjustment needed');

echo "\n=== Test 4: Count session creation ===\n";
$session = $svc->createCountSession([
    ['item_id' => $item->getId(), 'actual_qty' => 100],
], 'COUNT-SESSION-001', 'Period-end count', 'tester');

assertTrue(isset($session['session_id']), 'Count session created');
assertTrue($session['total_items'] > 0, 'Session has items');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

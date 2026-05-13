<?php
// Test: Inventory Transfer between warehouses

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

// Setup: reset balances + seed test warehouses
$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM inventory_cost_layers');

$whA_id = 'wh_a';
$whB_id = 'wh_b';
$pdo->exec("INSERT IGNORE INTO warehouses (id, code, name) VALUES ('{$whA_id}', 'KHO-A', 'Warehouse A')");
$pdo->exec("INSERT IGNORE INTO warehouses (id, code, name) VALUES ('{$whB_id}', 'KHO-B', 'Warehouse B')");

$item = $itemRepo->findByCode('VT001');
if (!$item) { echo "FAIL: VT001 not found\n"; exit(1); }
$item->setStockQty(0);
$itemRepo->save($item);

// Receive goods into default (no warehouse = general stock)
$svc->receiveGoods($item->getId(), 100, 50000, [], 'PO-TRF-001', 'tester');

echo "\n=== Test 1: Basic transfer between warehouses ===\n";
// Transfer 30 units from general stock to Warehouse A
$result = $svc->transferGoods($item->getId(), 30, null, $whA_id, 'TRF-001', 'tester');

// Item stock_qty unchanged (still same item)
$itemAfter = $itemRepo->findById($item->getId());
assertEq(100, $itemAfter->getStockQty(), 'Stock qty unchanged after transfer');

// Journal entry created
$txn = $txnRepo->findById($result['transaction_id']);
assertTrue($txn !== null, 'Transfer journal entry created');
assertEq('posted', $txn->getStatus(), 'Transfer journal posted');

// Cost: 30 x 50,000 = 1,500,000
$expectedCost = 30 * 50000;
assertEq($expectedCost, $result['total_cost'], "Transfer total cost = {$expectedCost}");

// Check cost layers: warehouse A has 30 units
$stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id = ?");
$stmt->execute([$item->getId(), $whA_id]);
assertEq(30, (float)$stmt->fetchColumn(), '30 units moved to Warehouse A cost layers');

// General stock (warehouse_id IS NULL) has 70 units
$stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id IS NULL");
$stmt->execute([$item->getId()]);
assertEq(70, (float)$stmt->fetchColumn(), '70 units remain in general stock');

echo "\n=== Test 2: Transfer from specific warehouse to another ===\n";
// Transfer 10 units from Warehouse A to Warehouse B
$result2 = $svc->transferGoods($item->getId(), 10, $whA_id, $whB_id, 'TRF-002', 'tester');

// Warehouse A now has 20, Warehouse B has 10, general still 70
$stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id = ?");
$stmt->execute([$item->getId(), $whA_id]);
assertEq(20, (float)$stmt->fetchColumn(), 'Warehouse A has 20 units after onward transfer');

$stmt->execute([$item->getId(), $whB_id]);
assertEq(10, (float)$stmt->fetchColumn(), 'Warehouse B has 10 units');

echo "\n=== Test 3: Insufficient stock in source warehouse ===\n";
try {
    $svc->transferGoods($item->getId(), 999, $whA_id, $whB_id, 'TRF-BAD', 'tester');
    echo "FAIL: Insufficient stock was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Insufficient stock in source warehouse rejected');
}

echo "\n=== Test 4: Invalid warehouse throws error ===\n";
try {
    $svc->transferGoods($item->getId(), 5, 'nonexistent', $whA_id, 'TRF-BAD2', 'tester');
    echo "FAIL: Invalid warehouse not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Invalid source warehouse rejected');
}

try {
    $svc->transferGoods($item->getId(), 5, $whA_id, 'nonexistent', 'TRF-BAD3', 'tester');
    echo "FAIL: Invalid destination warehouse not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Invalid destination warehouse rejected');
}

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

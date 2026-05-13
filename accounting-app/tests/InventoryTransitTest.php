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
$pdo->exec('DELETE FROM inventory_in_transit');

$item = $itemRepo->findByCode('VT001');
if (!$item) { echo "FAIL: VT001 not found\n"; exit(1); }
$item->setStockQty(0);
$itemRepo->save($item);

echo "\n=== Test 1: Record goods in transit ===\n";
$result = $svc->recordInTransit($item->getId(), 50, 60000, [], 'PO-INTRANSIT-001', 'tester');

// Check journal: Dr 151 (goods in transit) — Cr 331 (AP)
$txn151 = $accountRepo->findByCode('151')->getBalance();
$ap = $accountRepo->findByCode('331')->getBalance();
$expected = 50 * 60000;
assertEq($expected, $txn151, "Goods in transit (151) increased by {$expected}");
assertEq($expected, $ap, "AP (331) increased by {$expected}");

// Stock qty unchanged
$itemCheck = $itemRepo->findById($item->getId());
assertEq(0, $itemCheck->getStockQty(), 'Stock qty unchanged (0)');

echo "\n=== Test 2: Receive goods from transit ===\n";
$result2 = $svc->receiveFromTransit($result['transit_id'], 50, 'RECV-FROM-TRANSIT-001', 'tester');

// Stock increased
$itemCheck2 = $itemRepo->findById($item->getId());
assertEq(50, $itemCheck2->getStockQty(), 'Stock qty increased to 50');

// Inventory (152) increased, transit (151) decreased to 0
$inv = $accountRepo->findByCode('152')->getBalance();
$txn151_after = $accountRepo->findByCode('151')->getBalance();
assertEq($expected, $inv, "Inventory (152) increased by {$expected}");
assertEq(0, $txn151_after, 'Transit (151) back to 0');

// Cost layer created
$stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) FROM inventory_cost_layers WHERE item_id = ?");
$stmt->execute([$item->getId()]);
assertEq(50, (float)$stmt->fetchColumn(), 'Cost layer qty = 50');

echo "\n=== Test 3: Partial receive from transit ===\n";
// Record another 30 in transit
$result3 = $svc->recordInTransit($item->getId(), 30, 70000, [['description' => 'Freight', 'amount' => 60000]], 'PO-INTRANSIT-002', 'tester');

// Receive 20 of 30
$result4 = $svc->receiveFromTransit($result3['transit_id'], 20, 'RECV-PARTIAL', 'tester');

// Stock: 50 + 20 = 70
$itemCheck3 = $itemRepo->findById($item->getId());
assertEq(70, $itemCheck3->getStockQty(), 'Stock qty after partial = 70');

// Remaining in transit = 10
$stmt = $pdo->prepare("SELECT qty FROM inventory_in_transit WHERE id = ?");
$stmt->execute([$result3['transit_id']]);
assertEq(10, (float)$stmt->fetchColumn(), 'Remaining in transit qty = 10');

echo "\n=== Test 4: Invalid transit reference ===\n";
try {
    $svc->receiveFromTransit('nonexistent', 5, 'BAD', 'tester');
    echo "FAIL: Invalid transit not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Invalid transit reference rejected');
}

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

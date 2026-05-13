<?php
// Test: Goods Issue / COGS — issue inventory to production or sale

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

$item = $itemRepo->findByCode('VT001');
if (!$item) { echo "FAIL: VT001 not found\n"; exit(1); }
$item->setStockQty(0);
$itemRepo->save($item);

// First: receive goods to have stock
$svc->receiveGoods($item->getId(), 100, 50000, [], 'PO-REC-001', 'tester');
$svc->calculateAndUpdateUnitCost($item->getId());

$origCost = $itemRepo->findById($item->getId())->getPurchasePrice();

echo "\n=== Test 1: Issue goods for sale (Dr COGS — Cr Inventory) ===\n";
$result = $svc->issueGoods($item->getId(), 10, 'sale', 'ORDER-001', 'tester');

// Check stock decreased
$itemAfter = $itemRepo->findById($item->getId());
assertEq(90, $itemAfter->getStockQty(), 'Stock decreased by 10 (100 → 90)');

// Check COGS account (TK 632) increased
$cogs = $accountRepo->findByCode('632')->getBalance();
$expectedCogs = 10 * $origCost;
assertEq($expectedCogs, $cogs, "COGS increased by {$expectedCogs} (10 × {$origCost})");

// Check Inventory (152) decreased
$inv = $accountRepo->findByCode('152')->getBalance();
$origInv = 100 * 50000; // first receipt
$expectedInv = $origInv - $expectedCogs;
assertEq($expectedInv, $inv, "Inventory decreased by same amount ({$expectedInv})");

echo "\n=== Test 2: Issue goods for production (Dr WIP — Cr Inventory) ===\n";
$result2 = $svc->issueGoods($item->getId(), 20, 'production', 'PROD-001', 'tester');

$itemAfter2 = $itemRepo->findById($item->getId());
assertEq(70, $itemAfter2->getStockQty(), 'Stock decreased by 20 (90 → 70)');

// WIP account (TK 154) increased
$wip = $accountRepo->findByCode('154')->getBalance();
$expectedWip = 20 * $origCost;
assertEq($expectedWip, $wip, "WIP (154) increased by {$expectedWip}");

echo "\n=== Test 3: Insufficient stock should reject ===\n";
try {
    $svc->issueGoods($item->getId(), 999, 'sale', 'BAD-001', 'tester');
    echo "FAIL: Insufficient stock was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Insufficient stock rejected');
}

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);
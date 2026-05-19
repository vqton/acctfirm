<?php
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
$pdo->exec('DELETE FROM periodic_inventory');

$item = $itemRepo->findByCode('VT001');
if (!$item) { echo "FAIL: VT001 not found\n"; exit(1); }
$item->setStockQty(0);
$itemRepo->save($item);

echo "\n=== Test 1: Open period, record purchases, close with COGS ===\n";
// Initial stock: receive 100 units × 50,000
$svc->receiveGoods($item->getId(), 100, 50000, [], 'OPENING-STOCK', 'tester');
$svc->calculateAndUpdateUnitCost($item->getId());

// Purchases during period: 50 units × 52,000
$svc->receiveGoods($item->getId(), 50, 52000, [], 'PO-PRD-001', 'tester');

// Physical count at period end: 120 units at 51,000 avg
// COGS = (100×50,000 + 50×52,000) - 120×51,000 = 5,000,000 + 2,600,000 - 6,120,000 = 1,480,000
$result = $svc->closePeriodicInventory($item->getId(), 120, 51000, 'PERIODIC-Q1', 'tester');

$itemAfter = $itemRepo->findById($item->getId());
assertEq(120, $itemAfter->getStockQty(), 'Stock set to closing qty 120');

$cogs = $accountRepo->findByCode('632')->getBalance();
$inv = $accountRepo->findByCode('152')->getBalance();
assertEq(1480000, $cogs, "COGS = 1,480,000 (5M+2.6M-6.12M)");
assertEq(6120000, $inv, "Inventory = 6,120,000 (120×51,000)");
assertTrue($result['cogs'] > 0, 'Periodic record has COGS');

echo "\n=== Test 2: Second period with no purchases ===\n";
// Opening: 120×51,000=6,120,000, Purchases: 0, Closing: 100×50,000=5,000,000
// COGS = 6,120,000 + 0 - 5,000,000 = 1,120,000
$result2 = $svc->closePeriodicInventory($item->getId(), 100, 50000, 'PERIODIC-Q2', 'tester');

$cogs2 = $accountRepo->findByCode('632')->getBalance();
$inv2 = $accountRepo->findByCode('152')->getBalance();
assertEq(2600000, $cogs2, "COGS cumulative = 2,600,000 (1.48M + 1.12M)");
assertEq(5000000, $inv2, "Inventory = 5,000,000 (100×50,000)");

$itemAfter2 = $itemRepo->findById($item->getId());
assertEq(100, $itemAfter2->getStockQty(), 'Stock set to 100');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

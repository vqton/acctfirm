<?php
// Test: Customer Returns — restore inventory and reverse COGS
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

$item = $itemRepo->findByCode('VT001');
if (!$item) { echo "FAIL: VT001 not found\n"; exit(1); }
$item->setStockQty(0);
$itemRepo->save($item);

// Receive 100 units at 50,000
$svc->receiveGoods($item->getId(), 100, 50000, [], 'PO-RET-001', 'tester');
// Issue 30 units as sale (COGS recorded)
$svc->issueGoods($item->getId(), 30, 'sale', 'SALE-RET-001', 'tester');

echo "\n=== Test 1: Return goods from customer ===\n";
$result = $svc->returnFromCustomer($item->getId(), 10, 'RET-001', 'tester');

// Stock should be 80 (100 - 30 + 10)
$itemAfter = $itemRepo->findById($item->getId());
assertEq(80, $itemAfter->getStockQty(), 'Stock restored to 80 (100-30+10)');

// COGS reversed by 10×50K = 500K
$cogs = $accountRepo->findByCode('632')->getBalance();
assertEq(1000000, $cogs, 'COGS (632) = 1,000,000 (30×50K - 10×50K)');

// Inventory increased by 10×50K = 500K
$inv = $accountRepo->findByCode('152')->getBalance();
assertEq(4000000, $inv, 'Inventory (152) = 4,000,000 (70×50K remaining + 10×50K returned)');

// Ràng buộc: Trả hàng với item không tồn tại → từ chối
echo "\n=== Test 2: Return non-existent item ===\n";
try {
    $svc->returnFromCustomer('nonexistent', 1, 'BAD-RET', 'tester');
    echo "FAIL: Non-existent item not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Non-existent item rejected');
}

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

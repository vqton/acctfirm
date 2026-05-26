<?php
// Test: Trả lại hàng nhà cung cấp — điều chỉnh công nợ
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
$pdo->exec('DELETE FROM supplier_returns');
$pdo->exec('DELETE FROM transactions');

$item = $itemRepo->findByCode('VT001');
assertTrue($item !== null, 'Test item VT001 exists');
$item->setStockQty(0);
$itemRepo->save($item);

echo "\n=== Test 1: Return goods to supplier after receipt ===\n";
// Receive 50 units at 80,000
$svc->receiveGoods($item->getId(), 50, 80000, [], 'PO-001', 'tester');

// Return 10 units to supplier
$result = $svc->returnToSupplier($item->getId(), 10, 'RET-001', 'tester');

$itemAfter = $itemRepo->findById($item->getId());
assertEq(40, $itemAfter->getStockQty(), 'Stock decreased from 50 to 40');

$inv = $accountRepo->findByCode('152')->getBalance();
$ap = $accountRepo->findByCode('331')->getBalance();
// Original: 50 x 80,000 = 4,000,000
// Return: 10 x 80,000 = 800,000
// Remaining inv: 3,200,000
assertEq(3200000, $inv, 'Inventory (152) = 3,200,000 after return');
assertEq(3200000, $ap, 'AP (331) = 3,200,000 after return');

echo "\n=== Test 2: Return with multiple cost layers (average cost) ===\n";
$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM inventory_cost_layers');
$item->setStockQty(0);
$itemRepo->save($item);

// Receive: 50 x 80,000 (older layer)
$svc->receiveGoods($item->getId(), 50, 80000, [], 'PO-OLD', 'tester');
// Receive: 30 x 90,000 (newer layer)
$svc->receiveGoods($item->getId(), 30, 90000, [], 'PO-NEW', 'tester');

// Return 20 units at weighted average cost
// Avg = (50*80,000 + 30*90,000) / 80 = 6,700,000 / 80 = 83,750
// Return COGS = 20 * 83,750 = 1,675,000
// Remaining = 6,700,000 - 1,675,000 = 5,025,000
$result2 = $svc->returnToSupplier($item->getId(), 20, 'RET-002', 'tester');

$inv2 = $accountRepo->findByCode('152')->getBalance();
$expectedInv2 = 6700000 - (20 * (6700000 / 80));
assertEq($expectedInv2, $inv2, "Inventory = {$expectedInv2} after avg-cost return");

// Check stock qty
$itemAfter2 = $itemRepo->findById($item->getId());
assertEq(60, $itemAfter2->getStockQty(), 'Stock = 60 (50+30-20)');

// Ràng buộc: Trả hàng vượt quá tồn kho → từ chối
// Nếu fail → có thể trả hàng không có thực → sai số lượng tồn kho
echo "\n=== Test 3: Return exceeding stock rejected ===\n";
try {
    $svc->returnToSupplier($item->getId(), 999, 'RET-BAD', 'tester');
    echo "FAIL: Excessive return was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Return exceeding stock rejected');
}

echo "\n=== Test 4: Invalid item rejected ===\n";
try {
    $svc->returnToSupplier('nonexistent', 1, 'RET-BAD', 'tester');
    echo "FAIL: Invalid item was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Invalid item rejected');
}

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

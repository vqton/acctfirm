<?php
// Test: Xóa sổ hàng tồn kho — hàng hỏng, mất, kém chất lượng
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
$pdo->exec('DELETE FROM inventory_write_offs');

$item = $itemRepo->findByCode('VT001');
assertTrue($item !== null, 'Test item VT001 exists');
$item->setStockQty(0);
$itemRepo->save($item);

echo "\n=== Test 1: Write off damaged goods ===\n";
$svc->receiveGoods($item->getId(), 100, 50000, [], 'PO-001', 'tester');

$result = $svc->writeOffGoods($item->getId(), 10, 'damaged', '632', 'WO-001', 'tester', 'Damaged in transit');

$itemAfter = $itemRepo->findById($item->getId());
assertEq(90, $itemAfter->getStockQty(), 'Stock decreased from 100 to 90');

$cogs = $accountRepo->findByCode('632')->getBalance();
assertEq(500000, $cogs, 'COGS (632) increased by 10 x 50,000 = 500,000');

$inv = $accountRepo->findByCode('152')->getBalance();
assertEq(4500000, $inv, 'Inventory (152) = 90 x 50,000 = 4,500,000');

echo "\n=== Test 2: Write off to other expense account (811) ===\n";
$result2 = $svc->writeOffGoods($item->getId(), 5, 'lost', '811', 'WO-002', 'tester', 'Inventory lost');

$itemAfter2 = $itemRepo->findById($item->getId());
assertEq(85, $itemAfter2->getStockQty(), 'Stock decreased from 90 to 85');

$otherExpense = $accountRepo->findByCode('811')->getBalance();
assertEq(250000, $otherExpense, 'Other expense (811) = 5 x 50,000 = 250,000');

// Ràng buộc: Lý do xóa sổ không hợp lệ → từ chối
// Chỉ chấp nhận các reason: damaged, lost, obsolete, quality_issue
// Nếu fail → có thể xóa sổ với lý do tùy ý → khó kiểm soát
echo "\n=== Test 3: Invalid reason rejected ===\n";
try {
    $svc->writeOffGoods($item->getId(), 1, 'invalid_reason', '632', 'WO-BAD', 'tester');
    echo "FAIL: Invalid reason not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Invalid reason rejected');
}

echo "\n=== Test 4: Insufficient stock rejected ===\n";
try {
    $svc->writeOffGoods($item->getId(), 9999, 'damaged', '632', 'WO-BAD', 'tester');
    echo "FAIL: Insufficient stock not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Insufficient stock rejected');
}

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

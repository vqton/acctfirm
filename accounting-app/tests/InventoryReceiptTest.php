<?php
// Test: GoodsReceipt — inventory purchase with landed cost
// RED phase

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

// Reset balances + find test item
$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM inventory_cost_layers');
$item = $itemRepo->findByCode('VT001');
assertTrue($item !== null, 'Test item VT001 exists');
$item->setStockQty(0);
$itemRepo->save($item);

$oldQty = 0;

echo "\n=== Test 1: Simple goods receipt (no add-on costs) ===\n";
// Buy 10 units at 85,000 each
$receipt = $svc->receiveGoods($item->getId(), 10, 85000, [], 'Purchase order PO-001', 'tester');

// Check item stock_qty increased
$item2 = $itemRepo->findById($item->getId());
assertEq($oldQty + 10, $item2->getStockQty(), 'Stock quantity increased by 10');

// Check journal entry: Dr Inventory (152) 850,000 — Cr AP (331) 850,000
$txn = $txnRepo->findById($receipt['transaction_id']);
assertTrue($txn !== null, 'Transaction created');
assertEq('posted', $txn->getStatus(), 'Transaction posted');

$rawMat = $accountRepo->findByCode('152')->getBalance();
$ap = $accountRepo->findByCode('331')->getBalance();
assertEq(850000, $rawMat, 'Raw materials (152) increased by 850,000');
assertEq(850000, $ap, 'AP (331) increased by 850,000');

// Nghiệp vụ: Nhập kho có chi phí vận chuyển (landed cost) — cộng vào giá vốn hàng nhập
// 5 x 50,000 + 100,000 (freight) = 350,000
// Nếu fail → giá trị hàng tồn kho không bao gồm chi phí → sai giá vốn
echo "\n=== Test 2: Receipt with landed cost (freight) ===\n";
$receipt2 = $svc->receiveGoods($item->getId(), 5, 50000, [
    ['description' => 'Freight', 'amount' => 100000],
], 'PO-002', 'tester');

// Total inventory cost = 5 x 50,000 + 100,000 freight = 350,000
$item3 = $itemRepo->findById($item->getId());
assertEq($oldQty + 15, $item3->getStockQty(), 'Stock now up by 15 total');

$rawMat2 = $accountRepo->findByCode('152')->getBalance();
assertEq(850000 + 350000, $rawMat2, 'Raw materials includes 350,000 (5x50K + 100K freight)');

$item3 = $itemRepo->findById($item->getId());
$expectedPurchase = 10*85000 + 5*50000 + 100000;
$expectedQty = $oldQty + 15;

echo "\n=== Test 3: Unit cost updated via weighted average ===\n";
$svc->calculateAndUpdateUnitCost($item->getId());
$item4 = $itemRepo->findById($item->getId());
$expectedAvgCost = $expectedPurchase / $expectedQty;
$actualAvgCost = $item4->getPurchasePrice();
echo "  Expected avg: {$expectedAvgCost}, Actual: {$actualAvgCost}\n";
assertTrue(abs($expectedAvgCost - $actualAvgCost) < 10, 'Unit cost reflects weighted average');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);
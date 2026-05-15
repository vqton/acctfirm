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
$svc = new InventoryService($accountRepo, $txnRepo, $itemRepo, $warehouseRepo, $pdo);

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

// Receive goods
$svc->receiveGoods($item->getId(), 100, 50000, [], 'PO-PROMO-001', 'tester');

echo "\n=== Test 1: Issue promotional goods ===\n";
$result = $svc->issuePromotional($item->getId(), 20, 'PROMO-TET-2026', 'tester');

$itemAfter = $itemRepo->findById($item->getId());
assertEq(80, $itemAfter->getStockQty(), 'Stock decreased by 20 (100 → 80)');

// Dr 641 (selling expense) — Cr 152 = 20 × 50000
$sellingExp = $accountRepo->findByCode('641')->getBalance();
$inv = $accountRepo->findByCode('152')->getBalance();
$expected = 20 * 50000;
assertEq($expected, $sellingExp, "Selling expense (641) increased by {$expected}");
assertEq(80 * 50000, $inv, "Inventory (152) = 4,000,000");

echo "\n=== Test 2: Insufficient stock rejection ===\n";
try {
    $svc->issuePromotional($item->getId(), 999, 'BAD-PROMO', 'tester');
    echo "FAIL: Insufficient stock not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Insufficient stock rejected');
}

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

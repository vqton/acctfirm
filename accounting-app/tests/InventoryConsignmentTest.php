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
$pdo->exec('DELETE FROM inventory_consignment');

$item = $itemRepo->findByCode('VT001');
if (!$item) { echo "FAIL: VT001 not found\n"; exit(1); }
$item->setStockQty(0);
$itemRepo->save($item);

// Receive goods first
$svc->receiveGoods($item->getId(), 100, 50000, [], 'PO-CONSIGN-001', 'tester');
$svc->calculateAndUpdateUnitCost($item->getId());

echo "\n=== Test 1: Send goods on consignment ===\n";
$result = $svc->consignGoods($item->getId(), 30, 'Đại lý A', 'CONSIGN-001', 'tester');

// Stock decreased
$itemAfter = $itemRepo->findById($item->getId());
assertEq(70, $itemAfter->getStockQty(), 'Stock decreased by 30 (100 → 70)');

// Journal: Dr 157 — Cr 152
$consign = $accountRepo->findByCode('157')->getBalance();
$inv = $accountRepo->findByCode('152')->getBalance();
$expected = 30 * 50000;
assertEq($expected, $consign, "Consignment (157) increased by {$expected}");
assertEq(70 * 50000, $inv, "Inventory (152) decreased to 3,500,000");

echo "\n=== Test 2: Sell consigned goods ===\n";
$result2 = $svc->sellConsigned($result['consignment_id'], 20, 'SALE-CONSIGN-001', 'tester');

// COGS (632) increased
$cogs = $accountRepo->findByCode('632')->getBalance();
$expectedCogs = 20 * 50000;
assertEq($expectedCogs, $cogs, "COGS (632) increased by {$expectedCogs}");

// Consignment decreased
$consignAfter = $accountRepo->findByCode('157')->getBalance();
assertEq(10 * 50000, $consignAfter, 'Consignment (157) decreased to 500,000');

// Consignment record qty reduced
$stmt = $pdo->prepare("SELECT qty FROM inventory_consignment WHERE id = ?");
$stmt->execute([$result['consignment_id']]);
assertEq(10, (float)$stmt->fetchColumn(), 'Remaining consignment qty = 10');

echo "\n=== Test 3: Insufficient consignment stock ===\n";
try {
    $svc->sellConsigned($result['consignment_id'], 999, 'BAD', 'tester');
    echo "FAIL: Insufficient consignment not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Insufficient consignment rejected');
}

echo "\n=== Test 4: Return consigned goods to inventory ===\n";
$result3 = $svc->returnConsigned($result['consignment_id'], 10, 'RETURN-CONSIGN-001', 'tester');

// Stock increased back by 10
$itemFinal = $itemRepo->findById($item->getId());
assertEq(80, $itemFinal->getStockQty(), 'Stock increased by 10 after return (70 → 80)');

// Consignment (157) back to 0
$consignFinal = $accountRepo->findByCode('157')->getBalance();
assertEq(0, $consignFinal, 'Consignment (157) back to 0');

// Inventory (152) increased back
$invFinal = $accountRepo->findByCode('152')->getBalance();
assertEq(80 * 50000, $invFinal, 'Inventory (152) back to 4,000,000');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

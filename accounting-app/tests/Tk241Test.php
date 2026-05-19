<?php
// Test: TK 241 — issue goods for construction/asset repair
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

// Receive goods first
$svc->receiveGoods($item->getId(), 100, 50000, [], 'PO-CONST-001', 'tester');

echo "\n=== Test 1: Issue for construction (TK 241) ===\n";
$result = $svc->issueGoods($item->getId(), 30, 'construction', 'ISSUE-CONST-001', 'tester');

$itemAfter = $itemRepo->findById($item->getId());
assertEq(70, $itemAfter->getStockQty(), 'Stock decreased by 30 (100→70)');

// Dr 241 (CIP) — Cr 152 (inventory)
$cip = $accountRepo->findByCode('241')->getBalance();
$inv = $accountRepo->findByCode('152')->getBalance();
assertEq(1500000, $cip, 'CIP (241) increased by 1,500,000 (30×50K)');
assertEq(3500000, $inv, 'Inventory (152) = 3,500,000 (70×50K)');

echo "\n=== Test 2: Remaining stock still works for production ===\n";
$svc->issueGoods($item->getId(), 20, 'production', 'ISSUE-PROD-001', 'tester');

$wip = $accountRepo->findByCode('154')->getBalance();
$inv2 = $accountRepo->findByCode('152')->getBalance();
assertEq(1000000, $wip, 'WIP (154) = 1,000,000 (20×50K)');
assertEq(2500000, $inv2, 'Inventory (152) = 2,500,000 (50×50K)');

echo "\n=== Test 3: Invalid issue type rejected ===\n";
try {
    $svc->issueGoods($item->getId(), 1, 'invalid_type', 'BAD-ISSUE', 'tester');
    echo "FAIL: Invalid issue type not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Invalid issue type rejected');
}

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

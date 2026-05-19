<?php
// Test: TK 153 — tool items post to correct account
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
use Accounting\Domain\Model\Item;

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

// Create a tool-type item for testing
$item = $itemRepo->findByCode('TEST_TOOL');
if (!$item) {
    $item = new Item(uniqid('itm_'), 'TEST_TOOL', 'Máy khoan cầm tay (test)', 'tool', 'cai');
    $itemRepo->save($item);
}

assertEq('tool', $item->getItemType(), 'Item is tool type');
$item->setStockQty(0);
$itemRepo->save($item);

echo "\n=== Test 1: Receive tool → posts to TK 153 (not TK 152) ===\n";
$receipt = $svc->receiveGoods($item->getId(), 10, 100000, [], 'PO-TOOL-001', 'tester');

$acc153 = $accountRepo->findByCode('153')->getBalance();
$acc152 = $accountRepo->findByCode('152')->getBalance();
assertEq(1000000, $acc153, 'TK 153 = 1,000,000 (10×100K)');
assertEq(0, $acc152, 'TK 152 = 0 (tools NOT mapped to 152)');

echo "\n=== Test 2: Issue tool for production → Dr 154, Cr 153 ===\n";
$svc->issueGoods($item->getId(), 3, 'production', 'ISS-TOOL-001', 'tester');

$wip = $accountRepo->findByCode('154')->getBalance();
$acc153After = $accountRepo->findByCode('153')->getBalance();
assertEq(300000, $wip, 'WIP (154) = 300K (3×100K)');
assertEq(700000, $acc153After, 'TK 153 = 700K (7×100K)');

echo "\n=== Test 3: Issue tool for sale → Dr 632, Cr 153 ===\n";
$svc->issueGoods($item->getId(), 2, 'sale', 'ISS-TOOL-002', 'tester');

$cogs = $accountRepo->findByCode('632')->getBalance();
$acc153Final = $accountRepo->findByCode('153')->getBalance();
assertEq(200000, $cogs, 'COGS (632) = 200K (2×100K)');
assertEq(500000, $acc153Final, 'TK 153 = 500K (5×100K)');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

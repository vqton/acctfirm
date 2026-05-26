<?php
// Test: Giảm giá trị hàng tồn kho — dự phòng (TK 2294)
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
$pdo->exec('DELETE FROM inventory_impairment');

$item = $itemRepo->findByCode('VT001');
if (!$item) { echo "FAIL: VT001 not found\n"; exit(1); }

echo "\n=== Test 1: Record inventory impairment ===\n";
$result = $svc->recordImpairment($item->getId(), 2000000, 'IMPAIR-Q1-2026', 'NRV write-down for VT001', 'tester');

$cogs = $accountRepo->findByCode('632')->getBalance();
$provision = $accountRepo->findByCode('2294')->getBalance();
assertEq(2000000, $cogs, "COGS (632) increased by 2,000,000");
assertEq(-2000000, $provision, "Provision (2294) credit balance = -2,000,000 (contra-asset)");
assertTrue(isset($result['impairment_id']), 'Impairment record created');

echo "\n=== Test 2: Reverse impairment ===\n";
$result2 = $svc->reverseImpairment($result['impairment_id'], 500000, 'REVERSE-Q1-2026', 'tester');

$cogs2 = $accountRepo->findByCode('632')->getBalance();
$provision2 = $accountRepo->findByCode('2294')->getBalance();
assertEq(1500000, $cogs2, "COGS reduced to 1,500,000 after reversal");
assertEq(-1500000, $provision2, "Provision (2294) reduced to -1,500,000 (contra-asset)");

// Ràng buộc: Hoàn nhập dự phòng vượt quá số đã trích → từ chối
// Nếu fail → có thể hoàn nhập âm → dự phòng sai
echo "\n=== Test 3: Reverse exceeds provision ===\n";
try {
    $svc->reverseImpairment($result['impairment_id'], 9999999, 'BAD', 'tester');
    echo "FAIL: Reverse exceeding provision not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Reverse exceeding provision rejected');
}

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

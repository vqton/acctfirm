<?php
// Test: Auto WA recalc, batch tracking, FC purchase
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
$item->setPurchasePrice(0);
$itemRepo->save($item);

// =========================================
echo "\n=== 1. Auto WA: purchasePrice updated after receipt ===\n";
$svc->receiveGoods($item->getId(), 10, 100000, [], 'PO-WA-001', 'tester');
$after = $itemRepo->findById($item->getId());
assertEq(100000, $after->getPurchasePrice(), 'WA unit cost = 100,000 after 10×100K receipt');

// Second receipt at different price
$svc->receiveGoods($item->getId(), 10, 50000, [], 'PO-WA-002', 'tester');
$after2 = $itemRepo->findById($item->getId());
assertEq(75000, $after2->getPurchasePrice(), 'WA unit cost = 75,000 after (10×100K + 10×50K) / 20');

// =========================================
$pdo->exec('DELETE FROM inventory_cost_layers');
$item->setStockQty(0);
$item->setPurchasePrice(0);
$itemRepo->save($item);

echo "\n=== 2. Batch tracking: receive with batch code ===\n";
$svc->receiveGoods($item->getId(), 20, 80000, [], 'PO-BATCH-001', 'tester',
    'LOT-2026-001', '2026-12-31');

$stmt = $pdo->prepare("SELECT * FROM inventory_cost_layers WHERE item_id = ?");
$stmt->execute([$item->getId()]);
$layer = $stmt->fetch(PDO::FETCH_ASSOC);
assertTrue($layer !== false, 'Cost layer created');
assertEq('LOT-2026-001', $layer['batch_code'], 'Batch code saved on layer');
assertEq('2026-12-31', $layer['expiry_date'], 'Expiry date saved on layer');
assertEq(20, (float)$layer['qty'], 'Layer qty = 20');

echo "\n=== 3. Issue from specific batch ===\n";
$svc->receiveGoods($item->getId(), 10, 60000, [], 'PO-BATCH-002', 'tester',
    'LOT-2026-002', null);

$result = $svc->issueFromBatch($item->getId(), 5, 'LOT-2026-001', 'sale', 'ISS-BATCH-001', 'tester');

$stmt = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM inventory_cost_layers WHERE item_id = ? AND batch_code = ?");
$stmt->execute([$item->getId(), 'LOT-2026-001']);
$remaining = (float)$stmt->fetchColumn();
assertEq(15, $remaining, 'Batch LOT-2026-001 remaining = 15 (20-5)');

// COGS = 5 × 80,000 = 400,000
$cogs = $accountRepo->findByCode('632')->getBalance();
assertEq(400000, $cogs, 'COGS (632) = 400,000 (5×80K from batch)');

echo "\n=== 4. Issue from non-existent batch ===\n";
try {
    $svc->issueFromBatch($item->getId(), 1, 'NONEXISTENT', 'sale', 'ISS-BAD', 'tester');
    echo "FAIL: Non-existent batch not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Non-existent batch rejected');
}

// =========================================
$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM inventory_cost_layers');
$pdo->exec('DELETE FROM fc_transactions');
$pdo->exec('DELETE FROM exchange_rates');
$item->setStockQty(0);
$item->setPurchasePrice(0);
$itemRepo->save($item);

echo "\n=== 5. FC purchase: seed exchange rate ===\n";
$pdo->prepare("INSERT INTO exchange_rates (id, currency_code, currency_name, rate, rate_date) VALUES (?, ?, ?, ?, ?)")
    ->execute([uniqid('fx_'), 'USD', 'US Dollar', 25480, '2026-05-19']);

$rate = $svc->getExchangeRate('USD');
assertEq(25480, $rate, 'Exchange rate USD = 25,480');

echo "\n=== 6. FC purchase: receive goods in foreign currency ===\n";
$fcResult = $svc->receiveGoodsFC($item->getId(), 10, 100, [], 'USD', null, 'PO-FC-001', 'tester');

// 10 × 100 USD × 25,480 = 25,480,000 VND
$stock = $itemRepo->findById($item->getId());
assertEq(10, $stock->getStockQty(), 'Stock +10 FC receipt');

$inv = $accountRepo->findByCode('152')->getBalance();
assertEq(25480000, $inv, 'Inventory (152) = 25,480,000 VND (10×100 USD × 25,480)');

$ap = $accountRepo->findByCode('331')->getBalance();
assertEq(25480000, $ap, 'AP (331) = 25,480,000 VND');

// FC details recorded
$stmt = $pdo->prepare("SELECT * FROM fc_transactions WHERE transaction_id = ?");
$stmt->execute([$fcResult['transaction_id']]);
$fcRow = $stmt->fetch(PDO::FETCH_ASSOC);
assertTrue($fcRow !== false, 'FC transaction recorded');
assertEq('USD', $fcRow['currency_code'], 'FC currency = USD');
assertEq('25480.0000', (string)$fcRow['exchange_rate'], 'FC rate = 25,480');
assertEq('1000.00', (string)$fcRow['fc_amount'], 'FC amount = 1,000 (10 × 100)');

echo "\n=== 7. FC purchase: explicit rate overrides lookup ===\n";
$fcResult2 = $svc->receiveGoodsFC($item->getId(), 5, 200, [], 'USD', 25000, 'PO-FC-002', 'tester');

$inv2 = $accountRepo->findByCode('152')->getBalance();
assertEq(25480000 + 25000000, $inv2, 'Inventory + 25,000,000 (5×200 USD × 25,000)');

echo "\n=== 8. FC purchase: non-existent currency ===\n";
try {
    $svc->receiveGoodsFC($item->getId(), 1, 100, [], 'EUR', null, 'PO-FC-BAD', 'tester');
    echo "FAIL: Non-existent currency not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Non-existent currency rejected');
}

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

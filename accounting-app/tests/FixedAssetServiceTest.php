<?php
// Test: FixedAssetService — 4 depreciation methods + monthly posting

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Model\FixedAsset;
use Accounting\Domain\Service\FixedAssetService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Infrastructure\Persistence\PDOFixedAssetRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$faRepo = new PDOFixedAssetRepository($pdo);
$journal = new JournalService($accountRepo, $txnRepo, $pdo);
$svc = new FixedAssetService($faRepo, $accountRepo, $txnRepo, $journal, $pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if (abs((float)$a - (float)$b) > 1) { echo "FAIL: {$m} — expected {$b}, got {$a}\n"; $failed++; } else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if (!$c) { echo "FAIL: {$m}\n"; $failed++; } else echo "PASS: {$m}\n";
}
function results() { global $total, $failed;
    echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
    exit($failed > 0 ? 1 : 0);
}

// Ensure current period is open
$periodCode = date('Y-m');
$pdo->prepare("UPDATE accounting_periods SET status = 'open' WHERE period_code = ?")->execute([$periodCode]);

// Clean up test data
$pdo->exec('DELETE FROM fixed_asset_depreciation');
$pdo->exec("DELETE FROM fixed_assets WHERE id LIKE 'test-fa-%'");

// Create test assets
echo "\n=== Test 1: Straight-line depreciation ===\n";
$fa1 = new FixedAsset('test-fa-sl', 'TEST-SL', 'Test Straight Line', '2025-01-01',
    120000000, 'straight_line', 10, 0, 0, 0, 120000000,
    'tangible', 'May moc thiet bi');
$faRepo->save($fa1);
$dep = $svc->calculateMonthlyDepreciation($fa1);
assertEq(1000000, $dep, 'SL: 120M / 10 years / 12 months = 1,000,000/month');

echo "\n=== Test 2: Straight-line with salvage value ===\n";
$fa2 = new FixedAsset('test-fa-sl-sv', 'TEST-SL-SV', 'SL With Salvage', '2025-01-01',
    120000000, 'straight_line', 10, 20000000, 0, 0, 120000000,
    'tangible', 'Phuong tien van tai');
$faRepo->save($fa2);
$dep2 = $svc->calculateMonthlyDepreciation($fa2);
assertEq(833333, $dep2, 'SL: (120M - 20M) / 10 / 12 = 833,333/month');

echo "\n=== Test 3: Declining balance method ===\n";
$fa3 = new FixedAsset('test-fa-db', 'TEST-DB', 'Test Declining Balance', '2025-06-01',
    100000000, 'declining_balance', 5, 0, 0, 0, 100000000,
    'tangible', 'Thiet bi cong nghe');
$faRepo->save($fa3);
$dep3 = $svc->calculateMonthlyDepreciation($fa3);
// Year 1: rate = 1/5 * 2 = 40%, annual = 100M * 40% = 40M, monthly = 3,333,333
assertEq(3333333, $dep3, 'DB: Year 1 monthly = 100M * 40% / 12 = 3,333,333');

echo "\n=== Test 4: Sum-of-years digits method ===\n";
$fa4 = new FixedAsset('test-fa-soy', 'TEST-SOY', 'Test Sum of Years', '2025-01-01',
    60000000, 'sum_of_years', 5, 0, 0, 0, 60000000,
    'tangible', 'May moc');
$faRepo->save($fa4);
$dep4 = $svc->calculateMonthlyDepreciation($fa4);
// SYD = 5+4+3+2+1 = 15. Year 1 fraction = 5/15. Annual = 60M * 5/15 = 20M. Monthly = 1,666,667
assertEq(1666667, $dep4, 'SOY: Year 1 monthly = 60M * 5/15 / 12 = 1,666,667');

echo "\n=== Test 5: Production/output method ===\n";
$fa5 = new FixedAsset('test-fa-prod', 'TEST-PROD', 'Test Production', '2025-01-01',
    450000000, 'production', 0, 0, 0, 0, 450000000,
    'tangible', 'May ui dat', 2400000);
$faRepo->save($fa5);
$dep5 = $svc->calculateMonthlyDepreciation($fa5, 14000);
// Per unit = 450M / 2,400,000 = 187.5. Monthly = 14,000 * 187.5 = 2,625,000
assertEq(2625000, $dep5, 'PROD: 14,000 units * 187.5 = 2,625,000');

echo "\n=== Test 6: Fully depreciated asset returns 0 ===\n";
$fa6 = new FixedAsset('test-fa-full', 'TEST-FULL', 'Fully Depreciated', '2020-01-01',
    60000000, 'straight_line', 5, 0, 0, 60000000, 0,
    'tangible', 'May moc');
$faRepo->save($fa6);
$dep6 = $svc->calculateMonthlyDepreciation($fa6);
assertEq(0, $dep6, 'Fully depreciated: monthly = 0');

echo "\n=== Test 7: Schedule generation ===\n";
$schedule = $svc->calculateSchedule($fa1);
assertEq(10, count($schedule), 'SL 10-year schedule has 10 rows');
assertEq(12000000, $schedule[0]['yearly_depreciation'], 'Year 1 SL = 12M');
assertEq(0, $schedule[9]['net_book_value'], 'Year 10 NBV = 0');

echo "\n=== Test 8: Post monthly depreciation ===\n";
$results = $svc->postMonthlyDepreciation($periodCode, 'tester');
assertTrue(count($results) > 0, 'At least one depreciation entry posted');

// Verify FA1 accumulated depreciation updated
$fa1Reloaded = $faRepo->findById('test-fa-sl');
assertEq(1000000, $fa1Reloaded->getAccumulatedDepreciation(), 'FA1 accumulated = 1,000,000 after first post');
assertEq(119000000, $fa1Reloaded->getNetBookValue(), 'FA1 NBV = 119,000,000');

echo "\n=== Test 9: Depreciation history ===\n";
$history = $svc->getDepreciationHistory('test-fa-sl');
assertTrue(count($history) >= 1, 'History has 1+ entries');
assertEq(1000000, (float)$history[0]['depreciation_amount'], 'History entry shows 1,000,000');

echo "\n=== Test 10: Period report ===\n";
$report = $svc->getDepreciationByPeriod($periodCode);
assertTrue(count($report) >= 1, 'Period report has 1+ entries');

echo "\n=== Test 11: Asset not in_use returns 0 ===\n";
$fa7 = new FixedAsset('test-fa-idle', 'TEST-IDLE', 'Idle Asset', '2025-01-01',
    60000000, 'straight_line', 5, 0, 0, 0, 60000000,
    'tangible', 'May moc', null, 0, null, null, null, 'idle');
$faRepo->save($fa7);
$dep7 = $svc->calculateMonthlyDepreciation($fa7);
assertEq(0, $dep7, 'Idle asset: monthly = 0');

echo "\n=== Cleanup: remove test assets ===\n";
$pdo->exec('DELETE FROM fixed_asset_depreciation');
foreach (['test-fa-sl','test-fa-sl-sv','test-fa-db','test-fa-soy','test-fa-prod','test-fa-full','test-fa-idle'] as $id) {
    $pdo->prepare("DELETE FROM fixed_assets WHERE id = ?")->execute([$id]);
}

results();

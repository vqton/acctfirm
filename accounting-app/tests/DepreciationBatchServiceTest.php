<?php
// Test: DepreciationBatchService — Mẫu 06-TSCĐ batch generation, save/load, carry-forward

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Model\FixedAsset;
use Accounting\Domain\Service\FixedAssetService;
use Accounting\Domain\Service\DepreciationBatchService;
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
$faSvc = new FixedAssetService($faRepo, $accountRepo, $txnRepo, $journal, $pdo);
$batchSvc = new DepreciationBatchService($pdo, $faRepo);

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

// Helper: given a batch's row map (keyed by row_key), return the account total
function getRowTotal(array $rows, string $rowKey, string $account): float {
    foreach ($rows as $r) {
        if ($r['row_key'] === $rowKey || $r['row_key'] === $rowKey) {
            if (isset($r['accounts'][$account])) return (float)$r['accounts'][$account];
        }
    }
    return 0;
}

// Ensure current period is open
$periodCode = date('Y-m');
$pdo->prepare("UPDATE accounting_periods SET status = 'open' WHERE period_code = ?")->execute([$periodCode]);

// Clean up test data
$pdo->exec('DELETE FROM fixed_asset_depreciation');
$pdo->exec("DELETE FROM fixed_assets WHERE id LIKE 'test-batch-%'");
$pdo->exec("DELETE FROM fa_depreciation_batches WHERE id LIKE 'test-batch-%'");
$pdo->exec("DELETE FROM fa_department_accounts WHERE department_id LIKE 'test-dept-%'");

// === Seed department→account mappings ===
$pdo->exec("INSERT INTO fa_department_accounts (department_id, debit_account, created_at)
    VALUES ('test-dept-banhang', '641', NOW())");
$pdo->exec("INSERT INTO fa_department_accounts (department_id, debit_account, created_at)
    VALUES ('test-dept-qldn', '642', NOW())");
$pdo->exec("INSERT INTO fa_department_accounts (department_id, debit_account, created_at)
    VALUES ('test-dept-sanxuat', '627', NOW())");

// === Create test assets ===
// FA1: thuộc phòng bán hàng → khấu hao Nợ 641, 10 triệu/tháng
$fa1 = new FixedAsset(
    'test-batch-fa1', 'TB-FA1', 'Máy tính PB Hà Nội', '2025-06-01',
    1200000000, 'straight_line', 10, 0, 0, 0, 1200000000,
    'tangible', 'Máy móc thiết bị', null, 1200000000, 'test-dept-banhang'
);
$faRepo->save($fa1);

// FA2: thuộc phòng QLDN → khấu hao Nợ 642, 5 triệu/tháng
$fa2 = new FixedAsset(
    'test-batch-fa2', 'TB-FA2', 'Xe ô tô công ty', '2025-01-01',
    600000000, 'straight_line', 10, 0, 0, 0, 600000000,
    'tangible', 'Phương tiện vận tải', null, 600000000, 'test-dept-qldn'
);
$faRepo->save($fa2);

// FA3: không có phòng ban (mặc định) → khấu hao Nợ 627, 2 triệu/tháng
$fa3 = new FixedAsset(
    'test-batch-fa3', 'TB-FA3', 'Máy photocopy', '2025-03-01',
    240000000, 'straight_line', 10, 0, 0, 0, 240000000,
    'tangible', 'Máy móc thiết bị'
);
$faRepo->save($fa3);

// Seed depreciation records for current period
$period = date('Y-m');
// FA1: 10M/tháng
$pdo->prepare("INSERT INTO fixed_asset_depreciation (id, fixed_asset_id, period, depreciation_amount, accumulated_before, accumulated_after, net_book_before, net_book_after, created_at)
    VALUES (?, ?, ?, 10000000, 0, 10000000, 1200000000, 1190000000, NOW())")->execute([uniqid('dep_'), $fa1->getId(), $period]);
// FA2: 5M/tháng
$pdo->prepare("INSERT INTO fixed_asset_depreciation (id, fixed_asset_id, period, depreciation_amount, accumulated_before, accumulated_after, net_book_before, net_book_after, created_at)
    VALUES (?, ?, ?, 5000000, 0, 5000000, 600000000, 595000000, NOW())")->execute([uniqid('dep_'), $fa2->getId(), $period]);
// FA3: 2M/tháng
$pdo->prepare("INSERT INTO fixed_asset_depreciation (id, fixed_asset_id, period, depreciation_amount, accumulated_before, accumulated_after, net_book_before, net_book_after, created_at)
    VALUES (?, ?, ?, 2000000, 0, 2000000, 240000000, 238000000, NOW())")->execute([uniqid('dep_'), $fa3->getId(), $period]);

echo "\n=== DepreciationBatchService Tests ===\n";

// Test 1: generateReport returns correct structure
echo "\n--- Test 1: generateReport returns correct structure ---\n";
$report = $batchSvc->generateReport($period);
assertTrue(is_array($report), 'Report is array');
assertTrue(isset($report['period']), 'Report has period');
assertTrue(isset($report['rows']), 'Report has rows');
assertTrue(isset($report['asset_details']), 'Report has asset_details');
assertEq($report['period'], $period, 'Period matches');
assertEq($report['asset_count'], 3, '3 assets in period');

// Test 2: Current month total = sum of all depreciation
echo "\n--- Test 2: Current month total = 17M (10+5+2) ---\n";
$currentRow = $report['rows']['current'];
assertEq($currentRow['total'], 17000000, 'Total current month KH: 10M+5M+2M = 17M');

// Test 3: Account allocation — 641 (FA1), 642 (FA2), 627 (FA3)
echo "\n--- Test 3: Account allocation ---\n";
$acc = $currentRow['accounts'];
assertTrue(isset($acc['641']), 'Has 641 account');
assertTrue(isset($acc['642']), 'Has 642 account');
assertTrue(isset($acc['627']), 'Has 627 account');
assertEq($acc['641'], 10000000, '641 account = 10M (bán hàng)');
assertEq($acc['642'], 5000000, '642 account = 5M (QLDN)');
assertEq($acc['627'], 2000000, '627 account = 2M (mặc định)');

// Test 4: Carry-forward — first month should be 0
echo "\n--- Test 4: First month carry-forward = 0 ---\n";
assertEq($report['rows']['prev_month']['total'], 0, 'No previous batch → carry-forward = 0');

// Test 5: Increase = current (since prev was 0)
echo "\n--- Test 5: Increase = current total when no prev batch ---\n";
assertEq($report['rows']['increase']['total'], 17000000, 'Increase = 17M (current - 0)');
assertEq($report['rows']['decrease']['total'], 0, 'Decrease = 0');

// Test 6: saveBatch — persist to DB
echo "\n--- Test 6: saveBatch persists to DB ---\n";
$batchId = $batchSvc->saveBatch($period, 'test');
assertTrue($batchId !== null && $batchId !== '', 'Batch ID generated');
$loaded = $batchSvc->loadBatch($period);
assertTrue($loaded !== null, 'Batch can be loaded');
assertEq($loaded['total_company'], 17000000, 'Saved total_company = 17M');
assertEq($loaded['total_641'], 10000000, 'Saved total_641 = 10M');
assertEq($loaded['total_642'], 5000000, 'Saved total_642 = 5M');
assertEq($loaded['total_627'], 2000000, 'Saved total_627 = 2M');
assertEq($loaded['asset_count'], 3, 'Saved asset_count = 3');
assertEq($loaded['increase_amount'], 17000000, 'Saved increase = 17M');
assertEq($loaded['decrease_amount'], 0, 'Saved decrease = 0');

// Test 7: saveBatch — upsert (run again, should update)
echo "\n--- Test 7: saveBatch upsert (idempotent) ---\n";
$batchId2 = $batchSvc->saveBatch($period, 'test');
assertTrue($batchId2 !== '', 'Second save also returns ID');
$loaded2 = $batchSvc->loadBatch($period);
assertEq($loaded2['total_company'], 17000000, 'After upsert, total still 17M');
assertEq($loaded2['id'], $loaded['id'], 'Upsert keeps same ID (ON DUPLICATE KEY)');
// Actually ON DUPLICATE KEY UPDATE with VALUES() keeps existing auto-increment, but we use a generated id so the id stays the same only if we check for the same ID
// Let's just verify the ID exists and data matches

// Test 8: generateReport after saveBatch — carries forward
echo "\n--- Test 8: Second month carries forward ---\n";
$period2 = date('Y-m', strtotime('+1 month'));
// Ensure period2 is open
$pdo->prepare("INSERT IGNORE INTO accounting_periods (period_code, status) VALUES (?, 'open')")->execute([$period2]);

// Seed FA1 depreciation for period2
$pdo->prepare("INSERT INTO fixed_asset_depreciation (id, fixed_asset_id, period, depreciation_amount, accumulated_before, accumulated_after, net_book_before, net_book_after, created_at)
    VALUES (?, ?, ?, 10000000, 10000000, 20000000, 1190000000, 1180000000, NOW())")->execute([uniqid('dep_'), $fa1->getId(), $period2]);
// FA2 period2
$pdo->prepare("INSERT INTO fixed_asset_depreciation (id, fixed_asset_id, period, depreciation_amount, accumulated_before, accumulated_after, net_book_before, net_book_after, created_at)
    VALUES (?, ?, ?, 5000000, 5000000, 10000000, 595000000, 590000000, NOW())")->execute([uniqid('dep_'), $fa2->getId(), $period2]);
// FA3 period2
$pdo->prepare("INSERT INTO fixed_asset_depreciation (id, fixed_asset_id, period, depreciation_amount, accumulated_before, accumulated_after, net_book_before, net_book_after, created_at)
    VALUES (?, ?, ?, 2000000, 2000000, 4000000, 238000000, 236000000, NOW())")->execute([uniqid('dep_'), $fa3->getId(), $period2]);

$report2 = $batchSvc->generateReport($period2);
assertEq($report2['prev_period'], $period, 'Previous period detected');
assertEq($report2['rows']['prev_month']['total'], 17000000, 'Carry-forward = 17M from prev batch');
assertEq($report2['rows']['increase']['total'], 0, 'Increase = 0 (same assets, same amounts)');
assertEq($report2['rows']['decrease']['total'], 0, 'Decrease = 0 (same assets)');
assertEq($report2['rows']['current']['total'], 17000000, 'Current = 17M still');

// Test 9: Account allocation maps correctly with carry-forward
echo "\n--- Test 9: Account allocation with carry-forward ---\n";
$acc2 = $report2['rows']['current']['accounts'];
assertEq($acc2['641'], 10000000, 'Period2 641 = 10M');
assertEq($acc2['642'], 5000000, 'Period2 642 = 5M');
assertEq($acc2['627'], 2000000, 'Period2 627 = 2M');

// Test 10: generateReport — empty period (no depreciation records)
echo "\n--- Test 10: Empty period returns 0s ---\n";
$periodEmpty = '2025-01';
$emptyReport = $batchSvc->generateReport($periodEmpty);
assertEq($emptyReport['asset_count'], 0, 'Empty period: 0 assets');
assertEq($emptyReport['rows']['current']['total'], 0, 'Empty period: total = 0');
assertTrue(empty($emptyReport['asset_details']), 'Empty period: no details');

// Test 11: loadBatch — non-existent period returns null
echo "\n--- Test 11: loadBatch for non-existent period ---\n";
$nullBatch = $batchSvc->loadBatch('2099-12');
assertTrue($nullBatch === null, 'Non-existent period returns null');

// Test 12: Period rollover (Dec → Jan)
echo "\n--- Test 12: Period rollover Dec→Jan ---\n";
$decPeriod = '2025-12';
$janPeriod = '2026-01';
$decReport = $batchSvc->generateReport($decPeriod);
assertEq($decReport['period'], $decPeriod, 'Dec report period OK');
$janReport = $batchSvc->generateReport($janPeriod);
assertEq($janReport['prev_period'], $decPeriod, 'Jan prev_period = Dec');

// Test 13: Asset details contain all fields
echo "\n--- Test 13: Asset details structure ---\n";
$details = $report['asset_details'];
assertEq(count($details), 3, '3 asset details in report');
$first = $details[0];
assertTrue(isset($first['asset_code']), 'Detail has asset_code');
assertTrue(isset($first['asset_name']), 'Detail has asset_name');
assertTrue(isset($first['depreciation_amount']), 'Detail has depreciation_amount');
assertTrue(isset($first['original_cost']), 'Detail has original_cost');

// Test 14: Account codes returned correctly
echo "\n--- Test 14: Account codes list ---\n";
assertTrue(in_array('627', $report['accounts']), '627 in accounts list');
assertTrue(in_array('641', $report['accounts']), '641 in accounts list');
assertTrue(in_array('642', $report['accounts']), '642 in accounts list');

// Clean up
echo "\n--- Cleaning up test data ---\n";
$pdo->exec('DELETE FROM fixed_asset_depreciation');
$pdo->exec("DELETE FROM fixed_assets WHERE id LIKE 'test-batch-%'");
$pdo->exec("DELETE FROM fa_depreciation_batches WHERE id LIKE 'test-batch-%'");
$pdo->exec("DELETE FROM fa_depreciation_batches WHERE id LIKE 'fab_%'");
$pdo->exec("DELETE FROM fa_department_accounts WHERE department_id LIKE 'test-dept-%'");
$pdo->exec("DELETE FROM accounting_periods WHERE period_code = '$period2'");
$pdo->exec("DELETE FROM accounting_periods WHERE period_code = '$decPeriod'");
$pdo->exec("DELETE FROM accounting_periods WHERE period_code = '$janPeriod'");

results();

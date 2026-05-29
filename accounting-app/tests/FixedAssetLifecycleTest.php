<?php
// Test: FixedAssetService — Acquisition & Disposal lifecycle
// Nghiệp vụ: Ghi tăng TSCĐ (Nợ 211/Có 111/112/331/411/711) và thanh lý TSCĐ

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

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

$periodCode = date('Y-m');
$pdo->prepare("UPDATE accounting_periods SET status = 'open' WHERE period_code = ?")->execute([$periodCode]);

// Clean up test data
$pdo->exec("DELETE FROM fixed_assets WHERE id LIKE 'test-lifecycle-%'");
$pdo->exec("DELETE FROM transactions WHERE id LIKE 'jrn_%' AND description LIKE 'Ghi tang TSCD%'");
$pdo->exec("DELETE FROM transactions WHERE id LIKE 'jrn_%' AND description LIKE 'Thanh ly TSCD%'");

echo "\n=== Test 1: Acquisition — purchase_cash (Nợ 211 / Có 111) ===\n";
$result = $svc->recordAcquisition([
    'code' => 'TEST-MAY-TINH', 'name' => 'Máy tính Dell', 'original_cost' => 35000000,
    'useful_life' => 5, 'purchase_date' => date('Y-m-d'), 'fa_category' => 'tangible',
    'fa_type' => 'Thiet bi quan ly',
], 'purchase_cash', '111', 'tester');
assertTrue(!empty($result['transaction_id']), 'Acquisition transaction created');
assertTrue(!empty($result['fixed_asset_id']), 'FA record created');

$saved = $faRepo->findByCode('TEST-MAY-TINH');
assertTrue($saved !== null, 'FA found by code');
assertEq(35000000, $saved->getNetBookValue(), 'NBV = original cost after acquisition');
assertEq(0, $saved->getAccumulatedDepreciation(), 'Accum deprec = 0 after acquisition');

echo "\n=== Test 2: Acquisition — purchase_credit (Nợ 211 + Nợ 1332 / Có 331) ===\n";
$result2 = $svc->recordAcquisition([
    'code' => 'TEST-XE-TAI', 'name' => 'Xe tải Isuzu', 'original_cost' => 800000000,
    'useful_life' => 10, 'purchase_date' => date('Y-m-d'), 'fa_category' => 'tangible',
    'fa_type' => 'Phuong tien van tai',
], 'purchase_credit', '331', 'tester', 80000000);
assertTrue(!empty($result2['transaction_id']), 'Credit acquisition created');
$saved2 = $faRepo->findByCode('TEST-XE-TAI');
assertEq(800000000, $saved2->getOriginalCost(), 'NG = 800M');

echo "\n=== Test 3: Acquisition — capital_contribution (Nợ 211 / Có 4111) ===\n";
$result3 = $svc->recordAcquisition([
    'code' => 'TEST-GOP-VON', 'name' => 'Máy CNC do góp vốn', 'original_cost' => 500000000,
    'useful_life' => 8, 'purchase_date' => date('Y-m-d'), 'fa_category' => 'tangible',
    'fa_type' => 'May moc thiet bi',
], 'capital_contribution', '41111', 'tester');
assertTrue(!empty($result3['transaction_id']), 'Capital contribution acquisition created');
$saved3 = $faRepo->findByCode('TEST-GOP-VON');
assertEq(500000000, $saved3->getOriginalCost(), 'NG = 500M');

echo "\n=== Test 4: Acquisition — gift (Nợ 211 / Có 711) ===\n";
$result4 = $svc->recordAcquisition([
    'code' => 'TEST-TANG', 'name' => 'Máy photocopy được tặng', 'original_cost' => 45000000,
    'useful_life' => 5, 'purchase_date' => date('Y-m-d'), 'fa_category' => 'tangible',
    'fa_type' => 'Thiet bi quan ly',
], 'gift', '711', 'tester');
assertTrue(!empty($result4['transaction_id']), 'Gift acquisition created');

echo "\n=== Test 5: Acquisition — invalid type throws ===\n";
try {
    $svc->recordAcquisition(['code'=>'INVALID', 'name'=>'Invalid', 'original_cost'=>100000], 'invalid_type', '111', 'tester');
    assertTrue(false, 'Should have thrown');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Invalid type correctly rejected');
}

echo "\n=== Test 6: Acquisition — zero cost throws ===\n";
try {
    $svc->recordAcquisition(['code'=>'ZERO', 'name'=>'Zero Cost', 'original_cost'=>0], 'purchase_cash', '111', 'tester');
    assertTrue(false, 'Should have thrown');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Zero cost correctly rejected');
}

// Create an asset for disposal tests
echo "\n=== Test 7: Disposal — liquidation of fully depreciated FA ===\n";
$faDispose1 = new \Accounting\Domain\Model\FixedAsset(
    'test-lifecycle-full', 'TEST-THANHLY-FULL', 'TSCĐ khấu hao hết', '2020-01-01',
    100000000, 'straight_line', 5, 0, 0, 100000000, 0,
    'tangible', 'May moc');
$faRepo->save($faDispose1);

$result7 = $svc->recordDisposal('test-lifecycle-full', 'liquidation', 0, null, 0, null, date('Y-m-d'), 'tester');
assertTrue(!empty($result7['transaction_id']), 'Liquidation transaction created');
$sold7 = $faRepo->findById('test-lifecycle-full');
assertEq('liquidated', $sold7->getStatus(), 'FA status = liquidated');

echo "\n=== Test 8: Disposal — sale with proceeds (partially depreciated) ===\n";
// Tạo TSCĐ 120M, khấu hao 3 năm (36 tháng) trong 10 năm = 1M/tháng → 36M
$faDispose2 = new \Accounting\Domain\Model\FixedAsset(
    'test-lifecycle-sale', 'TEST-BAN', 'Máy bán lại', '2022-01-01',
    120000000, 'straight_line', 10, 0, 0, 36000000, 84000000,
    'tangible', 'May moc');
$faRepo->save($faDispose2);

// Bán với giá 55M (bao gồm VAT 10%) → tiền chưa VAT = 50M
// GTCL = 84M, Giá bán chưa VAT = 50M → Lỗ = 34M
$result8 = $svc->recordDisposal('test-lifecycle-sale', 'sale', 55000000, '111', 0, null, date('Y-m-d'), 'tester');
assertTrue(!empty($result8['transaction_id']), 'Sale transaction created');
assertEq(-34000000, $result8['gain_loss'], 'Loss = 50M - 84M = -34M');
$sold8 = $faRepo->findById('test-lifecycle-sale');
assertEq('liquidated', $sold8->getStatus(), 'FA status = liquidated');

echo "\n=== Test 9: Disposal — already disposed throws ===\n";
try {
    $svc->recordDisposal('test-lifecycle-full', 'liquidation', 0, null, 0, null, date('Y-m-d'), 'tester');
    assertTrue(false, 'Should have thrown');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Already disposed correctly rejected');
}

echo "\n=== Test 10: Disposal — liquidation with NBV > 0 ===\n";
$faDispose3 = new \Accounting\Domain\Model\FixedAsset(
    'test-lifecycle-nbv', 'TEST-NBV', 'TSCĐ thanh lý còn GTCL', '2024-01-01',
    60000000, 'straight_line', 5, 0, 0, 12000000, 48000000,
    'tangible', 'May moc');
$faRepo->save($faDispose3);

$result10 = $svc->recordDisposal('test-lifecycle-nbv', 'liquidation', 0, null, 5000000, '111', date('Y-m-d'), 'tester');
assertTrue(!empty($result10['transaction_id']), 'Liquidation with NBV > 0 created');
// Lỗ = NBV + costs = 48M + 5M = 53M
assertEq(-53000000, $result10['gain_loss'], 'Loss = 48M + 5M = 53M');

echo "\n=== Test 11: Acquisition — purchase_bank (Nợ 211 / Có 112) ===\n";
$result11 = $svc->recordAcquisition([
    'code' => 'TEST-BANK', 'name' => 'Máy in chuyển khoản', 'original_cost' => 15000000,
    'useful_life' => 3, 'purchase_date' => date('Y-m-d'), 'fa_category' => 'tangible',
    'fa_type' => 'Thiet bi quan ly',
], 'purchase_bank', '112', 'tester');
assertTrue(!empty($result11['transaction_id']), 'Bank acquisition created');
$saved11 = $faRepo->findByCode('TEST-BANK');
assertEq(15000000, $saved11->getOriginalCost(), 'NG = 15M');

echo "\n=== Test 12: Disposal — sale with gain ===\n";
// Tạo TSCĐ 100M, khấu hao 2 năm trong 5 năm = 2/5 * 100M = 40M
$faDispose4 = new \Accounting\Domain\Model\FixedAsset(
    'test-lifecycle-gain', 'TEST-GAIN', 'Máy bán lãi', '2023-01-01',
    100000000, 'straight_line', 5, 0, 0, 40000000, 60000000,
    'tangible', 'May moc');
$faRepo->save($faDispose4);

// Bán 88M (có VAT) → chưa VAT = 80M. GTCL = 60M. Lãi = 80M - 60M = 20M
$result12 = $svc->recordDisposal('test-lifecycle-gain', 'sale', 88000000, '112', 0, null, date('Y-m-d'), 'tester');
assertTrue(!empty($result12['transaction_id']), 'Sale with gain created');
assertEq(20000000, $result12['gain_loss'], 'Gain = 80M - 60M = 20M');

echo "\n=== Cleanup ===\n";
foreach (['TEST-MAY-TINH','TEST-XE-TAI','TEST-GOP-VON','TEST-TANG','TEST-BANK'] as $code) {
    $fa = $faRepo->findByCode($code);
    if ($fa) $faRepo->delete($fa->getId());
}
foreach (['test-lifecycle-full','test-lifecycle-sale','test-lifecycle-nbv','test-lifecycle-gain'] as $id) {
    $fa = $faRepo->findById($id);
    if ($fa) $faRepo->delete($fa->getId());
}

echo "\n=== Trial balance check ===\n";
$stmt = $pdo->query("
    SELECT SUM(CASE WHEN is_debit = 1 THEN amount ELSE 0 END) AS total_dr,
           SUM(CASE WHEN is_debit = 0 THEN amount ELSE 0 END) AS total_cr
    FROM ledger_entries le
    JOIN transactions t ON t.id = le.transaction_id
    WHERE t.description LIKE 'Ghi tang TSCD%' OR t.description LIKE 'Thanh ly TSCD%'
");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
assertEq($row['total_dr'], $row['total_cr'], 'Trial balance: Dr = Cr for all lifecycle transactions');

results();

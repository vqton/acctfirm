<?php
// Test: BC03 — Lưu chuyển tiền tệ
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Service\FsService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$fs = new FsService($pdo, $accountRepo);
$journal = new JournalService($accountRepo, $txnRepo, $pdo);

$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');
$pdo->exec('DELETE FROM fs_snapshots');

// Mở kỳ 2026-06 để JournalService có thể post
$pdo->exec("INSERT IGNORE INTO accounting_periods (period_code, start_date, end_date, status) VALUES ('2026-06', '2026-06-01', '2026-06-30', 'open')");
$pdo->exec("UPDATE accounting_periods SET status = 'open' WHERE period_code = '2026-06'");

// Seed prior-period snapshot cho BC03 2025
$priorData = json_encode([
    '01' => 10000000, '20' => 5000000, '30' => -2000000, '40' => 3000000,
    '50' => 6000000, '60' => 2000000, '70' => 8000000,
]);
$pdo->prepare("INSERT IGNORE INTO fs_snapshots (statement, period_code,period_end_date,data,created_by) VALUES ('BC03','2025','2025-12-31',?,'system')")->execute([$priorData]);

// Không seed BC01 prior snapshot — cash_begin trong test = 0
// (accounts.balance được reset, nên BC01 MS110 chỉ phản ánh thay đổi trong kỳ)

function assertFloatEq($expected, $actual, $msg, $tol = 1) {
    global $total, $failed;
    $total++;
    if (abs((float)$expected - (float)$actual) <= $tol) {
        echo "PASS: {$msg}\n";
    } else {
        echo "FAIL: {$msg} — expected {$expected}, got {$actual}\n";
        $failed++;
    }
}

echo "\n=== Test 1: BC 03 line items loaded ===\n";
$items = $fs->getLineItems('BC03');
assertTrue(count($items) >= 37, 'BC03 has 37+ line items');

echo "\n=== Test 2: BC 03 generation with zero balances ===\n";
$bc03 = $fs->generateBC03('2026');
assertTrue(count($bc03) >= 37, 'BC03 result has 37+ rows');

foreach ($bc03 as $r) {
    if ($r['ma_so'] === '20') $ms20 = $r['value'];
    if ($r['ma_so'] === '30') $ms30 = $r['value'];
    if ($r['ma_so'] === '40') $ms40 = $r['value'];
    if ($r['ma_so'] === '50') $ms50 = $r['value'];
    if ($r['ma_so'] === '60') $ms60 = $r['value'];
    if ($r['ma_so'] === '70') $ms70 = $r['value'];
}
assertFloatEq(0, $ms20 ?? 0, 'Operating cash flow (20) = 0');
assertFloatEq(0, $ms30 ?? 0, 'Investing cash flow (30) = 0');
assertFloatEq(0, $ms40 ?? 0, 'Financing cash flow (40) = 0');
assertFloatEq(0, $ms50 ?? 0, 'Net cash flow (50) = 0');
assertFloatEq(0, $ms60 ?? 0, 'Opening cash (60) = 0');
assertFloatEq(0, $ms70 ?? 0, 'Closing cash (70) = 0');

$errors = $fs->validateBC03($bc03);
assertTrue(count($errors) === 0, 'BC03 validation passes: ' . implode('; ', $errors));

echo "\n=== Test 3: BC 03 with revenue transaction ===\n";
$journal->postEntry('Sales revenue', 'C3-REV-001', [
    ['account_code' => '112', 'amount' => 50000000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 50000000, 'is_debit' => false],
], 'tester');

$journal->postEntry('Operating expense', 'C3-EXP-001', [
    ['account_code' => '642', 'amount' => 20000000, 'is_debit' => true],
    ['account_code' => '112', 'amount' => 20000000, 'is_debit' => false],
], 'tester');

$bc03b = $fs->generateBC03('2026');

$ms01 = 0; $ms20 = 0;
foreach ($bc03b as $r) {
    if ($r['ma_so'] === '01') $ms01 = $r['value'];
    if ($r['ma_so'] === '20') $ms20 = $r['value'];
}
assertTrue($ms01 > 0, 'Profit before tax (01) > 0');
assertTrue($ms20 !== 0, 'Operating cash flow (20) is non-zero');

echo "\n=== Test 4: BC 03 investment (FA purchase) ===\n";
$journal->postEntry('Purchase FA', 'C3-FA-001', [
    ['account_code' => '211', 'amount' => 100000000, 'is_debit' => true],
    ['account_code' => '112', 'amount' => 100000000, 'is_debit' => false],
], 'tester');

$bc03c = $fs->generateBC03('2026');

$ms21 = 0; $ms30 = 0;
foreach ($bc03c as $r) {
    if ($r['ma_so'] === '21') $ms21 = $r['value'];
    if ($r['ma_so'] === '30') $ms30 = $r['value'];
}
assertFloatEq(-100000000, $ms21, 'FA purchase (21) = -100M');
assertFloatEq(-100000000, $ms30, 'Investing flow (30) = -100M');

echo "\n=== Test 5: BC 03 loan transaction ===\n";
$journal->postEntry('Bank loan', 'C3-LOAN-001', [
    ['account_code' => '112', 'amount' => 200000000, 'is_debit' => true],
    ['account_code' => '3411', 'amount' => 200000000, 'is_debit' => false],
], 'tester');

$bc03d = $fs->generateBC03('2026');

$ms33 = 0; $ms40 = 0;
foreach ($bc03d as $r) {
    if ($r['ma_so'] === '33') $ms33 = $r['value'];
    if ($r['ma_so'] === '40') $ms40 = $r['value'];
}
assertFloatEq(200000000, $ms33, 'Loan proceeds (33) = 200M');
assertFloatEq(200000000, $ms40, 'Financing flow (40) = 200M');

// Ràng buộc liên báo cáo: Tiền cuối kỳ trên BC03 (MS 70) = Tiền mặt trên BC01 (MS 110)
// Nếu fail → 2 báo cáo mâu thuẫn → cơ quan thuế không chấp nhận
echo "\n=== Test 6: BC 03 closing cash matches BC 01 ===\n";
$bc01 = $fs->generateBC01('2026');
$bc01Cash = 0;
foreach ($bc01 as $r) {
    if ($r['ma_so'] === '110') $bc01Cash = $r['value'];
}
$bc03ms70 = 0;
foreach ($bc03d as $r) {
    if ($r['ma_so'] === '70') $bc03ms70 = $r['value'];
}
// Cash = 50M (sales) - 20M (expense) - 100M (FA) + 200M (loan) = 130M
assertFloatEq(130000000, $bc01Cash, 'BC01 cash (110) = 130M');
assertFloatEq($bc01Cash, $bc03ms70, 'BC03 closing cash (70) matches BC01 cash (110)');

// Ràng buộc: Công thức BC03 — 50 (Lưu chuyển thuần) = 20 + 30 + 40
// 70 (Tiền cuối kỳ) = 50 + 60 (Tiền đầu kỳ) + 61 (Ảnh hưởng tỷ giá)
// Nếu fail → BC03 sai cấu trúc → không đúng mẫu quy định
echo "\n=== Test 7: BC 03 summary formula ===\n";
$ms20 = 0; $ms30 = 0; $ms40 = 0; $ms50 = 0; $ms60 = 0; $ms61 = 0; $ms70 = 0;
foreach ($bc03d as $r) {
    if ($r['ma_so'] === '20') $ms20 = $r['value'];
    if ($r['ma_so'] === '30') $ms30 = $r['value'];
    if ($r['ma_so'] === '40') $ms40 = $r['value'];
    if ($r['ma_so'] === '50') $ms50 = $r['value'];
    if ($r['ma_so'] === '60') $ms60 = $r['value'];
    if ($r['ma_so'] === '61') $ms61 = $r['value'];
    if ($r['ma_so'] === '70') $ms70 = $r['value'];
}
assertFloatEq($ms20 + $ms30 + $ms40, $ms50, '50 = 20+30+40');
assertFloatEq($ms50 + $ms60 + $ms61, $ms70, '70 = 50+60+61');

echo "\n=== Test 8: BC 03 snapshot saved ===\n";
$snapshots = $pdo->query("SELECT COUNT(*) FROM fs_snapshots WHERE statement = 'BC03'")->fetchColumn();
assertTrue($snapshots >= 1, 'BC03 snapshot saved');

echo "\n=== Test 9: Prior-period values returned ===\n";
$prior = $fs->getPriorPeriodValues('BC03', '2026');
assertTrue($prior !== null, 'Prior-period snapshot exists');
assertTrue(isset($prior['20']), 'Prior-period MS 20 exists');
assertTrue(abs($prior['20'] - 5000000) < 1, 'Prior-period MS 20 = 5M');

echo "\n=== Test 10: Prior-period period code parsing ===\n";
// Seed monthly snapshot, then look up prior
$monthData = json_encode(['01' => 5000000, '20' => 3000000]);
$pdo->prepare("INSERT IGNORE INTO fs_snapshots (statement, period_code,period_end_date,data,created_by) VALUES ('BC03','2026-05','2026-05-31',?,'system')")->execute([$monthData]);
$priorMonth = $fs->getPriorPeriodValues('BC03', '2026-06');
assertTrue($priorMonth !== null, 'Prior-period month format resolves');
assertTrue(abs(($priorMonth['20'] ?? 0) - 3000000) < 1, 'Prior-period month MS 20 = 3M');

// Quarter format
$qData = json_encode(['20' => 4000000]);
$pdo->prepare("INSERT IGNORE INTO fs_snapshots (statement, period_code,period_end_date,data,created_by) VALUES ('BC03','2026-Q1','2026-03-31',?,'system')")->execute([$qData]);
$priorQ = $fs->getPriorPeriodValues('BC03', '2026-Q2');
assertTrue($priorQ !== null, 'Prior-period quarter format resolves');
assertTrue(abs(($priorQ['20'] ?? 0) - 4000000) < 1, 'Prior-period Q MS 20 = 4M');

// Year boundary: 2026-01 -> prior = 2025-12
$decData = json_encode(['20' => 2000000]);
$pdo->prepare("INSERT IGNORE INTO fs_snapshots (statement, period_code,period_end_date,data,created_by) VALUES ('BC03','2025-12','2025-12-31',?,'system')")->execute([$decData]);
$priorJan = $fs->getPriorPeriodValues('BC03', '2026-01');
assertTrue($priorJan !== null, 'Prior-period year boundary (Jan) resolves');
assertTrue(abs(($priorJan['20'] ?? 0) - 2000000) < 1, 'Prior-period Jan MS 20 = 2M (Dec prior)');

// ════════════════════════════════════════════════════════
// BC03 — PHƯƠNG PHÁP TRỰC TIẾP (Direct Method)
// ════════════════════════════════════════════════════════

echo "\n=== Test 11: BC03 direct — line items loaded ===\n";
$directItems = $fs->getLineItems('BC03D');
assertTrue(count($directItems) >= 20, 'BC03D has 20+ line items');

echo "\n=== Test 12: BC03 direct — zero balances ===\n";
$bc03d0 = $fs->generateBC03Direct('2026');
assertTrue(count($bc03d0) >= 20, 'BC03_DIRECT result has 20+ rows');

echo "\n=== Test 13: BC03 direct — classify transactions ===\n";
// The journal entries from tests 3-5 should produce:
// Dr 112 50M / Cr 511 50M → thu tiền từ KH (MS 01)
// Dr 642 20M / Cr 112 20M → chi khác HĐKD (MS 07, since 642 unclassified)
// Dr 211 100M / Cr 112 100M → chi mua TSCĐ (MS 21)
// Dr 112 200M / Cr 3411 200M → thu từ đi vay (MS 33)
$ms01 = 0; $ms07 = 0; $ms10 = 0;
$ms21 = 0; $ms30 = 0;
$ms33 = 0; $ms40 = 0;
$ms50 = 0; $ms60 = 0; $ms70 = 0;
foreach ($bc03d0 as $r) {
    $ms = $r['ma_so'];
    if ($ms === '01') $ms01 = $r['value'];
    if ($ms === '07') $ms07 = $r['value'];
    if ($ms === '10') $ms10 = $r['value'];
    if ($ms === '21') $ms21 = $r['value'];
    if ($ms === '30') $ms30 = $r['value'];
    if ($ms === '33') $ms33 = $r['value'];
    if ($ms === '40') $ms40 = $r['value'];
    if ($ms === '50') $ms50 = $r['value'];
    if ($ms === '60') $ms60 = $r['value'];
    if ($ms === '70') $ms70 = $r['value'];
}
assertFloatEq(50000000, $ms01, 'Direct MS 01 (thu KH) = 50M');
assertFloatEq(-20000000, $ms07, 'Direct MS 07 (chi khác) = -20M');
assertFloatEq(-100000000, $ms21, 'Direct MS 21 (mua TSCĐ) = -100M');
assertFloatEq(200000000, $ms33, 'Direct MS 33 (thu vay) = 200M');
assertTrue($ms10 > 0, 'Direct MS 10 (HĐKD thuần) > 0');
assertTrue($ms30 < 0, 'Direct MS 30 (HĐĐT thuần) < 0');
assertTrue($ms40 > 0, 'Direct MS 40 (HĐTC thuần) > 0');

echo "\n=== Test 14: BC03 direct — closing cash = indirect closing cash ===\n";
// Direct MS 70 should equal indirect MS 70 (both should be 130M + cash_begin)
$indirectMs70 = 0;
foreach ($bc03d as $r) {
    if ($r['ma_so'] === '70') $indirectMs70 = $r['value'];
}
assertFloatEq($indirectMs70, $ms70, 'Direct MS 70 = Indirect MS 70');

echo "\n=== Test 15: BC03 direct — MS 70 = MS 50 + MS 60 ===\n";
assertFloatEq($ms50 + $ms60, $ms70, 'Direct 70 = 50+60');

echo "\n=== Test 16: BC03 direct — snapshot saved ===\n";
$snapDir = $pdo->query("SELECT COUNT(*) FROM fs_snapshots WHERE statement = 'BC03D'")->fetchColumn();
assertTrue($snapDir >= 1, 'BC03_DIRECT snapshot saved');

results();

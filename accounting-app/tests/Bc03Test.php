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

results();

<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\FsService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Repository\PDOAccountRepository;
use Accounting\Infrastructure\Repository\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$fs = new FsService($pdo, $accountRepo);
$journal = new JournalService($accountRepo, $txnRepo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>1){echo"FAIL: {$m} expected {$b} got {$a}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');
$pdo->exec('DELETE FROM fs_snapshots');

echo "\n=== Test 1: BC 01 line items loaded ===\n";
$items = $fs->getLineItems('BC01');
assertTrue(count($items) > 80, 'BC01 has 80+ line items');

$itemsBC02 = $fs->getLineItems('BC02');
assertTrue(count($itemsBC02) >= 18, 'BC02 has 18+ line items');

echo "\n=== Test 2: BC 01 generation with zero balances ===\n";
$bc01 = $fs->generateBC01('2026');
assertTrue(count($bc01) >= 80, 'BC01 result has 80+ rows');
// Find total assets and total liabilities+equity
$totalAssets = 0;
$totalEq = 0;
foreach ($bc01 as $r) {
    if ($r['ma_so'] === '280') $totalAssets = $r['value'];
    if ($r['ma_so'] === '440') $totalEq = $r['value'];
}
assertEq(0, $totalAssets, 'Total assets = 0 (zero balances)');
assertEq(0, $totalEq, 'Total equity = 0 (zero balances)');

$errors = $fs->validateBC01($bc01);
assertTrue(count($errors) === 0, 'BC01 validation passes: ' . implode('; ', $errors));

echo "\n=== Test 3: BC 02 generation with zero balances ===\n";
$bc02 = $fs->generateBC02('2026');
$total60 = 0;
foreach ($bc02 as $r) {
    if ($r['ma_so'] === '60') $total60 = $r['value'];
}
assertEq(0, $total60, 'Net profit = 0 (zero balances)');

$errors2 = $fs->validateBC02($bc02);
assertTrue(count($errors2) === 0, 'BC02 validation passes');

echo "\n=== Test 4: BC 01 with transactions ===\n";
$journal->postEntry('Test revenue', 'FS-REV-001', [
    ['account_code' => '112', 'amount' => 10000000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 10000000, 'is_debit' => false],
], 'tester');

$journal->postEntry('Test expense', 'FS-EXP-001', [
    ['account_code' => '642', 'amount' => 3000000, 'is_debit' => true],
    ['account_code' => '112', 'amount' => 3000000, 'is_debit' => false],
], 'tester');

// Run closing entries so BC 01 shows retained earnings
$periodSvc = new Accounting\Domain\Service\PeriodService($pdo, $accountRepo, $txnRepo);
$periodSvc->executeClosingEntries('tester');

$bc01b = $fs->generateBC01('2026');

$cashVal = 0;
foreach ($bc01b as $r) {
    if ($r['ma_so'] === '111') $cashVal = $r['value'];
    if ($r['ma_so'] === '280') $totalAssets = $r['value'];
    if ($r['ma_so'] === '440') $totalEq = $r['value'];
}
assertTrue($cashVal > 0, 'Cash (111) has balance');
assertEq($totalAssets, $totalEq, 'Assets = Liabilities+Equity');

echo "\n=== Test 5: BC 02 with transactions ===\n";
// Create fresh transactions for BC 02 test (accounts were zeroed by closing entries in test 4)
$journal->postEntry('Revenue test', 'FS2-REV-001', [
    ['account_code' => '112', 'amount' => 15000000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 15000000, 'is_debit' => false],
], 'tester');
$journal->postEntry('Expense test', 'FS2-EXP-001', [
    ['account_code' => '642', 'amount' => 5000000, 'is_debit' => true],
    ['account_code' => '112', 'amount' => 5000000, 'is_debit' => false],
], 'tester');

$bc02b = $fs->generateBC02('2026');

$rev = 0; $exp = 0; $profit = 0;
foreach ($bc02b as $r) {
    if ($r['ma_so'] === '01') $rev = $r['value'];
    if ($r['ma_so'] === '26') $exp = $r['value'];
    if ($r['ma_so'] === '60') $profit = $r['value'];
}
assertEq(15000000, $rev, 'Revenue (01) = 15,000,000');
assertEq(5000000, $exp, 'Admin expense (26) = 5,000,000');
assertEq(10000000, $profit, 'Net profit (60) = 10,000,000');

$errors3 = $fs->validateBC02($bc02b);
assertTrue(count($errors3) === 0, 'BC02 validation passes');

echo "\n=== Test 6: Prior period values ===\n";
// Generate prior period snapshot (captures current balances under period 2025)
$priorBc01 = $fs->generateBC01('2025');
$prior = $fs->getPriorPeriodValues('BC01', '2026');
assertTrue($prior !== null, 'Prior period exists (2025 snapshot)');
assertTrue(isset($prior['280']), 'Prior period has total assets');
// Verify that the snapshot stored non-zero values (from test 4 + test 5 transactions)
assertTrue($prior['280'] > 0, 'Prior period total assets > 0 (captures current balances)');

echo "\n=== Test 7: Snapshot persistence ===\n";
$snapshots = $pdo->query("SELECT COUNT(*) FROM fs_snapshots WHERE statement = 'BC01'")->fetchColumn();
assertTrue($snapshots >= 1, 'BC01 snapshot saved');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

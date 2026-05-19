<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\GlService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$gl = new GlService($pdo, $accountRepo);
$journal = new JournalService($accountRepo, $txnRepo, $pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>1){echo"FAIL: {$m} expected {$b} got {$a}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

// Ensure current period is open for posting test transactions
$pdo->prepare("UPDATE accounting_periods SET status = 'open' WHERE period_code = ?")->execute([date('Y-m')]);

$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');

// Clean up monthly test data if it exists from previous runs
$pdo->exec("DELETE FROM ledger_entries WHERE transaction_id IN ('txn-monthly-1','txn-monthly-2')");
$pdo->exec("DELETE FROM transactions WHERE id IN ('txn-monthly-1','txn-monthly-2')");

echo "\n=== Test 1: GL account list ===\n";
$accounts = $gl->getAccounts();
assertTrue(count($accounts) > 50, '50+ non-control accounts returned');

echo "\n=== Test 2: GL with zero transactions ===\n";
$result = $gl->getGeneralLedger('111');
assertEq('111', $result['account_code'], 'Account code correct');
assertEq(0, $result['opening_balance'], 'Opening balance = 0');
assertEq(0, $result['closing_balance'], 'Closing balance = 0');

echo "\n=== Test 3: GL with transactions ===\n";
$journal->postEntry('Test sale', 'GL-001', [
    ['account_code' => '111', 'amount' => 10000000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 10000000, 'is_debit' => false],
], 'tester');

$journal->postEntry('Test expense', 'GL-002', [
    ['account_code' => '642', 'amount' => 3000000, 'is_debit' => true],
    ['account_code' => '111', 'amount' => 3000000, 'is_debit' => false],
], 'tester');

$r = $gl->getGeneralLedger('111');
assertEq(7000000, $r['closing_balance'], '111 closing = 10M - 3M = 7M');
assertTrue(count($r['entries']) >= 2, 'At least 2 entries');
assertEq(10000000, $r['entries'][0]['debit'], 'First entry: Dr 10M');
assertEq(3000000, $r['entries'][1]['credit'], 'Second entry: Cr 3M');

echo "\n=== Test 4: Contra account shown ===\n";
assertTrue(strlen($r['entries'][0]['contra_account']) > 0, 'Contra account shown');

echo "\n=== Test 5: Running balance correct ===\n";
$result2 = $gl->getGeneralLedger('511');
assertTrue($result2['closing_balance'] > 0, 'Revenue (511) has credit balance');

echo "\n=== Test 6: Filtered by date ===\n";
$r2 = $gl->getGeneralLedger('111', '2026-01-01', '2026-12-31');
assertEq(7000000, $r2['closing_balance'], 'Filtered by year: closing = 7M');

echo "\n=== Test 7: Monthly ledger (S05-DN) — empty account ===\n";
$monthly = $gl->getMonthlyLedger('111');
assertEq('monthly', $monthly['mode'], 'Monthly mode indicator');
assertTrue(count($monthly['entries']) >= 12, '12 months returned for full year');

echo "\n=== Test 8: Monthly ledger with transactions in different months ===\n";
// Insert controlled-dated transactions
$pdo->prepare("INSERT INTO transactions (id, date, description, reference, status, created_by, created_at) VALUES (?, ?, ?, ?, 'posted', 'tester', ?)")->execute([
    'txn-monthly-1', '2026-01-15 10:00:00', 'January sale', 'GL-M01', '2026-01-15 10:00:00'
]);
$pdo->prepare("INSERT INTO ledger_entries (id, transaction_id, account_id, amount, is_debit) VALUES (?, ?, ?, ?, ?)")->execute([
    'le-m1-111', 'txn-monthly-1', (new PDOAccountRepository($pdo))->findByCode('111')->getId(), 5000000, 1
]);
$pdo->prepare("INSERT INTO ledger_entries (id, transaction_id, account_id, amount, is_debit) VALUES (?, ?, ?, ?, ?)")->execute([
    'le-m1-511', 'txn-monthly-1', (new PDOAccountRepository($pdo))->findByCode('511')->getId(), 5000000, 0
]);

$pdo->prepare("INSERT INTO transactions (id, date, description, reference, status, created_by, created_at) VALUES (?, ?, ?, ?, 'posted', 'tester', ?)")->execute([
    'txn-monthly-2', '2026-02-20 14:00:00', 'February expense', 'GL-M02', '2026-02-20 14:00:00'
]);
$pdo->prepare("INSERT INTO ledger_entries (id, transaction_id, account_id, amount, is_debit) VALUES (?, ?, ?, ?, ?)")->execute([
    'le-m2-642', 'txn-monthly-2', (new PDOAccountRepository($pdo))->findByCode('642')->getId(), 2000000, 1
]);
$pdo->prepare("INSERT INTO ledger_entries (id, transaction_id, account_id, amount, is_debit) VALUES (?, ?, ?, ?, ?)")->execute([
    'le-m2-111', 'txn-monthly-2', (new PDOAccountRepository($pdo))->findByCode('111')->getId(), 2000000, 0
]);

$monthly111 = $gl->getMonthlyLedger('111', '2026-01-01', '2026-12-31');
$janEntry = null;
$febEntry = null;
foreach ($monthly111['entries'] as $e) {
    if ($e['period'] === '2026-01') $janEntry = $e;
    if ($e['period'] === '2026-02') $febEntry = $e;
}

assertTrue($janEntry !== null, 'January entry exists');
assertEq(5000000, $janEntry['total_debit'], 'Jan 111 Dr = 5M');
assertEq(0, $janEntry['total_credit'], 'Jan 111 Cr = 0');
assertEq(5000000, $janEntry['closing_balance'], 'Jan 111 closing = 5M');

assertTrue($febEntry !== null, 'February entry exists');
assertEq(0, $febEntry['total_debit'], 'Feb 111 Dr = 0');
assertEq(2000000, $febEntry['total_credit'], 'Feb 111 Cr = 2M');
assertEq(3000000, $febEntry['closing_balance'], 'Feb 111 closing = 5M - 2M = 3M');

echo "\n=== Test 9: Monthly contra account detail ===\n";
assertTrue(count($janEntry['contra_debit_items']) > 0, 'Jan has contra items');
assertEq('511', $janEntry['contra_debit_items'][0]['contra_account_code'], 'Contra account is 511');
assertEq(5000000, $janEntry['contra_debit_items'][0]['amount'], 'Contra amount = 5M');

echo "\n=== Test 10: Monthly ledger for revenue account ===\n";
$monthly511 = $gl->getMonthlyLedger('511', '2026-01-01', '2026-12-31');
$jan511 = null;
foreach ($monthly511['entries'] as $e) {
    if ($e['period'] === '2026-01') $jan511 = $e;
}
assertTrue($jan511 !== null, 'Jan 511 entry exists');
assertEq(0, $jan511['total_debit'], 'Jan 511 Dr = 0');
assertEq(5000000, $jan511['total_credit'], 'Jan 511 Cr = 5M (revenue = credit normal)');

echo "\n=== Trial balance after GL ===\n";
$all = $accountRepo->findAll();
$totalDr = 0; $totalCr = 0;
foreach ($all as $a) {
    $bal = $a->getBalance();
    if (abs($bal) < 1) continue;
    if (in_array($a->getType(), ['asset', 'expense'])) { $totalDr += $bal; }
    else { $totalCr += $bal; }
}
assertEq(round($totalDr, 0), round($totalCr, 0), 'Dr = Cr');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

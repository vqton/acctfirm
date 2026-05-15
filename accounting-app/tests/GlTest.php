<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\GlService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Repository\PDOAccountRepository;
use Accounting\Infrastructure\Repository\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$gl = new GlService($pdo, $accountRepo);
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

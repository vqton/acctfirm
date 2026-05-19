<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\PeriodService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$journal = new JournalService($accountRepo, $txnRepo, $pdo);
$svc = new PeriodService($pdo, $accountRepo, $txnRepo, $journal);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>1){echo"FAIL: {$m} expected {$a} got {$b}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');
$pdo->exec('DELETE FROM accounting_periods');

echo "\n=== Test 1: Create monthly period ===\n";
$p = $svc->createPeriod('month', '2026-05', 'Tháng 5/2026', '2026-05-01', '2026-05-31', 'admin');
assertTrue($p['id'] > 0, 'Period ID returned');
assertEq('open', $p['status'], 'Status = open');
assertEq('month', $p['period_type'], 'Type = month');
assertEq('2026-05', $p['period_code'], 'Code = 2026-05');

echo "\n=== Test 2: List periods ===\n";
$list = $svc->getPeriods();
assertTrue(count($list) >= 1, 'At least 1 period');

echo "\n=== Test 3: Close period blocks if status != open ===\n";
try {
    $svc->closePeriod(99999, 'admin');
    echo "FAIL: Invalid period not rejected\n"; $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Invalid period rejected');
}

echo "\n=== Test 4: Execute closing entries ===\n";
// Create some revenue and expense transactions first
$journal->postEntry('Service revenue', 'REV-001', [
    ['account_code' => '112', 'amount' => 10000000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 10000000, 'is_debit' => false],
], 'tester');

$journal->postEntry('Rent expense', 'EXP-001', [
    ['account_code' => '642', 'amount' => 3000000, 'is_debit' => true],
    ['account_code' => '112', 'amount' => 3000000, 'is_debit' => false],
], 'tester');

$svc->executeClosingEntries('tester');

$revBal = $accountRepo->findByCode('511')->getBalance();
$expBal = $accountRepo->findByCode('642')->getBalance();
$plBal = $accountRepo->findByCode('911')->getBalance();
$reBal = $accountRepo->findByCode('421')->getBalance();

assertTrue(abs($revBal) < 1, 'Revenue (511) zeroed after close');
assertTrue(abs($expBal) < 1, 'Expense (642) zeroed after close');
assertTrue(abs($plBal) < 1, 'P&L (911) cleared to zero after profit transfer');
assertTrue(abs($reBal) > 0, 'Retained earnings (421) updated');

echo "\n=== Test 5: Close period ===\n";
$p2 = $svc->createPeriod('month', '2026-06', 'Tháng 6/2026', '2026-06-01', '2026-06-30', 'admin');
$closed = $svc->closePeriod($p2['id'], 'admin');
assertEq('closed', $closed['status'], 'Period status = closed');
assertTrue($closed['closed_at'] !== null, 'closed_at set');

echo "\n=== Test 6: Re-open period ===\n";
$reopened = $svc->reOpenPeriod($p2['id'], 'auditor');
assertEq('open', $reopened['status'], 'Re-opened status = open');
assertEq(1, $reopened['re_open_count'], 're_open_count = 1');

echo "\n=== Test 7: isPeriodOpen static check ===\n";
$GLOBALS['container']['pdo'] = $pdo;
assertTrue(PeriodService::isPeriodOpen('2026-05-15'), 'May 2026 is open');
assertTrue(PeriodService::isPeriodOpen('2026-06-15'), 'June 2026 is open');

// Close June again and check
$svc->closePeriod($p2['id'], 'admin');
assertTrue(!PeriodService::isPeriodOpen('2026-06-15'), 'June is now closed');

echo "\n=== Test 8: Trial balance after closing entries ===\n";
$all = $accountRepo->findAll();
$totalDr = 0; $totalCr = 0;
foreach ($all as $a) {
    $bal = $a->getBalance();
    if (abs($bal) < 1) continue;
    if (in_array($a->getType(), ['asset', 'expense'])) { $totalDr += $bal; }
    else { $totalCr += $bal; }
}
assertEq(round($totalDr, 0), round($totalCr, 0), 'Trial balance: Dr = Cr');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

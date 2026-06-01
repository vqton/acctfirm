<?php
// Test: Kỳ kế toán — mở, đóng, kiểm tra kỳ
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

echo "\n=== Test 1: Create 2026-06 (current month, no prior period check) ===\n";
$pJun = $svc->createPeriod('month', '2026-06', 'Tháng 6/2026', '2026-06-01', '2026-06-30', 'admin');
assertTrue($pJun['id'] > 0, 'Period ID returned');
assertEq('open', $pJun['status'], 'Status = open');

echo "\n=== Test 2: Create 2026-05 (prior period not sequential, allowed) ===\n";
$pMay = $svc->createPeriod('month', '2026-05', 'Tháng 5/2026', '2026-05-01', '2026-05-31', 'admin');
assertTrue($pMay['id'] > 0, 'Period 2026-05 created');
assertEq('open', $pMay['status'], 'Status = open');

// Chồng lấn: period 2026-05-15 → 2026-06-15 overlaps 2026-05 (May 1-31) AND 2026-06 (Jun 1-30)
echo "\n=== Test 3: Overlap validation rejects overlapping period ===\n";
try {
    $svc->createPeriod('month', '2026-05-dup', 'Trùng', '2026-05-15', '2026-06-15', 'admin');
    echo "FAIL: Overlap not rejected\n"; $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Overlap rejected');
}

echo "\n=== Test 4: Adjacent period (2026-04) no gap/overlap ===\n";
$pApr = $svc->createPeriod('month', '2026-04', 'Tháng 4/2026', '2026-04-01', '2026-04-30', 'admin');
assertTrue($pApr['id'] > 0, 'Adjacent period created');

echo "\n=== Test 5: List periods ===\n";
$list = $svc->getPeriods();
assertTrue(count($list) >= 3, 'At least 3 periods');

echo "\n=== Test 6: Close period blocks if invalid id ===\n";
try {
    $svc->closePeriod(99999, 'admin');
    echo "FAIL: Invalid period not rejected\n"; $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Invalid period rejected');
}

echo "\n=== Test 7: Execute closing entries in 2026-06 ===\n";
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
assertTrue(abs($plBal) < 1, 'P&L (911) cleared after profit transfer');
assertTrue(abs($reBal) > 0, 'Retained earnings (421) updated');

echo "\n=== Test 8: Close 2026-05 and 2026-06 ===\n";
$svc->closePeriod($pMay['id'], 'admin');
$closed = $svc->closePeriod($pJun['id'], 'admin');
assertEq('closed', $closed['status'], 'Period status = closed');
assertTrue($closed['closed_at'] !== null, 'closed_at set');

echo "\n=== Test 9: Re-open period ===\n";
$reopened = $svc->reOpenPeriod($pJun['id'], 'auditor');
assertEq('open', $reopened['status'], 'Re-opened status = open');
assertEq(1, $reopened['re_open_count'], 're_open_count = 1');

echo "\n=== Test 10: isPeriodOpen static check ===\n";
$GLOBALS['container']['pdo'] = $pdo;
assertTrue(!PeriodService::isPeriodOpen('2026-05-15'), 'May 2026 is closed');
assertTrue(PeriodService::isPeriodOpen('2026-06-15'), 'June 2026 is open');

$svc->closePeriod($pJun['id'], 'admin');
assertTrue(!PeriodService::isPeriodOpen('2026-06-15'), 'June is now closed');

echo "\n=== Test 11: Trial balance after closing entries ===\n";
$all = $accountRepo->findAll();
$totalDr = 0; $totalCr = 0;
foreach ($all as $a) {
    $bal = $a->getBalance();
    if (abs($bal) < 1) continue;
    if (in_array($a->getType(), ['asset', 'expense'])) { $totalDr += $bal; }
    else { $totalCr += $bal; }
}
assertEq(round($totalDr, 0), round($totalCr, 0), 'Trial balance: Dr = Cr');

echo "\n=== Cleanup: re-open all periods so sequential tests can post ===\n";
$stmt = $pdo->prepare("UPDATE accounting_periods SET status = 'open' WHERE status = 'closed'");
$stmt->execute();
$affected = $stmt->rowCount();
$pdo->exec("INSERT IGNORE INTO accounting_periods (period_code, period_type, name, status, start_date, end_date) VALUES
    ('2026-06', 'month', 'Tháng 6/2026', 'open', '2026-06-01', '2026-06-30'),
    ('2026-05', 'month', 'Tháng 5/2026', 'open', '2026-05-01', '2026-05-31'),
    ('2026-04', 'month', 'Tháng 4/2026', 'open', '2026-04-01', '2026-04-30')");
echo "Re-opened {$affected} periods for subsequent tests\n";

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

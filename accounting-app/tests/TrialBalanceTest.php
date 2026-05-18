<?php
// Test: TrialBalanceService — generate trial balance from posted entries
// RED phase

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$svc = new JournalService($accountRepo, $txnRepo, $pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>10){echo"FAIL: {$m} expected {$a} got {$b}\n";$failed++;}
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

// Reset balances
$pdo->exec('UPDATE accounts SET balance = 0');

// Post 3 journal entries to create trial balance data
$svc->postEntry('Sale 1', 'TB-001', [
    ['account_code' => '111', 'amount' => 1000000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 1000000, 'is_debit' => false],
], 'tester');

$svc->postEntry('Sale 2', 'TB-002', [
    ['account_code' => '111', 'amount' => 2000000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 2000000, 'is_debit' => false],
], 'tester');

$svc->postEntry('Rent expense', 'TB-003', [
    ['account_code' => '642', 'amount' => 500000, 'is_debit' => true],
    ['account_code' => '111', 'amount' => 500000, 'is_debit' => false],
], 'tester');

echo "\n=== Test 1: Manual trial balance check ===\n";
$cash = $accountRepo->findByCode('111')->getBalance();  // 1M+2M-0.5M = 2.5M
$revenue = $accountRepo->findByCode('511')->getBalance(); // 1M+2M = 3M
$expense = $accountRepo->findByCode('642')->getBalance(); // 0.5M

assertEq(2500000, $cash, 'Cash = 2,500,000 (1M+2M-0.5M)');
assertEq(3000000, $revenue, 'Revenue = 3,000,000 (1M+2M)');
assertEq(500000, $expense, 'Expense = 500,000');

// Accounting equation: Assets = Liabilities + Equity
// Cash (asset) = 2.5M. Revenue - Expense = 3M - 0.5M = 2.5M = Equity (net income)
assertEq($cash, $revenue - $expense, 'Accounting equation: Assets = Revenue - Expense');

// Trial balance: all accounts with non-zero balance
echo "\n=== Test 2: Trial balance as array ===\n";
$stmt = $pdo->query("SELECT code, name, type, balance FROM accounts WHERE balance != 0 ORDER BY code");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
assertTrue(count($rows) >= 3, 'At least 3 accounts have non-zero balance');

$totalDr = 0; $totalCr = 0;
$codes = [];
foreach ($rows as $r) {
    $codes[] = $r['code'];
    if (in_array($r['type'], ['asset','expense'])) {
        // Normal debit balance
        $totalDr += $r['balance'];
    } else {
        $totalCr += $r['balance'];
    }
}
echo "  Accounts with balance: " . implode(', ', $codes) . "\n";
echo "  Total Dr: {$totalDr}, Total Cr: {$totalCr}\n";
assertTrue(in_array('111', $codes), 'Cash (111) in trial balance');
assertTrue(in_array('511', $codes), 'Revenue (511) in trial balance');
assertTrue(in_array('642', $codes), 'Expense (642) in trial balance');
assertEq($totalDr, $totalCr, 'Trial balance: total debits = total credits');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);
<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\CashService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$svc = new CashService($accountRepo, $txnRepo, $pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>1){echo"FAIL: {$m} expected {$a} got {$b}\n";$failed++;}
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM petty_cash_transactions');
$pdo->exec('DELETE FROM petty_cash_funds');

echo "\n=== Test 1: Establish petty cash fund ===\n";
$fund = $svc->establishPettyCash('Office petty cash', 5000000, 'tester');
assertTrue(isset($fund['fund_id']), 'Fund ID returned');
assertEq(5000000, $fund['imprest_amount'], 'Imprest amount = 5,000,000');
assertEq(5000000, $fund['current_balance'], 'Current balance = 5,000,000');

$fundId = $fund['fund_id'];

echo "\n=== Test 2: Disburse from petty cash ===\n";
$tx = $svc->disbursePettyCash($fundId, 500000, 'Office supplies', 'PC-PC-01', 'tester');
assertTrue(isset($tx['transaction_id']), 'Disbursement recorded');

$funds = $svc->getPettyCashFunds();
$f = current(array_filter($funds, fn($f) => $f['id'] === $fundId));
assertEq(4500000, $f['current_balance'], 'Balance reduced to 4,500,000');

echo "\n=== Test 3: Multiple disbursements ===\n";
$svc->disbursePettyCash($fundId, 200000, 'Taxi fare', 'PC-PC-02', 'tester');
$svc->disbursePettyCash($fundId, 800000, 'Tea & coffee', 'PC-PC-03', 'tester');

$txs = $svc->getPettyCashTransactions($fundId);
assertTrue(count($txs) >= 3, '3 disbursements recorded');

$funds2 = $svc->getPettyCashFunds();
$f2 = current(array_filter($funds2, fn($f) => $f['id'] === $fundId));
assertEq(3500000, $f2['current_balance'], 'Balance = 3,500,000 after 3 disbursements');

echo "\n=== Test 4: Replenish petty cash with GL entry ===\n";
$svc->recordReceipt(5000000, '511', 'Cash injection', 'PT-PC-01', 'tester');
$result = $svc->replenishPettyCash($fundId, 642, 1500000, 'Monthly replenishment', 'PC-PC-04', 'tester');
assertTrue(isset($result['transaction_id']), 'Replenishment GL entry created');

$funds3 = $svc->getPettyCashFunds();
$f3 = current(array_filter($funds3, fn($f) => $f['id'] === $fundId));
assertEq(5000000, $f3['current_balance'], 'Fund restored to imprest amount 5,000,000');

$expense = $accountRepo->findByCode('642')->getBalance();
assertEq(1500000, $expense, 'Expense (642) recorded = 1,500,000');
$cash2 = $accountRepo->findByCode('111')->getBalance();
assertEq(3500000, $cash2, 'Cash (111) decreased by 1,500,000');

echo "\n=== Test 5: Disbursement exceeding fund balance rejected ===\n";
try {
    $svc->disbursePettyCash($fundId, 99999999, 'Over-limit', 'PC-BAD', 'tester');
    echo "FAIL: Over-limit not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Over-limit disbursement rejected');
}

echo "\n=== Test 6: Close petty cash fund ===\n";
$result2 = $svc->closePettyCash($fundId, 5000000, 'tester');
assertTrue(isset($result2['transaction_id']), 'Close GL entry created');

$funds4 = $svc->getPettyCashFunds();
$f4 = current(array_filter($funds4, fn($f) => $f['id'] === $fundId));
assertEq('closed', $f4['status'], 'Fund status = closed');

echo "\n=== Test 7: Trial balance after petty cash transactions ===\n";
$all = $accountRepo->findAll();
$totalDr = 0; $totalCr = 0;
foreach ($all as $a) {
    $bal = $a->getBalance();
    if (abs($bal) < 1) continue;
    if (in_array($a->getType(), ['asset', 'expense'])) {
        $totalDr += $bal;
    } else {
        $totalCr += $bal;
    }
}
assertEq(round($totalDr, 0), round($totalCr, 0), 'Trial balance: Dr = Cr');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

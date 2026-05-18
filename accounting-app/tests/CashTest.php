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
$svc = new CashService($accountRepo, $txnRepo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>10){echo"FAIL: {$m} expected {$a} got {$b}\n";$failed++;}
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

$pdo->exec('UPDATE accounts SET balance = 0');

echo "\n=== Test 1: Cash receipt from customer (Dr 111 — Cr 131) ===\n";
$result = $svc->recordReceipt(5000000, '131', 'Customer payment for invoice INV-001', 'PT-001', 'tester');

$cash = $accountRepo->findByCode('111')->getBalance();
$ar = $accountRepo->findByCode('131')->getBalance();
assertEq(5000000, $cash, 'Cash (111) increased by 5,000,000');
assertEq(-5000000, $ar, 'AR (131) decreased by 5,000,000 (credit-normal, Cr reduces)');

echo "\n=== Test 2: Cash sale (Dr 111 — Cr 511) ===\n";
$svc->recordReceipt(2000000, '511', 'Cash sale', 'PT-002', 'tester');

$cash2 = $accountRepo->findByCode('111')->getBalance();
$revenue = $accountRepo->findByCode('511')->getBalance();
assertEq(7000000, $cash2, 'Cash (111) = 7,000,000');
assertEq(2000000, $revenue, 'Revenue (511) = 2,000,000');

echo "\n=== Test 3: Cash payment to supplier (Dr 331 — Cr 111) ===\n";
$svc->recordPayment(3000000, '331', 'Payment to supplier for PO-001', 'PC-001', 'tester');

$cash3 = $accountRepo->findByCode('111')->getBalance();
$ap = $accountRepo->findByCode('331')->getBalance();
assertEq(4000000, $cash3, 'Cash (111) decreased to 4,000,000');
assertEq(-3000000, $ap, 'AP (331) decreased by 3,000,000 (credit-normal, Dr reduces)');

echo "\n=== Test 4: Cash payment for operating expense (Dr 642 — Cr 111) ===\n";
$svc->recordPayment(1500000, '642', 'Office supplies', 'PC-002', 'tester');

$cash4 = $accountRepo->findByCode('111')->getBalance();
$expense = $accountRepo->findByCode('642')->getBalance();
assertEq(2500000, $cash4, 'Cash (111) = 2,500,000');
assertEq(1500000, $expense, 'Expense (642) = 1,500,000');

echo "\n=== Test 5: Insufficient cash for payment (rejected) ===\n";
try {
    $svc->recordPayment(99999999, '642', 'Over-limit payment', 'PC-BAD', 'tester');
    echo "FAIL: Insufficient cash not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Insufficient cash rejected');
}

echo "\n=== Test 6: Invalid account rejection ===\n";
try {
    $svc->recordReceipt(100000, 'NONEXIST', 'Bad account', 'PT-BAD', 'tester');
    echo "FAIL: Invalid account not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Invalid account rejected');
}

echo "\n=== Test 7: Trial balance still balances after transactions ===\n";
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

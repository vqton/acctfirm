<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\CashService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$journal = new JournalService($accountRepo, $txnRepo, $pdo);
$svc = new CashService($accountRepo, $txnRepo, $journal);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>10){echo"FAIL: {$m} expected {$a} got {$b}\n";$failed++;}
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

$pdo->exec('UPDATE accounts SET balance = 0');

echo "\n=== Test 1: Cash deposit to bank (Dr 112 — Cr 111) ===\n";
$svc->recordReceipt(10000000, '511', 'Cash sale', 'PT-BANK-01', 'tester');
$svc->recordBankDeposit(6000000, 'Deposit to bank', 'BC-001', 'tester');

$cash = $accountRepo->findByCode('111')->getBalance();
$bank = $accountRepo->findByCode('112')->getBalance();
assertEq(4000000, $cash, 'Cash (111) decreased to 4,000,000 after deposit');
assertEq(6000000, $bank, 'Bank (112) increased to 6,000,000');

echo "\n=== Test 2: Cash withdrawal from bank (Dr 111 — Cr 112) ===\n";
$svc->recordBankWithdrawal(2000000, 'ATM withdrawal', 'BN-001', 'tester');

$cash2 = $accountRepo->findByCode('111')->getBalance();
$bank2 = $accountRepo->findByCode('112')->getBalance();
assertEq(6000000, $cash2, 'Cash (111) increased to 6,000,000');
assertEq(4000000, $bank2, 'Bank (112) decreased to 4,000,000');

echo "\n=== Test 3: Customer pays directly to bank (Dr 112 — Cr 131) ===\n";
$svc->recordBankReceipt(3000000, '131', 'Customer payment via bank', 'BC-002', 'tester');

$bank3 = $accountRepo->findByCode('112')->getBalance();
$ar = $accountRepo->findByCode('131')->getBalance();
assertEq(7000000, $bank3, 'Bank (112) increased to 7,000,000');
assertEq(-3000000, $ar, 'AR (131) decreased by 3,000,000');

echo "\n=== Test 4: Supplier paid via bank transfer (Dr 331 — Cr 112) ===\n";
$svc->recordBankPayment(4000000, '331', 'Supplier payment via bank', 'BN-002', 'tester');

$bank4 = $accountRepo->findByCode('112')->getBalance();
$ap = $accountRepo->findByCode('331')->getBalance();
assertEq(3000000, $bank4, 'Bank (112) decreased to 3,000,000');
assertEq(-4000000, $ap, 'AP (331) decreased by 4,000,000');

echo "\n=== Test 5: Bank interest credited (Dr 112 — Cr 515) ===\n";
$svc->recordBankInterest(50000, 'Interest income August', 'BC-003', 'tester');

$bank5 = $accountRepo->findByCode('112')->getBalance();
$interest = $accountRepo->findByCode('515')->getBalance();
assertEq(3050000, $bank5, 'Bank (112) increased to 3,050,000');
assertEq(50000, $interest, 'Interest income (515) = 50,000');

echo "\n=== Test 6: Bank charges debited (Dr 642 — Cr 112) ===\n";
$svc->recordBankCharge(220000, 'Service fee', 'BN-003', 'tester');

$bank6 = $accountRepo->findByCode('112')->getBalance();
$expense = $accountRepo->findByCode('642')->getBalance();
assertEq(2830000, $bank6, 'Bank (112) decreased to 2,830,000');
assertEq(220000, $expense, 'Expense (642) = 220,000');

echo "\n=== Test 7: Insufficient bank balance rejected ===\n";
try {
    $svc->recordBankPayment(99999999, '331', 'Over-limit payment', 'BN-BAD', 'tester');
    echo "FAIL: Insufficient bank balance not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Insufficient bank balance rejected');
}

echo "\n=== Test 8: Trial balance after bank transactions ===\n";
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

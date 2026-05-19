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
$svc = new CashService($accountRepo, $txnRepo, $journal, $pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>1){echo"FAIL: {$m} expected {$a} got {$b}\n";$failed++;}
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

$pdo->exec('UPDATE accounts SET balance = 0');

echo "\n=== Test 1: Cash book shows entries after receipt and payment ===\n";
$svc->recordReceipt(10000000, '511', 'Revenue', 'PT-CB-01', 'tester');
$svc->recordPayment(3000000, '642', 'Rent', 'PC-CB-01', 'tester');

$book = $svc->getCashBook();
assertTrue(count($book) >= 2, 'Cash book has at least 2 entries');
assertTrue($book[0]['balance'] > 0, 'Running balance after first entry is positive');

echo "\n=== Test 2: Running balance calculated correctly ===\n";
$receiptTotal = 0; $paymentTotal = 0;
foreach ($book as $e) {
    $receiptTotal += $e['receipt_amount'];
    $paymentTotal += $e['payment_amount'];
}
$lastBalance = $book[count($book)-1]['balance'];
$expectedLast = $receiptTotal - $paymentTotal;
assertEq($expectedLast, $lastBalance, 'Last running balance = total receipts - total payments');

echo "\n=== Test 3: Cash book filtered by date range ===\n";
$filtered = $svc->getCashBook('2020-01-01', '2099-12-31');
assertTrue(count($filtered) > 0, 'Filtered cash book returns entries');

echo "\n=== Test 4: Cash book entries sorted chronologically ===\n";
for ($i = 1; $i < count($book); $i++) {
    assertTrue(strtotime($book[$i]['date']) >= strtotime($book[$i-1]['date']), 'Entries sorted by date');
}

echo "\n=== Test 5: Running balance starts from 0 and ends at account balance ===\n";
assertEq(0, $book[0]['balance'] - $book[0]['receipt_amount'] + $book[0]['payment_amount'], 'First entry: start balance + receipt - payment = running');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

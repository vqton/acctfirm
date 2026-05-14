<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\CashService;
use Accounting\Infrastructure\Repository\PDOAccountRepository;
use Accounting\Infrastructure\Repository\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$cash = new CashService($accountRepo, $txnRepo, $pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>10){echo"FAIL: {$m} expected {$a} got {$b}\n";$failed++;}
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM fc_transactions');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');

echo "\n=== Test 1: FC bank receipt (USD customer payment) ===\n";
// Customer pays $1,000 at rate 25,500 → VND 25,500,000
$result = $cash->recordReceiptFC(1000, '131', 'USD', 25500, 'Customer A payment in USD', 'BC-FC-001', 'tester');

$bank = $accountRepo->findByCode('112')->getBalance();
$ar = $accountRepo->findByCode('131')->getBalance();
assertEq(25500000, $bank, 'Bank (112) increased by 25,500,000 VND');
assertEq(-25500000, $ar, 'AR (131) decreased by 25,500,000 VND');

$fc = $pdo->query('SELECT * FROM fc_transactions')->fetchAll(PDO::FETCH_ASSOC);
assertTrue(count($fc) >= 1, 'FC transaction record created');
assertEq('USD', $fc[0]['currency_code'], 'Currency = USD');
assertEq(1000, $fc[0]['fc_amount'], 'FC amount = 1,000');
assertEq(25500, $fc[0]['exchange_rate'], 'Rate = 25,500');

echo "\n=== Test 2: FC bank payment (USD supplier) ===\n";
$cash->recordPaymentFC(500, '331', 'USD', 25600, 'Supplier payment in USD', 'PC-FC-001', 'tester');

$bank2 = $accountRepo->findByCode('112')->getBalance();
$ap = $accountRepo->findByCode('331')->getBalance();
assertEq(12700000, $bank2, 'Bank = 25.5M - 12.8M = 12,700,000');
assertEq(-12800000, $ap, 'AP (331) decreased by 12,800,000');

echo "\n=== Test 3: FC balance summary ===\n";
$summary = $cash->getFCBalances();
assertTrue(count($summary) >= 1, 'FC balance summary returned');
$usdEntry = current(array_filter($summary, fn($s) => $s['currency'] === 'USD'));
assertTrue($usdEntry !== false, 'USD entry in summary');
assertEq(500, $usdEntry['fc_balance'], 'USD balance = 1,000 - 500 = 500');

echo "\n=== Test 4: Period-end revaluation (rate increased) ===\n";
// Book rate: weighted average. Rate now 25,800. FC balance = 500 USD.
// Gain = 500 × (25,800 - book_avg_rate)
$reval = $cash->revalueFC('112', 'USD', 25800, date('Y-m-d'), 'tester');
assertTrue(isset($reval['transaction_id']), 'Revaluation entry created');
assertTrue($reval['gain_loss'] > 0, 'Gain positive (rate increased)');

$bank3 = $accountRepo->findByCode('112')->getBalance();
$fxDiff = $accountRepo->findByCode('413')->getBalance();
assertTrue(abs($fxDiff) > 0, 'TK 413 (FX diff) has balance');

echo "\n=== Test 5: Trial balance after FC transactions ===\n";
$all = $accountRepo->findAll();
$totalDr = 0; $totalCr = 0;
foreach ($all as $a) {
    $bal = $a->getBalance();
    if (abs($bal) < 1) continue;
    if (in_array($a->getType(), ['asset', 'expense'])) { $totalDr += $bal; }
    else { $totalCr += $bal; }
}
assertEq(round($totalDr, 0), round($totalCr, 0), 'Trial balance: Dr = Cr');
$fxAccount = $accountRepo->findByCode('413');
assertTrue($fxAccount !== null, 'TK 413 exists');

echo "\n=== Test 6: Revalue again with no rate change (zero gain/loss) ===\n";
$reval2 = $cash->revalueFC('112', 'USD', 25800, date('Y-m-d'), 'tester');
assertEq(0, $reval2['gain_loss'], 'Zero gain/loss when rate unchanged');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

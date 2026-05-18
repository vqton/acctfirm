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
    $total++; if(abs((float)$a-(float)$b)>10){echo"FAIL: {$m} expected {$a} got {$b}\n";$failed++;}
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM cash_transit');

echo "\n=== Test 1: Record cash in transit (Dr 113 — Cr 111) ===\n";
$svc->recordReceipt(8000000, '511', 'Cash sale', 'PT-TR-01', 'tester');
$result = $svc->recordTransit(5000000, 'End-of-day deposit', 'CT-001', 'tester');

$cash = $accountRepo->findByCode('111')->getBalance();
$transit = $accountRepo->findByCode('113')->getBalance();
assertEq(3000000, $cash, 'Cash (111) = 3,000,000 after transit');
assertEq(5000000, $transit, 'Transit (113) = 5,000,000');
assertTrue(isset($result['transit_id']), 'Transit record ID returned');

echo "\n=== Test 2: Confirm transit — bank credited (Dr 112 — Cr 113) ===\n";
$svc->confirmTransit($result['transit_id'], 'tester');

$bank = $accountRepo->findByCode('112')->getBalance();
$transit2 = $accountRepo->findByCode('113')->getBalance();
assertEq(5000000, $bank, 'Bank (112) = 5,000,000 after confirmation');
assertEq(0, $transit2, 'Transit (113) cleared to 0');

$row = $pdo->query("SELECT status FROM cash_transit WHERE id='{$result['transit_id']}'")->fetch();
assertEq('confirmed', $row['status'], 'Transit status = confirmed');

echo "\n=== Test 3: Cheque dishonour — reverse transit (Dr 111 — Cr 113) ===\n";
$result2 = $svc->recordTransit(2000000, 'Cheque deposit', 'CT-002', 'tester');
$svc->reverseTransit($result2['transit_id'], 'tester');

$cash3 = $accountRepo->findByCode('111')->getBalance();
$transit3 = $accountRepo->findByCode('113')->getBalance();
// 111 went from 3,000,000 → 1,000,000 (transit 2M out), then reversed back to 3,000,000
assertEq(3000000, $cash3, 'Cash (111) restored after reversal');
assertEq(0, $transit3, 'Transit (113) cleared after reversal');

$row2 = $pdo->query("SELECT status FROM cash_transit WHERE id='{$result2['transit_id']}'")->fetch();
assertEq('reversed', $row2['status'], 'Transit status = reversed');

echo "\n=== Test 4: Trial balance after transit transactions ===\n";
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

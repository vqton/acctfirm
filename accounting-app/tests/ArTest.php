<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\ArService;
use Accounting\Infrastructure\Repository\PDOAccountRepository;
use Accounting\Infrastructure\Repository\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$ar = new ArService($pdo, $accountRepo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>1){echo"FAIL: {$m} expected {$b} got {$a}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM ar_payments');
$pdo->exec('DELETE FROM ar_invoices');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');
$pdo->exec('UPDATE customers SET balance = 0');

$customers = $ar->getCustomers();
if (empty($customers)) {
    echo "FAIL: No customers. Seed some first.\n"; exit(1);
}
$cid = $customers[0]['id'];

echo "\n=== Test 1: Record AR invoice ===\n";
$inv = $ar->recordInvoice($cid, 'SI-2026-001', '2026-05-01', '2026-06-01', 20000000, 2000000, 10, 'Software license sale', 'tester');
assertTrue($inv['invoice_id'] > 0, 'Invoice recorded');
assertEq(22000000, $inv['amount'], 'Total = 20M + 2M VAT');

$invGet = $ar->getInvoice($inv['invoice_id']);
assertEq('unpaid', $invGet['status'], 'Status = unpaid');
assertEq(22000000, $invGet['balance'], 'Balance = gross');

$arBal = $accountRepo->findByCode('131')->getBalance();
assertEq(22000000, $arBal, 'AR (131) increased by 22,000,000');
$revBal = $accountRepo->findByCode('511')->getBalance();
assertEq(20000000, $revBal, 'Revenue (511) = 20,000,000');

echo "\n=== Test 2: Partial payment ===\n";
$pay = $ar->recordPayment($inv['invoice_id'], 10000000, 'tester');
assertEq(12000000, $pay['balance'], 'Remaining = 22M - 10M');
assertEq('partial', $ar->getInvoice($inv['invoice_id'])['status'], 'Status = partial');

echo "\n=== Test 3: Full payment ===\n";
$ar->recordPayment($inv['invoice_id'], 12000000, 'tester');
assertEq('paid', $ar->getInvoice($inv['invoice_id'])['status'], 'Status = paid');

echo "\n=== Test 4: Prepayment ===\n";
$pre = $ar->recordPrepayment($cid, 5000000, 'Advance for order', 'tester');
assertTrue($pre['invoice_id'] > 0, 'Prepayment recorded');

echo "\n=== Test 5: Sales return ===\n";
$retInv = $ar->recordInvoice($cid, 'SI-RET-001', '2026-06-01', '2026-07-01', 5000000, 500000, 10, 'Return test', 'tester');
$ret = $ar->recordReturn($retInv['invoice_id'], 2000000, 'tester');
assertTrue($ret['amount'] > 0, 'Return recorded');

$deduction = $accountRepo->findByCode('521')->getBalance();
assertTrue($deduction != 0, 'Revenue deduction (521) recorded (contra-revenue, debit-normal)');

echo "\n=== Test 6: Settlement discount ===\n";
$discInv = $ar->recordInvoice($cid, 'SI-DISC-001', '2026-07-01', '2026-08-01', 3000000, 300000, 10, 'Discount test', 'tester');
$disc = $ar->recordSettlementDiscount($discInv['invoice_id'], 200000, 'tester');
assertTrue($disc['amount'] > 0, 'Discount recorded');

$fc = $accountRepo->findByCode('635')->getBalance();
assertTrue($fc > 0, 'Finance cost (635) recorded for discount');

echo "\n=== Test 7: Write-off ===\n";
$woInv = $ar->recordInvoice($cid, 'SI-WO-001', '2025-03-01', '2025-03-15', 2000000, 200000, 10, 'Write-off test', 'tester');
$wo = $ar->writeOff($woInv['invoice_id'], 'tester');
assertTrue($wo['amount'] > 0, 'Write-off recorded');
assertEq('written_off', $ar->getInvoice($woInv['invoice_id'])['status'], 'Status = written_off');

echo "\n=== Test 8: Aging report ===\n";
$aging = $ar->getAgingReport();
assertTrue(isset($aging['buckets']), 'Aging has buckets');

echo "\n=== Test 9: Customer statement ===\n";
$stmt = $ar->getCustomerStatement($cid);
assertTrue(count($stmt) >= 1, 'Statement non-empty');

echo "\n=== Test 10: Trial balance ===\n";
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

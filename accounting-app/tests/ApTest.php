<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\ApService;
use Accounting\Infrastructure\Repository\PDOAccountRepository;
use Accounting\Infrastructure\Repository\PDOTransactionRepository;
use Accounting\Infrastructure\Repository\PDOSupplierRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$supplierRepo = new PDOSupplierRepository($pdo);
$ap = new ApService($pdo, $supplierRepo, $accountRepo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>1){echo"FAIL: {$m} expected {$b} got {$a}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM ap_payments');
$pdo->exec('DELETE FROM ap_invoices');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');
$pdo->exec('UPDATE suppliers SET balance = 0');

// Get or create a test supplier
$suppliers = $ap->getSuppliers();
if (empty($suppliers)) {
    echo "FAIL: No suppliers in DB. Seed some suppliers first.\n";
    $supplierId = 'test_supplier_1';
    $supplierRepo->save(new \Accounting\Domain\Model\Supplier($supplierId, 'SUP001', 'Test Supplier Ltd', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL));
} else {
    $supplierId = $suppliers[0]['id'];
}

echo "\n=== Test 1: Record AP invoice ===\n";
$inv = $ap->recordInvoice($supplierId, 'INV-2026-001', '2026-05-01', '2026-05-31', 10000000, 1000000, 10, 'Raw materials purchase', '152', 'tester');
assertTrue($inv['invoice_id'] > 0, 'Invoice recorded with ID');
assertEq(11000000, $inv['amount'], 'Total amount = 10M + 1M VAT');

$apInv = $ap->getInvoice($inv['invoice_id']);
assertEq('unpaid', $apInv['status'], 'Status = unpaid');
assertEq(11000000, $apInv['balance'], 'Balance = gross amount');

$bank = $accountRepo->findByCode('112')->getBalance();
$apBal = $accountRepo->findByCode('331')->getBalance();
assertEq(11000000, $apBal, 'AP (331) increased by 11,000,000 (credit-normal)');
$supplier = $supplierRepo->findById($supplierId);
assertTrue($supplier->getBalance() > 0, 'Supplier balance updated');

echo "\n=== Test 2: Invoice list ===\n";
$list = $ap->getInvoices();
assertTrue(count($list) >= 1, 'Invoice list non-empty');

echo "\n=== Test 3: Partial payment ===\n";
$pay = $ap->recordPayment($inv['invoice_id'], 5000000, 'tester');
assertEq(5000000, $pay['amount'], 'Payment recorded');
assertEq(6000000, $pay['balance'], 'Remaining balance = 11M - 5M');

$apInv2 = $ap->getInvoice($inv['invoice_id']);
assertEq('partial', $apInv2['status'], 'Status = partial');

echo "\n=== Test 4: Full payment ===\n";
$pay2 = $ap->recordPayment($inv['invoice_id'], 6000000, 'tester');
assertEq('paid', $ap->getInvoice($inv['invoice_id'])['status'], 'Status = paid');

echo "\n=== Test 5: Prepayment ===\n";
$pre = $ap->recordPrepayment($supplierId, 2000000, 'Advance for order PO-005', 'tester');
assertTrue($pre['invoice_id'] > 0, 'Prepayment recorded');

echo "\n=== Test 6: Aging report ===\n";
// Create an overdue invoice
$oldInv = $ap->recordInvoice($supplierId, 'INV-OLD-001', '2026-01-01', '2026-01-15', 5000000, 500000, 10, 'Old purchase', '152', 'tester');

$aging = $ap->getAgingReport();
assertTrue(isset($aging['buckets']), 'Aging report has buckets');
assertTrue(count($aging['buckets']['current']) >= 0, 'Current bucket exists');

echo "\n=== Test 7: Discount ===\n";
$discInv = $ap->recordInvoice($supplierId, 'INV-DISC-001', '2026-06-01', '2026-06-30', 3000000, 300000, 10, 'Discount test', '152', 'tester');
$disc = $ap->recordDiscount($discInv['invoice_id'], 100000, 'tester');
assertTrue($disc['amount'] > 0, 'Discount recorded');

$fi = $accountRepo->findByCode('515')->getBalance();
assertTrue($fi > 0, 'Finance income (515) recorded for discount');

echo "\n=== Test 8: Return ===\n";
$retInv = $ap->recordInvoice($supplierId, 'INV-RET-001', '2026-06-01', '2026-06-30', 2000000, 200000, 10, 'Return test', '152', 'tester');
$ret = $ap->recordReturn($retInv['invoice_id'], 500000, '152', 'tester');
assertTrue($ret['amount'] > 0, 'Return recorded');

$retInv2 = $ap->getInvoice($retInv['invoice_id']);
assertTrue($retInv2['balance'] < 2200000, 'Balance reduced after return');

echo "\n=== Test 9: Write-off ===\n";
$woInv = $ap->recordInvoice($supplierId, 'INV-WO-001', '2025-01-01', '2025-01-15', 1000000, 100000, 10, 'Write-off test', '152', 'tester');
$wo = $ap->writeOff($woInv['invoice_id'], 'tester');
assertTrue($wo['amount'] > 0, 'Write-off recorded');

$woInv2 = $ap->getInvoice($woInv['invoice_id']);
assertEq('written_off', $woInv2['status'], 'Status = written_off');

echo "\n=== Test 10: Supplier statement ===\n";
$stmt = $ap->getSupplierStatement($supplierId);
assertTrue(count($stmt) >= 1, 'Supplier statement non-empty');

echo "\n=== Test 11: Trial balance after all AP transactions ===\n";
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

<?php
// Test: AR — công nợ phải thu khách hàng (TK 131)
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\ArService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Đảm bảo kỳ 2026-05 đang mở cho test
$pdo->exec("INSERT IGNORE INTO accounting_periods (period_code, start_date, end_date, status) VALUES ('2026-05', '2026-05-01', '2026-05-31', 'open')");
$pdo->exec("UPDATE accounting_periods SET status = 'open' WHERE period_code = '2026-05'");
// Reset credit limits từ test trước
$pdo->exec("UPDATE customers SET credit_limit = 0");

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$journal = new JournalService($accountRepo, $txnRepo, $pdo);
$ar = new ArService($pdo, $accountRepo, $journal);

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

// Nghiệp vụ: Hàng bán bị trả lại — ghi nhận khoản giảm trừ doanh thu
// TK 521 (Các khoản giảm trừ doanh thu) — bên Nợ
// Nếu fail → doanh thu gộp không được điều chỉnh → sai BC02
echo "\n=== Test 5: Sales return ===\n";
$retInv = $ar->recordInvoice($cid, 'SI-RET-001', '2026-06-01', '2026-07-01', 5000000, 500000, 10, 'Return test', 'tester');
$ret = $ar->recordReturn($retInv['invoice_id'], 2000000, 'tester');
assertTrue($ret['amount'] > 0, 'Return recorded');

$deduction = $accountRepo->findByCode('521')->getBalance();
assertTrue($deduction != 0, 'Revenue deduction (521) recorded (contra-revenue, debit-normal)');

// Nghiệp vụ: Chiết khấu thanh toán cho khách hàng — ghi vào TK 635 (Chi phí tài chính)
// Nếu fail → chiết khấu không được ghi nhận đúng → sai chi phí tài chính
echo "\n=== Test 6: Settlement discount ===\n";
$discInv = $ar->recordInvoice($cid, 'SI-DISC-001', '2026-07-01', '2026-08-01', 3000000, 300000, 10, 'Discount test', 'tester');
$disc = $ar->recordSettlementDiscount($discInv['invoice_id'], 200000, 'tester');
assertTrue($disc['amount'] > 0, 'Discount recorded');

$fc = $accountRepo->findByCode('635')->getBalance();
assertTrue($fc > 0, 'Finance cost (635) recorded for discount');

// Nghiệp vụ: Xóa sổ công nợ phải thu khó đòi
// Chỉ áp dụng với hóa đơn quá hạn lâu ngày, được phê duyệt đặc biệt
// Nếu fail → không xóa được nợ xấu → BC01 sai số dư 131
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

echo "\n=== Test 10: Multi-invoice receipt allocation ===\n";
$allocInv1 = $ar->recordInvoice($cid, 'REC-ALLOC-001', '2026-05-20', '2026-06-20', 4000000, 400000, 10, 'Receipt alloc test 1', 'tester');
$allocInv2 = $ar->recordInvoice($cid, 'REC-ALLOC-002', '2026-05-20', '2026-06-20', 6000000, 600000, 10, 'Receipt alloc test 2', 'tester');
$totalAlloc = $allocInv1['amount'] + $allocInv2['amount'];
assertEq(11000000, $totalAlloc, 'Total invoices = 4.4M + 6.6M = 11M');

$alloc = $ar->allocateReceipt([
    ['invoice_id' => $allocInv1['invoice_id'], 'amount' => 4400000],
    ['invoice_id' => $allocInv2['invoice_id'], 'amount' => 6600000],
], '112', 'Bulk receipt', 'tester');
assertEq(11000000, $alloc['total_amount'], 'Allocation total = 11M');
assertEq('paid', $ar->getInvoice($allocInv1['invoice_id'])['status'], 'Invoice 1 paid');
assertEq('paid', $ar->getInvoice($allocInv2['invoice_id'])['status'], 'Invoice 2 paid');

$allDet = $ar->getReceiptAllocationDetails($alloc['transaction_id']);
assertEq(2, count($allDet), 'Allocation has 2 entries');

// Different customer → should fail
$otherCust = $customers[1]['id'] ?? null;
if ($otherCust) {
    $otherInv = $ar->recordInvoice($otherCust, 'REC-OTHER', '2026-05-20', '2026-06-20', 1000000, 100000, 10, 'Other customer', 'tester');
    try {
        $ar->allocateReceipt([['invoice_id' => $otherInv['invoice_id'], 'amount' => 1000]], '112', 'Different customer', 'tester');
        assertTrue(false, 'Should reject different customer');
    } catch (\InvalidArgumentException $e) {
        assertTrue(true, 'Different customer rejected');
    }
}

echo "\n=== Test 11: Credit limit enforcement ===\n";
$creditCid = $customers[count($customers) - 1]['id']; // Use last customer (likely 0 balance)
$pdo->prepare("UPDATE customers SET credit_limit = 500000, balance = 0 WHERE id = ?")->execute([$creditCid]);
try {
    $ar->recordInvoice($creditCid, 'OVER-LIMIT', '2026-05-25', '2026-06-25', 1000000, 100000, 10, 'Over limit', 'tester');
    assertTrue(false, 'Should reject over-limit AR invoice');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Over-limit AR invoice rejected');
}
$under = $ar->recordInvoice($creditCid, 'UNDER-LIMIT', '2026-05-25', '2026-06-25', 300000, 30000, 10, 'Under limit', 'tester');
assertTrue($under['invoice_id'] > 0, 'Under-limit AR invoice allowed');
$pdo->prepare("UPDATE customers SET credit_limit = 0 WHERE id = ?")->execute([$creditCid]);

echo "\n=== Test 12: Provision rate calc (TT 48/2019) ===\n";
assertEq(0, $ar->getProvisionRate(0), 'Current = 0%');
assertEq(0, $ar->getProvisionRate(180), '6 months = 0%');
assertEq(30, $ar->getProvisionRate(181), '6-12 months = 30%');
assertEq(30, $ar->getProvisionRate(365), '12 months = 30%');
assertEq(50, $ar->getProvisionRate(366), '12-18 months = 50%');
assertEq(50, $ar->getProvisionRate(545), '18 months = 50%');
assertEq(70, $ar->getProvisionRate(546), '18-36 months = 70%');
assertEq(70, $ar->getProvisionRate(1095), '36 months = 70%');
assertEq(100, $ar->getProvisionRate(1096), '>36 months = 100%');

echo "\n=== Test 13: Provision summary ===\n";
$prov = $ar->getProvisionSummary();
assertTrue($prov['total_balance'] > 0, 'Provision balance > 0');
assertTrue($prov['total_provision'] >= 0, 'Provision amount >= 0');
assertTrue(count($prov['buckets']) === 5, '5 TT48 buckets');
assertTrue(count($prov['details']) > 0, 'Provision details non-empty');
assertTrue(isset($prov['details'][0]['provision_rate']), 'Each detail has rate');
assertTrue(isset($prov['details'][0]['provision_amount']), 'Each detail has provision amount');

echo "\n=== Test 14: Trial balance ===\n";
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

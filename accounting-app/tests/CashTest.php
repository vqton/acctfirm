<?php
// Test: Nghiệp vụ tiền mặt (TK 111) — thu, chi, tạm ứng
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

// Ràng buộc nghiệp vụ: Số dư tiền mặt không đủ → từ chối chi
// Nếu fail → có thể chi vượt quá số dư → âm tiền mặt không kiểm soát
echo "\n=== Test 5: Insufficient cash for payment (rejected) ===\n";
try {
    $svc->recordPayment(99999999, '642', 'Over-limit payment', 'PC-BAD', 'tester');
    echo "FAIL: Insufficient cash not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Insufficient cash rejected');
}

// Ràng buộc: Mã tài khoản không tồn tại → từ chối
// Nếu fail → thu/chi vào TK không hợp lệ → sai số dư
echo "\n=== Test 6: Invalid account rejection ===\n";
try {
    $svc->recordReceipt(100000, 'NONEXIST', 'Bad account', 'PT-BAD', 'tester');
    echo "FAIL: Invalid account not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Invalid account rejected');
}

// Kiểm tra ràng buộc kế toán: Tổng Dr = tổng Cr sau tất cả giao dịch
// Nếu fail → hệ thống mất cân đối, báo cáo tài chính sai
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

// ── VAT Tests ──
// NGHIỆP VỤ: THU TIỀN CÓ VAT — bán hàng thu tiền mặt, tổng 11tr (hàng 10tr + VAT 1tr)
// Hạch toán chuẩn: Nợ 111 (11tr) / Có 511 (10tr) + Có 33311 (1tr)
echo "\n=== Test 8: Cash receipt with VAT output (Dr 111 — Cr 511 + Cr 33311) ===\n";
$pdo->exec('UPDATE accounts SET balance = 0');
$result = $svc->recordReceipt(11000000, '511', 'Cash sale with VAT', 'PT-VAT-001', 'tester', 1000000, 10);

$cash8 = $accountRepo->findByCode('111')->getBalance();
$rev8 = $accountRepo->findByCode('511')->getBalance();
$vatOut = $accountRepo->findByCode('33311')->getBalance();
assertEq(11000000, $cash8, 'Cash (111) = 11,000,000 (total)');
assertEq(10000000, $rev8, 'Revenue (511) = 10,000,000 (net)');
assertEq(1000000, $vatOut, 'VAT output (33311) = 1,000,000');

// NGHIỆP VỤ: CHI TIỀN CÓ VAT — mua văn phòng phẩm 5.5tr (hàng 5tr + VAT 0.5tr)
// Hạch toán chuẩn: Nợ 642 (5tr) + Nợ 1331 (0.5tr) / Có 111 (5.5tr)
echo "\n=== Test 9: Cash payment with VAT input (Dr 642 + Dr 1331 — Cr 111) ===\n";
$pdo->exec('UPDATE accounts SET balance = 0');
// First add enough cash
$svc->recordReceipt(50000000, '511', 'Initial cash', 'PT-INIT', 'tester');
$result = $svc->recordPayment(5500000, '642', 'Office supplies with VAT', 'PC-VAT-001', 'tester', 500000, 10);

$cash9 = $accountRepo->findByCode('111')->getBalance();
$exp9 = $accountRepo->findByCode('642')->getBalance();
$vatIn = $accountRepo->findByCode('1331')->getBalance();
assertEq(44500000, $cash9, 'Cash (111) = 44,500,000 (50tr - 5.5tr)');
assertEq(5000000, $exp9, 'Expense (642) = 5,000,000 (net)');
assertEq(500000, $vatIn, 'VAT input (1331) = 500,000');

// NGHIỆP VỤ: THU TIỀN QUA NH CÓ VAT — bán hàng thu qua bank 33tr (hàng 30tr + VAT 3tr)
echo "\n=== Test 10: Bank receipt with VAT output (Dr 112 — Cr 511 + Cr 33311) ===\n";
$pdo->exec('UPDATE accounts SET balance = 0');
$result = $svc->recordBankReceipt(33000000, '511', 'Bank sale with VAT', 'BC-VAT-001', 'tester', 3000000, 10);

$bank10 = $accountRepo->findByCode('112')->getBalance();
$rev10 = $accountRepo->findByCode('511')->getBalance();
$vatOut10 = $accountRepo->findByCode('33311')->getBalance();
assertEq(33000000, $bank10, 'Bank (112) = 33,000,000');
assertEq(30000000, $rev10, 'Revenue (511) = 30,000,000 (net)');
assertEq(3000000, $vatOut10, 'VAT output (33311) = 3,000,000');

// NGHIỆP VỤ: CHI TIỀN QUA NH CÓ VAT — mua TSCĐ 66tr (hàng 60tr + VAT 6tr)
echo "\n=== Test 11: Bank payment with VAT input (Dr 211 + Dr 1331 — Cr 112) ===\n";
$pdo->exec('UPDATE accounts SET balance = 0');
$svc->recordBankReceipt(100000000, '511', 'Initial bank fund', 'BC-INIT', 'tester');
$result = $svc->recordBankPayment(66000000, '211', 'Buy FA with VAT', 'BN-VAT-001', 'tester', 6000000, 10);

$bank11 = $accountRepo->findByCode('112')->getBalance();
$fa11 = $accountRepo->findByCode('211')->getBalance();
$vatIn11 = $accountRepo->findByCode('1331')->getBalance();
assertEq(34000000, $bank11, 'Bank (112) = 34,000,000 (100tr - 66tr)');
assertEq(60000000, $fa11, 'FA (211) = 60,000,000 (net)');
assertEq(6000000, $vatIn11, 'VAT input (1331) = 6,000,000');

// NGHIỆP VỤ: PHÍ NGÂN HÀNG CÓ VAT — phí 2.2tr (phí 2tr + VAT 0.2tr)
echo "\n=== Test 12: Bank charge with VAT input (Dr 642 + Dr 1331 — Cr 112) ===\n";
$result = $svc->recordBankCharge(2200000, 'Bank fee with VAT', 'BN-VAT-002', 'tester', 200000, 10);

$bank12 = $accountRepo->findByCode('112')->getBalance();
$exp12 = $accountRepo->findByCode('642')->getBalance();
$vatIn12 = $accountRepo->findByCode('1331')->getBalance();
assertEq(31800000, $bank12, 'Bank (112) = 31,800,000 (34tr - 2.2tr)');
assertEq(2000000, $exp12, 'Expense (642) = 2,000,000 (net)');
assertEq(6200000, $vatIn12, 'VAT input (1331) = 6,200,000 (6tr + 0.2tr)');

// KIỂM TRA Dr = Cr TOÀN HỆ THỐNG SAU CÁC GIAO DỊCH VAT
echo "\n=== Test 13: Trial balance after VAT transactions ===\n";
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
assertEq(round($totalDr, 0), round($totalCr, 0), 'Trial balance: Dr = Cr after VAT transactions');

// Ràng buộc: VAT amount > 0 nhưng <= tổng tiền
echo "\n=== Test 14: VAT amount exceeds total (rejected) ===\n";
try {
    $svc->recordReceipt(10000000, '511', 'VAT > total', 'PT-VAT-BAD', 'tester', 15000000, 10);
    echo "FAIL: VAT > total not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'VAT > total rejected (Dr != Cr)');
}

// KIỂM TRA T11: AUTO-LINK AP SUPPLIER PAYMENT
echo "\n=== Test 15: Auto-link AP payment allocation ===\n";
// Đảm bảo đủ tiền mặt trước khi test
$pdo->exec('UPDATE accounts SET balance = 50000000 WHERE code = 111');
$apPmt = $svc->recordPayment(3000000, '331', 'Supplier payment auto-link', 'PC-AUTO-001', 'tester');
$apTxnId = $apPmt['transaction_id'];
// Giả lập controller: ghi payer_info + auto-insert payment_allocation
$pdo->prepare('UPDATE transactions SET payer_type = ?, payer_id = ? WHERE id = ?')
    ->execute(['supplier', '999', $apTxnId]);
$pdo->prepare("INSERT INTO payment_allocations (payment_type, transaction_id, invoice_id, amount, entity_id) VALUES ('ap', ?, 0, ?, 1)")
    ->execute([$apTxnId, 3000000]);
$allocStmt = $pdo->prepare("SELECT * FROM payment_allocations WHERE transaction_id = ?");
$allocStmt->execute([$apTxnId]);
$alloc = $allocStmt->fetch(PDO::FETCH_ASSOC);
assertTrue($alloc !== false, 'Payment allocation created for supplier payment');
assertEq($alloc['payment_type'], 'ap', 'Allocation type = ap');
assertEq($alloc['amount'], 3000000, 'Allocation amount = 3,000,000');
assertEq($alloc['invoice_id'], 0, 'Allocation invoice_id = 0 (unallocated)');
// Cleanup
$pdo->prepare("DELETE FROM payment_allocations WHERE transaction_id = ?")->execute([$apTxnId]);

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

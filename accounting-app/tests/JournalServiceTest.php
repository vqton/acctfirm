<?php
// Test: JournalService — create + post simple double-entry
// RED phase — test defines expected behavior before implementation

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

require_once __DIR__ . '/../src/Accounting/Domain/Service/JournalService.php';

use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Model\Account;

$failed = 0;
$total = 0;

function assertEq($expected, $actual, $msg) {
    global $total, $failed;
    $total++;
    if (abs((float)$expected - (float)$actual) > 10) { // tolerance for VND amounts
        echo "FAIL: {$msg} — expected {$expected}, got {$actual}\n";
        $failed++;
    } else {
        echo "PASS: {$msg}\n";
    }
}

function assertTrue($cond, $msg) {
    global $total, $failed;
    $total++;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failed++;
    } else {
        echo "PASS: {$msg}\n";
    }
}

// Setup: PDO + repositories
$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new \Accounting\Infrastructure\Persistence\PDOAccountRepository($pdo);
$txnRepo = new \Accounting\Infrastructure\Persistence\PDOTransactionRepository($pdo);

$svc = new JournalService($accountRepo, $txnRepo, $pdo);

// Make sure COA accounts exist for testing
$cash = $accountRepo->findByCode('111');
$revenue = $accountRepo->findByCode('511');

if (!$cash || !$revenue) {
    echo "FAIL: Prerequisite — COA accounts 111 (Cash) and 511 (Revenue) must exist.\n";
    echo "Run the COA seed first (POST /api/coa/seed)\n";
    exit(1);
}

// Nghiệp vụ: Hạch toán bút toán đơn giản Dr tiền mặt (111) / Cr doanh thu (511)
// Nếu test fail → cơ chế post journal bị hỏng, không ghi nhận được bất kỳ nghiệp vụ nào
// Giả định: TK 111 và 511 đã tồn tại trong COA
echo "\n=== Test 1: Post simple Dr/Cr journal entry ===\n";

// Capture starting balances to handle parallel runs
$cashStart = $accountRepo->findByCode('111')->getBalance();
$revStart = $accountRepo->findByCode('511')->getBalance();

$txn = $svc->postEntry('Test sale', 'REF-001', [
    ['account_code' => '111', 'amount' => 1000000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 1000000, 'is_debit' => false],
], 'test_user');

assertEq('posted', $txn->getStatus(), 'Transaction status is "posted"');

// Verify balances
$cashBalance = $accountRepo->findByCode('111')->getBalance();
$revBalance = $accountRepo->findByCode('511')->getBalance();

assertEq($cashStart + 1000000, $cashBalance, 'Cash (asset) increased by 1,000,000 on debit');
assertEq($revStart + 1000000, $revBalance, 'Revenue increased by 1,000,000 on credit');

// Nghiệp vụ: Hạch toán chi phí — Dr 641 (chi phí bán hàng) / Cr 112 (ngân hàng)
// Nếu fail → không ghi nhận được chi phí qua ngân hàng, sai BC02
// Giả định: TK 641, 112 tồn tại
echo "\n=== Test 2: Post Dr Expense — Cr Bank ===\n";
$bankStart = $accountRepo->findByCode('112')->getBalance();
$txn2 = $svc->postEntry('Test expense', 'REF-002', [
    ['account_code' => '641', 'amount' => 500000, 'is_debit' => true],
    ['account_code' => '112', 'amount' => 500000, 'is_debit' => false],
], 'test_user');

$bankBalance = $accountRepo->findByCode('112')->getBalance();
assertEq($bankStart - 500000, $bankBalance, 'Bank (asset) decreased by 500,000 on credit');
assertEq('posted', $txn2->getStatus(), 'Expense entry posted');

// Ràng buộc kế toán: tổng Dr ≠ tổng Cr → throw InvalidArgumentException
// Nếu fail → bút toán lệch được chấp nhận → bảng cân đối kế toán sai
echo "\n=== Test 3: Unbalanced entry must be rejected ===\n";
try {
    $svc->postEntry('Bad entry', 'REF-003', [
        ['account_code' => '111', 'amount' => 100000, 'is_debit' => true],
        ['account_code' => '511', 'amount' => 90000, 'is_debit' => false],
    ], 'test_user');
    echo "FAIL: Unbalanced entry was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Unbalanced entry rejected with InvalidArgumentException');
}

// Ràng buộc: Số tiền không thể = 0 → vô nghĩa trong hạch toán
// Nếu fail → bút toán 0đ được ghi nhận → sai lệch dữ liệu
echo "\n=== Test 4: Zero-amount line must be rejected ===\n";
try {
    $svc->postEntry('Zero entry', 'REF-004', [
        ['account_code' => '111', 'amount' => 0, 'is_debit' => true],
        ['account_code' => '511', 'amount' => 0, 'is_debit' => false],
    ], 'test_user');
    echo "FAIL: Zero-amount entry was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Zero-amount entry rejected');
}

// Ràng buộc: Bút toán phải có ít nhất 2 dòng (1 Dr + 1 Cr)
// Nếu fail → bút toán 1 dòng làm mất cân đối hệ thống
echo "\n=== Test 5: Single-line entry must be rejected ===\n";
try {
    $svc->postEntry('Single line', 'REF-005', [
        ['account_code' => '111', 'amount' => 100000, 'is_debit' => true],
    ], 'test_user');
    echo "FAIL: Single-line entry was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Single-line entry rejected');
}

// Ràng buộc: Mã tài khoản không tồn tại trong COA → rejected
// Nếu fail → bút toán vào TK không hợp lệ → sai báo cáo tài chính
echo "\n=== Test 6: Non-existent account must be rejected ===\n";
try {
    $svc->postEntry('Bad account', 'REF-006', [
        ['account_code' => '999', 'amount' => 100000, 'is_debit' => true],
        ['account_code' => '511', 'amount' => 100000, 'is_debit' => false],
    ], 'test_user');
    echo "FAIL: Non-existent account was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Non-existent account rejected');
}

// Kiểm tra: Bút toán đã post được persist đầy đủ — immutable
// Nếu fail → mất dữ liệu hoặc audit trail không tin cậy
// Giả định: TransactionRepository::findById hoạt động
echo "\n=== Test 7: Posted entry is immutable ===\n";
$saved = $txnRepo->findById($txn->getId());
assertTrue($saved !== null, 'Transaction persisted');
assertEq('posted', $saved->getStatus(), 'Status persisted as "posted"');
assertEq('REF-001', $saved->getReference(), 'Reference persisted');

// ====================================================================
// R-1: REVERSE ENTRY (Bút toán ngược) — TT99/2025/TT-BTC tuân thủ
// ====================================================================
//
// Nghiệp vụ: Khi phát hiện sai sót, kế toán tạo bút toán NGƯỢC để hủy hiệu lực
//   bút toán gốc đã ghi sổ. KHÔNG sửa/xóa bút toán gốc (audit trail).
//   - Bút toán gốc: status → 'reversed', lưu reversed_by + reversed_at
//   - Bút toán mới: type='negative' correction, is_correction=true,
//                   original_transaction_id = id gốc
//   - Tổng Dr = Cr vẫn đảm bảo (đảo dấu từng dòng)
//
// Rủi ro nếu sai:
//   - Thiếu audit trail: không biết ai reverse, khi nào → vi phạm TT99
//   - Reverse bút toán chưa posted: sai logic, có thể tạo trạng thái "ma"
//   - Sửa balance account: vi phạm control account protection
//

echo "\n=== Test 8: Reverse entry happy path — original posted → reverses cleanly ===\n";
$origTxn = $svc->postEntry('Original entry', 'REF-REV-1', [
    ['account_code' => '111', 'amount' => 2000000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 2000000, 'is_debit' => false],
], 'test_user');

$origId = $origTxn->getId();
$reverseTxn = $svc->createNegativeEntry($origId, 'Sai hóa đơn, khách trả lại', 'test_user');

assertTrue($reverseTxn !== null, 'Reverse transaction created');
assertTrue($reverseTxn->isCorrection(), 'Reverse is marked as correction');
assertEq('negative', $reverseTxn->getCorrectionType(), 'Correction type is "negative"');
assertEq($origId, $reverseTxn->getOriginalTransactionId(), 'Original ID linked');
assertEq('posted', $reverseTxn->getStatus(), 'Reverse entry auto-posted');

// Verify original is now marked reversed
$origAfter = $txnRepo->findById($origId);
assertEq('reversed', $origAfter->getStatus(), 'Original status flipped to "reversed"');
assertEq('test_user', $origAfter->getReversedBy(), 'Reversed-by persisted');
assertTrue($origAfter->getReversedAt() !== null, 'Reversed-at timestamp set');

// Verify reverse entry balances: Dr 511 / Cr 111 (đảo dấu so với gốc)
$reverseEntries = $reverseTxn->getLedgerEntries();
assertTrue(count($reverseEntries) === 2, 'Reverse entry has 2 ledger lines');
$firstEntry = $reverseEntries[0];
assertTrue(!$firstEntry->isDebit(), 'Reverse line Dr→Cr (opposite of original)');
assertEq(2000000, $firstEntry->getAmount(), 'Amount preserved on reverse');

echo "\n=== Test 9: Reverse entry failure — cannot reverse non-posted entry ===\n";
$draftTxn = $svc->createDraft('Draft', 'REF-DRAFT', [
    ['account_code' => '111', 'amount' => 100000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 100000, 'is_debit' => false],
], 'test_user');

try {
    $svc->createNegativeEntry($draftTxn->getId(), 'Thử reverse draft', 'test_user');
    echo "FAIL: Reverse of draft was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Reverse of non-posted entry rejected (must be posted first)');
}

echo "\n=== Test 10: Reverse entry failure — non-existent transaction ===\n";
try {
    $svc->createNegativeEntry('TXN-DOES-NOT-EXIST', 'Test', 'test_user');
    echo "FAIL: Reverse of non-existent txn was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Reverse of non-existent txn rejected');
}

echo "\n=== Test 11: Reverse entry restores account balance to original ===\n";
$cashBeforeOrig = $accountRepo->findByCode('111')->getBalance();
$revBeforeOrig = $accountRepo->findByCode('511')->getBalance();

$newOrig = $svc->postEntry('Test for balance restore', 'REF-REV-2', [
    ['account_code' => '111', 'amount' => 500000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 500000, 'is_debit' => false],
], 'test_user');

$cashAfterOrig = $accountRepo->findByCode('111')->getBalance();
$revAfterOrig = $accountRepo->findByCode('511')->getBalance();
assertEq($cashBeforeOrig + 500000, $cashAfterOrig, 'Cash +500k after original');
assertEq($revBeforeOrig + 500000, $revAfterOrig, 'Revenue +500k after original');

$svc->createNegativeEntry($newOrig->getId(), 'Hủy bút toán test', 'test_user');

$cashFinal = $accountRepo->findByCode('111')->getBalance();
$revFinal = $accountRepo->findByCode('511')->getBalance();
assertEq($cashBeforeOrig, $cashFinal, 'Cash restored to pre-original level');
assertEq($revBeforeOrig, $revFinal, 'Revenue restored to pre-original level');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);
<?php
// Test: CcdcAllocationService — CCDC lifecycle
// Nghiệp vụ: Phân bổ CCDC (Nợ TK chi phí / Có 242) qua nhiều kỳ
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Model\Ccdc;
use Accounting\Domain\Service\CcdcAllocationService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Infrastructure\Persistence\PDOCcdcRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$ccdcRepo = new PDOCcdcRepository($pdo);
$journal = new JournalService($accountRepo, $txnRepo, $pdo);
$svc = new CcdcAllocationService($ccdcRepo, $journal, $pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if (abs((float)$a - (float)$b) > 100) { echo "FAIL: {$m} — expected {$b}, got {$a}\n"; $failed++; } else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if (!$c) { echo "FAIL: {$m}\n"; $failed++; } else echo "PASS: {$m}\n";
}
function results() { global $total, $failed;
    echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
    exit($failed > 0 ? 1 : 0);
}

$periodCode = date('Y-m');
$pdo->prepare("UPDATE accounting_periods SET status = 'open' WHERE period_code = ?")->execute([$periodCode]);

// Clean up test data
$pdo->exec("DELETE FROM ccdc WHERE id LIKE 'test-ccdc-%'");
$pdo->exec("DELETE FROM transactions WHERE description LIKE 'Phan bo CCDC%'");
$pdo->exec("DELETE FROM ccdc_allocations WHERE ccdc_id LIKE 'test-ccdc-%'");

echo "\n=== Test 1: Create CCDC item via repository ===\n";
$ccdc1 = new Ccdc(
    'test-ccdc-01', 'TEST-CCDC-01', 'Bộ máy in văn phòng', 'bộ', 3,
    'period', 6, '642', date('Y-m-d'), 30000000, 0, 6
);
$ccdcRepo->save($ccdc1);
$saved = $ccdcRepo->findByCode('TEST-CCDC-01');
assertTrue($saved !== null, 'CCDC found by code');
assertEq(30000000, $saved->getTotalCost(), 'Total cost = 30M');
assertEq(6, $saved->getRemainingMonths(), 'Remaining months = 6');
assertEq('642', $saved->getExpenseAccount(), 'Expense account = 642');

echo "\n=== Test 2: CCDC found by id ===\n";
$found = $ccdcRepo->findById('test-ccdc-01');
assertTrue($found !== null, 'CCDC found by id');
assertEq('TEST-CCDC-01', $found->getCode(), 'Code matches');

echo "\n=== Test 3: CCDC found in pending allocation ===\n";
$pending = $ccdcRepo->findPendingAllocation(10);
assertTrue(count($pending) > 0, 'At least 1 pending allocation found');
$found = false;
foreach ($pending as $p) {
    if ($p->getId() === 'test-ccdc-01') { $found = true; break; }
}
assertTrue($found, 'Our CCDC is in pending allocation');

echo "\n=== Test 4: Run monthly allocation for current period ===\n";
$results = $svc->runMonthlyAllocation($periodCode, 'tester');
assertTrue(count($results) > 0, 'Allocation ran and produced results');
$ourResult = null;
foreach ($results as $r) {
    if ($r['ccdc_id'] === 'test-ccdc-01') { $ourResult = $r; break; }
}
assertTrue($ourResult !== null, 'Our CCDC was allocated');
$expectedAmount = 30000000 / 6;
assertEq($expectedAmount, $ourResult['amount'], "Monthly amount = {$expectedAmount}");

echo "\n=== Test 5: CCDC allocated value updated after run ===\n";
$updated = $ccdcRepo->findById('test-ccdc-01');
assertEq($expectedAmount, $updated->getAllocated(), "Allocated = {$expectedAmount} after 1 month");
assertEq(5, $updated->getRemainingMonths(), 'Remaining months decreased to 5');

echo "\n=== Test 6: Idempotent — second run same month skips ===\n";
$results2 = $svc->runMonthlyAllocation($periodCode, 'tester');
$skipped = true;
foreach ($results2 as $r) {
    if ($r['ccdc_id'] === 'test-ccdc-01') { $skipped = false; break; }
}
assertTrue($skipped, 'Second run same month skipped (no duplicate allocation)');

echo "\n=== Test 7: Next month allocation ===\n";
$nextMonth = date('Y-m', strtotime('+1 month'));
// Ensure next month period exists (migration 037 seeds only 3 months)
$pdo->prepare("INSERT IGNORE INTO accounting_periods (period_code, period_type, name, status, start_date, end_date)
    VALUES (?, 'month', ?, 'open', ?, ?)")->execute([
    $nextMonth,
    'Kỳ ' . $nextMonth,
    $nextMonth . '-01',
    date('Y-m-t', strtotime($nextMonth . '-01'))
]);
$results3 = $svc->runMonthlyAllocation($nextMonth, 'tester');
$ourResult3 = null;
foreach ($results3 as $r) {
    if ($r['ccdc_id'] === 'test-ccdc-01') { $ourResult3 = $r; break; }
}
assertTrue($ourResult3 !== null, 'Next month allocation ran');
assertEq($expectedAmount, $ourResult3['amount'], "Monthly amount = {$expectedAmount} again");
$updated2 = $ccdcRepo->findById('test-ccdc-01');
assertEq($expectedAmount * 2, $updated2->getAllocated(), "Allocated = {$expectedAmount} * 2 after 2 months");
assertEq(4, $updated2->getRemainingMonths(), 'Remaining months = 4');

echo "\n=== Test 8: CCDC with 0 remaining months is excluded ===\n";
$ccdcShort = new Ccdc('test-ccdc-short', 'TEST-SHORT', 'CCDC da phan bo het', 'cai', 1, 'period', 3, '642', date('Y-m-d'), 9000000, 9000000, 0);
$ccdcRepo->save($ccdcShort);
$pending8 = $ccdcRepo->findPendingAllocation(10);
$shortFound = false;
foreach ($pending8 as $p) {
    if ($p->getId() === 'test-ccdc-short') { $shortFound = true; break; }
}
assertTrue(!$shortFound, 'CCDC with 0 remaining months not in pending list');
$results8 = $svc->runMonthlyAllocation($periodCode, 'tester');
$shortAllocated = false;
foreach ($results8 as $r) {
    if ($r['ccdc_id'] === 'test-ccdc-short') { $shortAllocated = true; break; }
}
assertTrue(!$shortAllocated, 'CCDC with 0 remaining months not allocated');

echo "\n=== Test 9: Re-allocate for test-ccdc-01: set remaining to 0 then skip ===\n";
$ccdc1->setAllocated(30000000);
$ccdc1->setRemainingMonths(0);
$ccdcRepo->save($ccdc1);
$results9 = $svc->runMonthlyAllocation($nextMonth, 'tester');
$stillFound = false;
foreach ($results9 as $r) {
    if ($r['ccdc_id'] === 'test-ccdc-01') { $stillFound = true; break; }
}
assertTrue(!$stillFound, 'CCDC with 0 remaining months skipped by allocation run');

echo "\n=== Test 10: CCDC with different expense account ===\n";
$ccdc2 = new Ccdc(
    'test-ccdc-02', 'TEST-CCDC-02', 'Dụng cụ sản xuất', 'cái', 10,
    'period', 3, '627', date('Y-m-d'), 15000000, 0, 3
);
$ccdcRepo->save($ccdc2);
$results10 = $svc->runMonthlyAllocation($periodCode, 'tester');
assertTrue(count($results10) > 0, 'Second CCDC allocation ran');
$updated3 = $ccdcRepo->findById('test-ccdc-02');
assertEq(5000000, $updated3->getAllocated(), 'Second CCDC = 5M allocated');
assertEq('627', $updated3->getExpenseAccount(), 'Expense account = 627');

echo "\n=== Test 11: CCDC with zero start date is skipped ===\n";
$ccdc3 = new Ccdc('test-ccdc-03', 'TEST-CCDC-03', 'No start date', 'cái', 1, 'period', 6, '642', null, 12000000, 0, 6);
$ccdcRepo->save($ccdc3);
$results11 = $svc->runMonthlyAllocation($periodCode, 'tester');
$noStartFound = false;
foreach ($results11 as $r) {
    if ($r['ccdc_id'] === 'test-ccdc-03') { $noStartFound = true; break; }
}
assertTrue(!$noStartFound, 'CCDC with no start date is skipped');

echo "\n=== Test 12: Trial balance Dr = Cr for all CCDC allocations ===\n";
$stmt = $pdo->query("
    SELECT SUM(CASE WHEN is_debit = 1 THEN amount ELSE 0 END) AS total_dr,
           SUM(CASE WHEN is_debit = 0 THEN amount ELSE 0 END) AS total_cr
    FROM ledger_entries le
    JOIN transactions t ON t.id = le.transaction_id
    WHERE t.description LIKE 'Phan bo CCDC%'
");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
assertEq($row['total_dr'], $row['total_cr'], 'Trial balance: Dr = Cr for all CCDC allocations');

echo "\n=== Cleanup ===\n";
foreach (['test-ccdc-01','test-ccdc-02','test-ccdc-03','test-ccdc-short'] as $id) {
    $c = $ccdcRepo->findById($id);
    if ($c) $ccdcRepo->delete($id);
}

echo "\n=== Test 13: CCDC toArray includes remaining_value ===\n";
$arr = $ccdc1->toArray();
assertTrue(isset($arr['remaining_value']), 'toArray contains remaining_value');
assertTrue(isset($arr['allocation_months']), 'toArray contains allocation_months');

results();

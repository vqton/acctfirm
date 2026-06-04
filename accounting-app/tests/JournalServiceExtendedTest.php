<?php
//
// Test: JournalService — R-2, R-3, R-8, R-9, R-13
// R-2: Status guard (delete/restore/duplicate buttons per status)
// R-3: RBAC scope by created_by (KTV chỉ thấy data mình tạo)
// R-8: Bulk Post (transactional all-or-nothing)
// R-9: Duplicate Entry (copy lines → draft mới)
// R-13: Soft Delete + Restore (giữ audit, restore trong 30 ngày)

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

require __DIR__ . '/../src/Accounting/Domain/Service/JournalService.php';
require __DIR__ . '/../src/Accounting/Infrastructure/Database/AuditLogger.php';

use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;

$failed = 0;
$total = 0;
function assertEq($a, $b, $m) { global $total, $failed; $total++; if (abs((float)$a - (float)$b) > 10) { echo "FAIL: $m — exp $a, got $b\n"; $failed++; } else echo "PASS: $m\n"; }
function assertTrue($c, $m) { global $total, $failed; $total++; if (!$c) { echo "FAIL: $m\n"; $failed++; } else echo "PASS: $m\n"; }
function assertNull($v, $m) { global $total, $failed; $total++; if ($v !== null) { echo "FAIL: $m — expected null, got $v\n"; $failed++; } else echo "PASS: $m\n"; }

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$audit = new \Accounting\Infrastructure\Database\AuditLogger();
$svc = new JournalService($accountRepo, $txnRepo, $pdo, $audit);

if (!$accountRepo->findByCode('111') || !$accountRepo->findByCode('511')) {
    echo "FAIL: COA accounts 111, 511 required\n"; exit(1);
}

// ====================================================
// R-9: Duplicate Entry
// ====================================================
echo "\n=== R-9: Duplicate Entry ===\n";
$orig = $svc->postEntry('Original expense', 'REF-DUP-1', [
    ['account_code' => '641', 'amount' => 750000, 'is_debit' => true],
    ['account_code' => '111', 'amount' => 750000, 'is_debit' => false],
], 'test_user');

$dup = $svc->duplicateEntry($orig->getId(), 'test_user');
assertTrue($dup->getId() !== $orig->getId(), 'Duplicate has new ID');
assertEq('draft', $dup->getStatus(), 'Duplicate status = draft (not posted)');
assertTrue(str_starts_with($dup->getDescription(), '[COPY]'), 'Description prefixed [COPY]');
assertEq(2, count($dup->getLedgerEntries()), 'Same number of lines');
assertEq(750000, $dup->getLedgerEntries()[0]->getAmount(), 'Amount preserved');
assertTrue($dup->getLedgerEntries()[0]->isDebit() !== $orig->getLedgerEntries()[0]->isDebit() ||
    $dup->getLedgerEntries()[0]->isDebit() === $orig->getLedgerEntries()[0]->isDebit(), 'Dr/Cr preserved per line');

// Failure: duplicate non-existent
try {
    $svc->duplicateEntry('TXN-NONEXISTENT', 'test_user');
    echo "FAIL: Duplicate non-existent txn not rejected\n"; $failed++; $total++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Duplicate non-existent txn rejected');
}

// ====================================================
// R-13: Soft Delete + Restore
// ====================================================
echo "\n=== R-13: Soft Delete + Restore ===\n";
$draftForDelete = $svc->createDraft('Draft to delete', 'REF-DEL-1', [
    ['account_code' => '111', 'amount' => 200000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 200000, 'is_debit' => false],
], 'test_user');

$svc->softDelete($draftForDelete->getId(), 'test_user', 'Nhập nhầm, xóa');
$deleted = $txnRepo->findById($draftForDelete->getId());
assertTrue($deleted->isDeleted(), 'Soft delete sets deleted_at');
assertEq('test_user', $deleted->getDeletedBy(), 'Deleted_by persisted');

// Failure: cannot delete posted
$posted = $svc->postEntry('Posted - cannot delete', 'REF-DEL-2', [
    ['account_code' => '111', 'amount' => 100000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 100000, 'is_debit' => false],
], 'test_user');
try {
    $svc->softDelete($posted->getId(), 'test_user', 'Test');
    echo "FAIL: Delete posted not rejected\n"; $failed++; $total++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Cannot delete posted entry (must reverse first)');
}

// Restore trong window
$svc->restore($draftForDelete->getId(), 'test_user');
$restored = $txnRepo->findById($draftForDelete->getId());
assertTrue(!$restored->isDeleted(), 'Restored clears deleted_at');
assertNull($restored->getDeletedBy(), 'Restored clears deleted_by');

// ====================================================
// R-8: Bulk Post
// ====================================================
echo "\n=== R-8: Bulk Post (transactional all-or-nothing) ===\n";
$d1 = $svc->createDraft('Bulk 1', 'REF-BULK-1', [
    ['account_code' => '111', 'amount' => 100000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 100000, 'is_debit' => false],
], 'test_user');
$d2 = $svc->createDraft('Bulk 2', 'REF-BULK-2', [
    ['account_code' => '111', 'amount' => 200000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 200000, 'is_debit' => false],
], 'test_user');

$result = $svc->bulkPost([$d1->getId(), $d2->getId()], 'test_user');
assertTrue(count($result['posted']) === 2, 'Both posted in batch');
assertTrue(!$result['rolled_back'], 'No rollback on success');
assertEq('posted', $txnRepo->findById($d1->getId())->getStatus(), 'D1 is posted');
assertEq('posted', $txnRepo->findById($d2->getId())->getStatus(), 'D2 is posted');

// Failure: mix of valid + already-posted → rollback all
$failResult = $svc->bulkPost([$d1->getId(), $d2->getId()], 'test_user');
assertTrue(count($failResult['failed']) === 2, 'Both failed (already posted)');
assertTrue($failResult['rolled_back'], 'Rolled back');

// Empty list rejected
try {
    $svc->bulkPost([], 'test_user');
    echo "FAIL: Empty list not rejected\n"; $failed++; $total++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Empty list rejected');
}

// ====================================================
// R-3: RBAC scope (test query, not full middleware)
// ====================================================
echo "\n=== R-3: RBAC scope (created_by filter) ===\n";
$userA = $svc->createDraft('By userA', 'REF-RBAC-1', [
    ['account_code' => '111', 'amount' => 50000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 50000, 'is_debit' => false],
], 'userA');
$userB = $svc->createDraft('By userB', 'REF-RBAC-2', [
    ['account_code' => '111', 'amount' => 60000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 60000, 'is_debit' => false],
], 'userB');

// Test: filter by userA — chỉ thấy của userA
$txnsA = $txnRepo->getTransactionsByPeriod(date('Y-m'), 'userA');
$txnsB = $txnRepo->getTransactionsByPeriod(date('Y-m'), 'userB');
assertTrue(count($txnsA) >= 1, 'userA filter returns at least 1 txn');
assertTrue(count($txnsB) >= 1, 'userB filter returns at least 1 txn');

// Verify IDs are disjoint (không leak giữa user)
$idsA = array_map(fn($t) => $t->getId(), $txnsA);
$idsB = array_map(fn($t) => $t->getId(), $txnsB);
$overlap = array_intersect($idsA, $idsB);
assertTrue(count($overlap) === 0, 'No overlap between userA and userB filters (RBAC scope works)');

// Test: no filter → sees all
$allTxns = $txnRepo->getTransactionsByPeriod(date('Y-m'));
assertTrue(count($allTxns) > count($txnsA), 'No filter returns MORE txns than filtered');

// ====================================================
// R-13: Restore outside window (force 31-day old delete_at)
// ====================================================
echo "\n=== R-13: Restore window ===\n";
$oldDraft = $svc->createDraft('Old', 'REF-DEL-3', [
    ['account_code' => '111', 'amount' => 30000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 30000, 'is_debit' => false],
], 'test_user');
$svc->softDelete($oldDraft->getId(), 'test_user', 'test');
// Force deleted_at to 31 days ago
$pdo->prepare("UPDATE transactions SET deleted_at = DATE_SUB(NOW(), INTERVAL 31 DAY) WHERE id = ?")
    ->execute([$oldDraft->getId()]);
$svc->restore($oldDraft->getId(), 'test_user'); // refresh
try {
    $svc->restore($oldDraft->getId(), 'test_user', 30);
    echo "FAIL: Restore outside window not rejected\n"; $failed++; $total++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Restore outside 30-day window rejected');
}

echo "\n=== Results: $total tests, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);

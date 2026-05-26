<?php
// Test: Phê duyệt bút toán — quy trình duyệt chứng từ
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Model\Transaction;
use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Service\PostingRuleService;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// === Test 1: Transaction state machine transitions ===
$txn = new Transaction('test_01', new \DateTimeImmutable(), 'Test', 'JV001');
assertEq($txn->getStatus(), 'pending', 'Default status = pending');

$txn->submit();
assertEq($txn->getStatus(), 'submitted', 'After submit = submitted');

$txn->approve();
assertEq($txn->getStatus(), 'approved', 'After approve = approved');

$txn->post('tester');
assertEq($txn->getStatus(), 'posted', 'After post = posted');

$txn->reverse('tester');
assertEq($txn->getStatus(), 'reversed', 'After reverse = reversed');

echo "\n";

// === Test 2: Invalid transitions throw ===
// Ràng buộc: Không thể approve từ trạng thái pending (phải submit trước)
// Không thể submit hai lần → mỗi bước chỉ được thực hiện 1 lần
// Nếu fail → luồng phê duyệt bút toán không chặt chẽ → audit fail
$txn2 = new Transaction('test_02', new \DateTimeImmutable(), 'Test2', 'JV002');
$threw = false;
try { $txn2->approve(); } catch (\InvalidArgumentException $e) { $threw = true; }
assertTrue($threw, 'Cannot approve from pending (must submit first)');

$txn3 = new Transaction('test_03', new \DateTimeImmutable(), 'Test3', 'JV003');
$txn3->submit();
$threw = false;
try { $txn3->submit(); } catch (\InvalidArgumentException $e) { $threw = true; }
assertTrue($threw, 'Cannot submit twice');

echo "\n";

// === Test 3: Reject and return to draft ===
// Nghiệp vụ: Từ chối phê duyệt → chuyển về trạng thái draft (pending) để sửa
// Nếu fail → không thể từ chối và quay lại sửa chứng từ
$txn4 = new Transaction('test_04', new \DateTimeImmutable(), 'Test4', 'JV004');
$txn4->submit();
$txn4->reject();
assertEq($txn4->getStatus(), 'rejected', 'After reject = rejected');
$txn4->returnToDraft();
assertEq($txn4->getStatus(), 'pending', 'After return to draft = pending');

echo "\n";

// === Test 4: Integration with JournalService (submit/approve) ===
$accountRepo = new Accounting\Infrastructure\Persistence\PDOAccountRepository($pdo);
$txnRepo = new Accounting\Infrastructure\Persistence\PDOTransactionRepository($pdo);
$auditLogger = new Accounting\Infrastructure\Database\AuditLogger($pdo);
$postingRuleService = new PostingRuleService($pdo);
$svc = new JournalService($accountRepo, $txnRepo, $pdo, $auditLogger, $postingRuleService);

// Create draft
$txn = $svc->createDraft('Test approval flow', 'JV001', [
    ['account_code' => '111', 'amount' => 100000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 100000, 'is_debit' => false],
], 'tester');
assertEq($txn->getStatus(), 'pending', 'Created as pending');
$id = $txn->getId();

// Submit
$txn = $svc->submitEntry($id, 'tester');
assertEq($txn->getStatus(), 'submitted', 'After submit = submitted');

// Approve
$txn = $svc->approveEntry($id, 'approver', 'Approved');
assertEq($txn->getStatus(), 'approved', 'After approve = approved');

// Post
$txn = $svc->postEntry('Posted after approve', 'JV002', [
    ['account_code' => '111', 'amount' => 50000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 50000, 'is_debit' => false],
], 'tester');

echo "\n";

// === Test 5: Check approval records in DB ===
$stmt = $pdo->prepare('SELECT * FROM journal_entry_approvals WHERE transaction_id = ? ORDER BY created_at');
$stmt->execute([$id]);
$records = $stmt->fetchAll();
assertTrue(count($records) >= 2, 'At least 2 approval records (submit + approve)');
assertEq($records[0]['action'], 'submit', 'First record = submit');
assertEq($records[1]['action'], 'approve', 'Second record = approve');

echo "\n";

// Cleanup test transactions
$pdo->exec("DELETE le FROM ledger_entries le JOIN transactions t ON le.transaction_id = t.id WHERE t.id LIKE 'test_%' OR t.reference = 'JV001' OR t.reference = 'JV002'");
$pdo->exec("DELETE FROM transactions WHERE id LIKE 'test_%' OR reference = 'JV001' OR reference = 'JV002'");
$pdo->exec("DELETE FROM journal_entry_approvals WHERE transaction_id LIKE 'test_%'");

results();

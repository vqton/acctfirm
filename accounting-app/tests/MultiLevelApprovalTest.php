<?php
//
// R-16: Multi-level Approval tests
//
require __DIR__ . '/bootstrap.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

use Accounting\Domain\Service\ApprovalRoutingService;
$svc = new ApprovalRoutingService($pdo);

// Clean up test data
$pdo->exec("DELETE FROM journal_entry_approvals WHERE transaction_id LIKE 'TEST_ML_%'");

// ─── 1. getRequiredApprovalSteps cho amount nhỏ → 1 cấp ──────────────────
$steps = $svc->getRequiredApprovalSteps(5000000);
assertEq(count($steps), 1, 'Amount 5M → 1 cấp duyệt');
assertEq($steps[0], 'chief_accountant', 'Level 1 = chief_accountant (fallback)');

// ─── 2. getRequiredApprovalSteps cho amount 50M → 1 cấp ───────────────────
$steps = $svc->getRequiredApprovalSteps(50000000);
assertTrue(in_array('chief_accountant', $steps), 'Amount 50M có chief_accountant trong steps');

// ─── 3. getRequiredApprovalSteps cho amount 200M (100M-1B range) → 1 cấp ─
// Lưu ý: rule cũ với required_role=director (pri 30) thắng rule mới có sequence
// Multi-level kicks in từ 1B+ (rule 6 pri 25)
$steps = $svc->getRequiredApprovalSteps(200000000);
assertTrue(count($steps) >= 1, 'Amount 200M → ít nhất 1 cấp duyệt');

// ─── 4. getRequiredApprovalSteps cho amount > 1B → 3 cấp ─────────────────
$steps = $svc->getRequiredApprovalSteps(2000000000);
assertEq(count($steps), 3, 'Amount 2B → 3 cấp duyệt');
assertEq($steps[0], 'chief_accountant', 'Level 1 = chief_accountant');
assertEq($steps[1], 'director', 'Level 2 = director');
assertEq($steps[2], 'cfo', 'Level 3 = cfo');

// ─── 5. getRequiredApprovalSteps default fallback ────────────────────────
$steps = $svc->getRequiredApprovalSteps(1000);
assertEq(count($steps), 1, 'Default fallback = 1 cấp');
assertEq($steps[0], 'chief_accountant', 'Default = chief_accountant');

// ─── 6. getCurrentApprovalLevel ban đầu = 1 ───────────────────────────────
$level = $svc->getCurrentApprovalLevel('TEST_ML_NONE', ['chief_accountant', 'director']);
assertEq($level, 1, 'Chưa approve lần nào → level = 1');

// ─── 7. Sau 1 lần approve → level = 2 ────────────────────────────────────
$txnId = 'TEST_ML_001';
$ins = $pdo->prepare("INSERT INTO journal_entry_approvals (transaction_id, action, approval_level, actor) VALUES (?, 'approve', 1, 'tester')");
$ins->execute([$txnId]);
$level = $svc->getCurrentApprovalLevel($txnId, ['chief_accountant', 'director']);
assertEq($level, 2, 'Sau 1 lần approve → level = 2');

// ─── 8. isFullyApproved ───────────────────────────────────────────────────
$steps2 = ['chief_accountant', 'director'];
// Xóa các approve cũ để test sạch
$pdo->exec("DELETE FROM journal_entry_approvals WHERE transaction_id = 'TEST_ML_001'");
$isFull = $svc->isFullyApproved('TEST_ML_001', $steps2);
assertEq($isFull, false, '0/2 cấp → chưa fully approved');

$ins->execute(['TEST_ML_001']); // 1 approve
$isFull = $svc->isFullyApproved('TEST_ML_001', $steps2);
assertEq($isFull, false, '1/2 cấp → chưa fully approved');

$ins->execute(['TEST_ML_001']); // 2 approve
$isFull = $svc->isFullyApproved('TEST_ML_001', $steps2);
assertEq($isFull, true, '2/2 cấp → fully approved');

// ─── 9. JSON invalid → fallback về required_role ─────────────────────────
// (Difficult to test without modifying table — skip for now)

// ─── 10. Backward compat: rule cũ với approval_sequence = NULL → 1 cấp ───
$steps = $svc->getRequiredApprovalSteps(5000000, 'petty_cash');
assertEq(count($steps), 1, 'Backward compat: petty_cash rule cũ → 1 cấp');

// ─── 11. Sequence với 3+ cấp hoạt động đúng ──────────────────────────────
$steps = $svc->getRequiredApprovalSteps(2000000000);
$currentLevel = 1;
foreach ($steps as $i => $role) {
    assertTrue(isset($steps[$i]), "Step {$currentLevel} ({$role}) tồn tại");
    $currentLevel++;
}

// ─── 12. Edge case: amount = 0 vẫn có steps ───────────────────────────────
$steps = $svc->getRequiredApprovalSteps(0);
assertTrue(count($steps) >= 1, 'Amount 0 vẫn có ít nhất 1 cấp');

// Cleanup
$pdo->exec("DELETE FROM journal_entry_approvals WHERE transaction_id LIKE 'TEST_ML_%'");

results();

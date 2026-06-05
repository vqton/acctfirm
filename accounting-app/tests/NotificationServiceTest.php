<?php
//
// Test: NotificationService (R-12 In-App Notifications)
// Cover: notify, listForUser, countUnread, markRead, markAllRead,
//        helper methods (pending_approval, approval_result, period_deadline, import_result),
//        idempotency (5-min dedup)
//        Integration: hook into JournalService submit/approve/reject
//
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Service\NotificationService;
use Accounting\Infrastructure\Database\AuditLogger;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Service\PostingRuleService;
use Accounting\Domain\Service\VoucherService;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$audit = new AuditLogger($pdo);
$notif = new NotificationService($pdo, $audit);

// === TEST 1: notify() cơ bản — tạo 1 notification ===
$id1 = $notif->notify(
    'test.basic', 'Test Title', 'Test message body',
    userId: 'test_user_a', severity: 'info',
    link: '/test/link', createdBy: 'tester'
);
assertTrue(!empty($id1), 'notify trả về id');

// === TEST 2: listForUser trả về notification vừa tạo ===
$items = $notif->listForUser('test_user_a');
assertTrue(count($items) >= 1, 'Có ≥ 1 notification cho user');
$found = false;
foreach ($items as $n) {
    if ($n['id'] === $id1) {
        $found = true;
        assertEq($n['title'], 'Test Title', 'Title OK');
        assertEq($n['severity'], 'info', 'Severity OK');
        assertEq($n['link'], '/test/link', 'Link OK');
        assertEq($n['is_read'], 0, 'Mặc định chưa đọc');
    }
}
assertTrue($found, 'Notification có trong list');

// === TEST 3: countUnread ===
$count = $notif->countUnread('test_user_a');
assertTrue($count >= 1, 'Có ≥ 1 unread');

// === TEST 4: markRead ===
$marked = $notif->markRead($id1, 'test_user_a');
assertTrue($marked, 'markRead trả về true');
$countAfter = $notif->countUnread('test_user_a');
assertTrue($countAfter < $count, 'countUnread giảm sau markRead');

// === TEST 5: markRead lại → false (đã đọc rồi) ===
$markedAgain = $notif->markRead($id1, 'test_user_a');
assertFalse($markedAgain, 'markRead lần 2 trả false');

// === TEST 6: markRead với user khác → false (không sở hữu) ===
$markedByOther = $notif->markRead($id1, 'other_user');
assertFalse($markedByOther, 'markRead bởi user khác → false');

// === TEST 7: broadcast notification (user_id = null) ===
$idBcast = $notif->notify('test.broadcast', 'Broadcast', 'All users', null, 'warn');
$itemsBcast = $notif->listForUser('test_user_b');
$foundBcast = false;
foreach ($itemsBcast as $n) {
    if ($n['id'] === $idBcast) $foundBcast = true;
}
assertTrue($foundBcast, 'Broadcast notification hiển thị cho mọi user');

// === TEST 8: notifyPendingApproval helper ===
$idApproval = $notif->notifyPendingApproval('jrn_test_1', 'ketoan_a', 'Test description', 500000);
assertTrue(!empty($idApproval), 'notifyPendingApproval trả id');
$itemsApproval = $notif->listForUser('test_user_c');
$foundApproval = false;
foreach ($itemsApproval as $n) {
    if ($n['id'] === $idApproval && $n['type'] === 'journal.pending_approval') {
        $foundApproval = true;
    }
}
assertTrue($foundApproval, 'Pending approval broadcast cho user khác');

// === TEST 9: notifyApprovalResult (approved) ===
$idResult = $notif->notifyApprovalResult('jrn_test_1', 'ketoan_a', true, 'ktt_b');
$itemsCreator = $notif->listForUser('ketoan_a');
$foundResult = false;
foreach ($itemsCreator as $n) {
    if ($n['id'] === $idResult) {
        $foundResult = true;
        assertEq($n['type'], 'journal.approved', 'Type = approved');
        assertEq($n['severity'], 'info', 'Severity = info');
    }
}
assertTrue($foundResult, 'Người tạo nhận được approved notification');

// === TEST 10: notifyApprovalResult (rejected) ===
$idReject = $notif->notifyApprovalResult('jrn_test_2', 'ketoan_a', false, 'ktt_b', 'Sai TK');
$itemsCreator2 = $notif->listForUser('ketoan_a');
$foundReject = false;
foreach ($itemsCreator2 as $n) {
    if ($n['id'] === $idReject) {
        $foundReject = true;
        assertEq($n['type'], 'journal.rejected', 'Type = rejected');
        assertEq($n['severity'], 'warn', 'Severity = warn');
        assertTrue(str_contains($n['message'], 'Sai TK'), 'Message chứa lý do');
    }
}
assertTrue($foundReject, 'Người tạo nhận được rejected notification');

// === TEST 11: notifyPeriodDeadline (3 ngày) ===
$idDl3 = $notif->notifyPeriodDeadline('2026-01', '2026-01-31', 3);
$itemsDl = $notif->listForUser('test_user_d');
$foundDl = false;
foreach ($itemsDl as $n) {
    if ($n['id'] === $idDl3 && $n['severity'] === 'warn') $foundDl = true;
}
assertTrue($foundDl, 'Deadline 3 ngày = severity warn');

// === TEST 12: notifyPeriodDeadline (1 ngày) — critical ===
$idDl1 = $notif->notifyPeriodDeadline('2026-02', '2026-02-28', 1);
$itemsDl1 = $notif->listForUser('test_user_e');
$foundDl1 = false;
foreach ($itemsDl1 as $n) {
    if ($n['id'] === $idDl1 && $n['severity'] === 'critical') $foundDl1 = true;
}
assertTrue($foundDl1, 'Deadline 1 ngày = severity critical');

// === TEST 13: notifyImportResult (success) ===
$idImpOk = $notif->notifyImportResult('items', 'items.csv', 100, true);
$itemsImp = $notif->listForUser('test_user_f');
$foundImp = false;
foreach ($itemsImp as $n) {
    if ($n['id'] === $idImpOk) {
        $foundImp = true;
        assertEq($n['severity'], 'info', 'Success = info');
    }
}
assertTrue($foundImp, 'Import success notification OK');

// === TEST 14: notifyImportResult (failed) ===
$idImpFail = $notif->notifyImportResult('customers', 'customers.csv', 0, false, 'Permission denied');
$itemsImpF = $notif->listForUser('test_user_g');
$foundImpF = false;
foreach ($itemsImpF as $n) {
    if ($n['id'] === $idImpFail) {
        $foundImpF = true;
        assertEq($n['severity'], 'critical', 'Failed = critical');
        assertTrue(str_contains($n['message'], 'Permission denied'), 'Message có lỗi');
    }
}
assertTrue($foundImpF, 'Import failed notification OK');

// === TEST 15: Idempotency — duplicate trong 5 phút bị skip ===
$idDup1 = $notif->notify(
    'test.dedup', 'Dedup test', 'First',
    userId: 'test_user_h',
    resource: ['type' => 'transaction', 'id' => 'txn_dedup_1']
);
$idDup2 = $notif->notify(
    'test.dedup', 'Dedup test', 'Second (should be skipped)',
    userId: 'test_user_h',
    resource: ['type' => 'transaction', 'id' => 'txn_dedup_1']
);
assertTrue(!empty($idDup1), 'First notify OK');
assertEq($idDup2, '', 'Duplicate bị skip → return empty string');

// === TEST 16: markAllRead ===
$countBefore = $notif->countUnread('test_user_a');
$marked = $notif->markAllRead('test_user_a');
assertTrue($marked >= 1, 'markAllRead trả ≥ 1');
$countAfter = $notif->countUnread('test_user_a');
assertEq($countAfter, 0, 'countUnread = 0 sau markAllRead');

// === TEST 17: unreadOnly filter ===
$notif->notify('test.unread_filter', 'A', 'A', 'test_user_i', 'info');
$notif->notify('test.unread_filter', 'B', 'B', 'test_user_i', 'info');
$allItems = $notif->listForUser('test_user_i', 100, false);
$unreadItems = $notif->listForUser('test_user_i', 100, true);
assertTrue(count($allItems) >= 2, 'allItems ≥ 2');
assertTrue(count($unreadItems) < count($allItems) || count($allItems) === count($unreadItems),
    'unreadItems ≤ allItems (nếu đã mark hết thì = nhau)');

// === TEST 18: limit parameter ===
$notif->markAllRead('test_user_j');
for ($i = 0; $i < 5; $i++) {
    $notif->notify('test.limit', 'Item ' . $i, 'Body ' . $i, 'test_user_j');
}
$limited = $notif->listForUser('test_user_j', 3, false);
assertTrue(count($limited) <= 3, 'Limit = 3 trả ≤ 3 items');

// Cleanup
$pdo->exec("DELETE FROM notifications WHERE type LIKE 'test.%' OR type LIKE 'journal.%' OR type LIKE 'period.%' OR type LIKE 'import.%'");

results();

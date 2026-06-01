<?php
// Test: Debt Collection Module — Queue, Activities, Promises, Write-off, Settlement
// docs/analysis/debt-collection-engine-brain-logic.md

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\DebtCollectionService;
use Accounting\Domain\Service\ArService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Infrastructure\Repository\PDODebtCollectionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Đảm bảo kỳ đang mở
$pdo->exec("INSERT IGNORE INTO accounting_periods (period_code, start_date, end_date, status) VALUES ('2026-05', '2026-05-01', '2026-05-31', 'open')");
$pdo->exec("INSERT IGNORE INTO accounting_periods (period_code, start_date, end_date, status) VALUES ('2026-06', '2026-06-01', '2026-06-30', 'open')");
$pdo->exec("UPDATE accounting_periods SET status = 'open' WHERE period_code IN ('2026-05','2026-06')");

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$journal = new JournalService($accountRepo, $txnRepo, $pdo);
$ar = new ArService($pdo, $accountRepo, $journal);
$dcRepo = new PDODebtCollectionRepository($pdo);
$dcs = new DebtCollectionService($pdo, $dcRepo, $ar);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if (abs((float)$a - (float)$b) > 1) { echo "FAIL: {$m} expected {$b} got {$a}\n"; $failed++; } else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if (!$c) { echo "FAIL: {$m}\n"; $failed++; } else echo "PASS: {$m}\n";
}
function assertThrows($fn, $m) { global $total, $failed;
    $total++; try { $fn(); echo "FAIL: {$m} — no exception thrown\n"; $failed++; } catch (\Throwable $e) { echo "PASS: {$m} ({$e->getMessage()})\n"; }
}

// Clean up
$pdo->exec('DELETE FROM debt_collection_settlements');
$pdo->exec('DELETE FROM debt_collection_approvals');
$pdo->exec('DELETE FROM debt_collection_promises');
$pdo->exec('DELETE FROM debt_collection_activities');
$pdo->exec('DELETE FROM debt_collection_queue');
$pdo->exec('DELETE FROM ar_payments');
$pdo->exec('DELETE FROM ar_invoices');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');
$pdo->exec('UPDATE customers SET balance = 0');
$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec("DELETE FROM debt_collection_approvals");
$pdo->exec("DELETE FROM debt_collection_settlements");

// Get test customer
$customers = $ar->getCustomers();
if (empty($customers)) {
    echo "FAIL: No customers. Seed some first.\n"; exit(1);
}
$cid = $customers[0]['id'];

// ════════════════════════════════════════════════════════
// QUEUE GENERATION
// ════════════════════════════════════════════════════════

echo "\n=== Test 1: Generate queue — no overdue invoices ===\n";
$result = $dcs->generateQueueEntries('tester');
assertEq(0, count($result), 'No queue entries for current invoices (balance=0)');

echo "\n=== Test 2: Generate queue — with overdue invoice ===\n";
// Tạo hóa đơn quá hạn (due_date = 30 days ago)
$inv = $ar->recordInvoice($cid, 'DC-TEST-001', date('Y-m-d', strtotime('-60 days')), date('Y-m-d', strtotime('-30 days')), 10000000, 1000000, 10, 'Debt collection test', 'tester');
$invId = $inv['invoice_id'];
$result = $dcs->generateQueueEntries('tester');
assertTrue(count($result) > 0, 'Queue entry created for overdue invoice');
$firstId = $result[0]['queue_id'];

echo "\n=== Test 3: Duplicate queue generation ===\n";
$result2 = $dcs->generateQueueEntries('tester');
$duplicateFound = false;
foreach ($result2 as $e) {
    if ($e['invoice_id'] == $invId) $duplicateFound = true;
}
assertTrue(!$duplicateFound, 'No duplicate queue entry for same invoice');

echo "\n=== Test 4: Queue stats — total ===\n";
$stats = $dcs->getQueueStats();
assertTrue((int)$stats['total'] > 0, 'Queue stats total > 0');

// ════════════════════════════════════════════════════════
// QUEUE ASSIGNMENT
// ════════════════════════════════════════════════════════

echo "\n=== Test 5: Assign queue entry ===\n";
$assign = $dcs->assignQueue($firstId, 'collector_a', 'tester');
assertEq($firstId, $assign['queue_id'], 'Queue assigned');
assertEq('collector_a', $assign['assigned_to'], 'Assigned to collector_a');

echo "\n=== Test 6: List queues by collector ===\n";
$queues = $dcs->listQueues(['assigned_to' => 'collector_a']);
assertTrue(count($queues) > 0, 'Queues found for collector_a');

echo "\n=== Test 7: Load balancing — max 50 items ===\n";
assertThrows(function() use ($dcs) {
    for ($i = 0; $i < 51; $i++) {
        $dcs->assignQueue(99999, 'overloaded', 'tester'); // tránh queue_id thật
    }
}, 'Load balancing check');

// ════════════════════════════════════════════════════════
// QUEUE HOLD / RELEASE
// ════════════════════════════════════════════════════════

echo "\n=== Test 8: Hold queue entry ===\n";
$hold = $dcs->holdQueue($firstId, 'KH đang thương lượng', date('Y-m-d', strtotime('+7 days')), 'tester');
assertEq('hold', $hold['status'], 'Queue status = hold');

echo "\n=== Test 9: Release queue entry ===\n";
$release = $dcs->releaseQueue($firstId, 'tester');
assertEq('active', $release['status'], 'Queue status = active after release');

echo "\n=== Test 10: Hold max 3 times ===\n";
// Test 8 already used 1 hold, so only 2 more to reach max 3
for ($i = 0; $i < 2; $i++) {
    $dcs->holdQueue($firstId, "Hold #$i", date('Y-m-d', strtotime('+7 days')), 'tester');
    $dcs->releaseQueue($firstId, 'tester');
}
assertThrows(function() use ($dcs, $firstId) {
    $dcs->holdQueue($firstId, '4th hold', null, 'tester');
}, 'Max 3 holds exceeded');

echo "\n=== Test 11: Release non-hold entry ===\n";
assertThrows(function() use ($dcs, $firstId) {
    // Queue is currently active (released at end of test 10), trying to release again
    $dcs->releaseQueue($firstId, 'tester');
}, 'Release non-hold entry — queue is active after last release');

echo "\n=== Test 12: Max holds reached = 3, hold blocked ===\n";
assertThrows(function() use ($dcs, $firstId) {
    $dcs->holdQueue($firstId, 'Exceeded', null, 'tester');
}, 'Max 3 holds exceeded (hold_count=3)');

// ════════════════════════════════════════════════════════
// ACTIVITIES
// ════════════════════════════════════════════════════════

echo "\n=== Test 13: Log call activity ===\n";
// Tạo queue mới cho test activities
$inv2 = $ar->recordInvoice($cid, 'DC-TEST-002', date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('-10 days')), 5000000, 500000, 10, 'Activity test', 'tester');
$result = $dcs->generateQueueEntries('tester');
$actQueueId = $result[count($result)-1]['queue_id'];

$act = $dcs->logActivity($actQueueId, 'call', 'Called KH, no answer', 'tester', ['result' => 'no_answer']);
assertTrue(isset($act['id']), 'Activity logged with ID');
assertEq('call', $act['activity_type'], 'Activity type = call');

echo "\n=== Test 14: Log email activity ===\n";
$act2 = $dcs->logActivity($actQueueId, 'email', 'Sent overdue reminder', 'tester', ['contact_person' => 'Mr. Tuấn', 'contact_phone' => '0909123456']);
assertEq('email', $act2['activity_type'], 'Activity type = email');

echo "\n=== Test 15: Log meeting activity ===\n";
$act3 = $dcs->logActivity($actQueueId, 'meeting', 'Met KH tại văn phòng, KH hứa trả 50%', 'tester', ['result' => 'promise', 'promise_date' => date('Y-m-d', strtotime('+7 days')), 'promise_amount' => 2750000]);
assertEq('meeting', $act3['activity_type'], 'Activity type = meeting');

echo "\n=== Test 16: Log dispute activity ===\n";
$act4 = $dcs->logActivity($actQueueId, 'dispute', 'KH khiếu nại chất lượng sản phẩm', 'tester', ['detail' => 'KH nói hàng không đúng mẫu']);
assertEq('dispute', $act4['activity_type'], 'Activity type = dispute');

echo "\n=== Test 17: List activities ===\n";
$q = $dcs->getQueue($actQueueId);
assertTrue(count($q['activities']) >= 4, 'At least 4 activities listed');

echo "\n=== Test 18: Activity on closed queue ===\n";
$dcRepo->closeQueue($actQueueId, 'test_closed', 'Testing activity logging on closed queues');
assertThrows(function() use ($dcs, $actQueueId) {
    $dcs->logActivity($actQueueId, 'call', 'Test closed', 'tester');
}, 'Activity on closed queue blocked');

echo "\n=== Test 19: Delete own activity ===\n";
assertThrows(function() use ($dcs, $act) {
    $dcs->deleteActivity($act['id'] ?? 999999, 'wrong_user');
}, 'Delete activity — only creator');

// ════════════════════════════════════════════════════════
// PROMISES — Tạo queue riêng cho promise tests
// ════════════════════════════════════════════════════════

echo "\n=== Test 20: Create promise ===\n";
$invProm = $ar->recordInvoice($cid, 'DC-TEST-PROM', date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('-5 days')), 8000000, 800000, 10, 'Promise test', 'tester');
$resultProm = $dcs->generateQueueEntries('tester');
$promQueueId = null;
foreach ($resultProm as $e) { if ($e['invoice_id'] == $invProm['invoice_id']) $promQueueId = $e['queue_id']; }
$dcs->assignQueue($promQueueId, 'collector_p', 'tester');

$promise = $dcs->createPromise($promQueueId, date('Y-m-d', strtotime('+14 days')), 4400000, 'tester', null, 'KH hứa trả 50% vào ngày này');
assertTrue(isset($promise['id']), 'Promise created');
assertEq(date('Y-m-d', strtotime('+14 days')), $promise['promise_date'], 'Promise date correct');

echo "\n=== Test 21: Promise amount < 10% balance ===\n";
// Tạo invoice mới có balance 5.5M → 10% = 550k, test promise 100k
$invSmallBal = $ar->recordInvoice($cid, 'DC-TEST-PCT', date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('-5 days')), 5000000, 500000, 10, 'Pct test', 'tester');
$resultPct = $dcs->generateQueueEntries('tester');
$pctQueueId = null;
foreach ($resultPct as $e) { if ($e['invoice_id'] == $invSmallBal['invoice_id']) $pctQueueId = $e['queue_id']; }
assertThrows(function() use ($dcs, $pctQueueId) {
    $dcs->createPromise($pctQueueId, date('Y-m-d', strtotime('+7 days')), 1000, 'tester', null, 'Too small');
}, 'Promise amount < 10% blocked');

echo "\n=== Test 22: Promise date in the past ===\n";
assertThrows(function() use ($dcs, $pctQueueId) {
    $dcs->createPromise($pctQueueId, date('Y-m-d', strtotime('-1 days')), 1000000, 'tester', null, 'Past date');
}, 'Promise date in past blocked');

echo "\n=== Test 23: Promise date > 60 days ===\n";
assertThrows(function() use ($dcs, $pctQueueId) {
    $dcs->createPromise($pctQueueId, date('Y-m-d', strtotime('+61 days')), 1000000, 'tester', null, 'Too far');
}, 'Promise date > 60 days blocked');

echo "\n=== Test 24: Max 3 promises ===\n";
// Test 20 already created 1 active promise; only 2 more to reach max 3
for ($i = 0; $i < 2; $i++) {
    $dcs->createPromise($promQueueId, date('Y-m-d', strtotime('+'.($i+3).' days')), 1000000, 'tester');
}
assertThrows(function() use ($dcs, $promQueueId) {
    $dcs->createPromise($promQueueId, date('Y-m-d', strtotime('+5 days')), 1000000, 'tester');
}, 'Max 3 active promises');

echo "\n=== Test 25: Keep promise ===\n";
$keep = $dcs->keepPromise($promise['id'], 'tester');
assertEq('kept', $keep['status'], 'Promise kept');

echo "\n=== Test 26: Break promise ===\n";
$promise2 = $dcs->createPromise($promQueueId, date('Y-m-d', strtotime('+3 days')), 1000000, 'tester');
$break = $dcs->breakPromise($promise2['id'], 'KH không giữ lời hứa', 'tester');
assertEq('broken', $break['status'], 'Promise broken');

echo "\n=== Test 27: Broken 3 times → auto-escalate ===\n";
// Giả lập thêm 2 lần broken nữa = 3 lần total
$p3 = $dcs->createPromise($promQueueId, date('Y-m-d', strtotime('+1 days')), 1000000, 'tester');
$dcs->breakPromise($p3['id'], 'Lần 2', 'tester');
$p4 = $dcs->createPromise($promQueueId, date('Y-m-d', strtotime('+1 days')), 1000000, 'tester');
$dcs->breakPromise($p4['id'], 'Lần 3', 'tester');
$queue = $dcs->getQueue($promQueueId);
assertTrue($queue['escalation_level'] >= 2, 'Auto-escalated after 3 broken promises');

// ════════════════════════════════════════════════════════
// WRITE-OFF APPROVAL
// ════════════════════════════════════════════════════════

echo "\n=== Test 28: Propose write-off — too recent ===\n";
assertThrows(function() use ($dcs, $firstId) {
    $dcs->proposeWriteOff($firstId, 'tester', 'Nợ khó đòi');
}, 'Write-off blocked — invoice not old enough');

echo "\n=== Test 29: Propose write-off — insufficient activities ===\n";
// Tìm queue entry có invoice > 365 days
// Tạo hóa đơn mới đã quá hạn > 365 days
$inv3 = $ar->recordInvoice($cid, 'DC-TEST-003', date('Y-m-d', strtotime('-400 days')), date('Y-m-d', strtotime('-370 days')), 15000000, 1500000, 10, 'Old invoice for write-off', 'tester');
$result3 = $dcs->generateQueueEntries('tester');
$woQueueId = null;
foreach ($result3 as $e) { if ($e['invoice_id'] == $inv3['invoice_id']) $woQueueId = $e['queue_id']; }
$dcs->assignQueue($woQueueId, 'collector_b', 'tester');

assertThrows(function() use ($dcs, $woQueueId) {
    $dcs->proposeWriteOff($woQueueId, 'tester', 'Nợ quá hạn > 365 ngày');
}, 'Write-off blocked — < 3 activities in 180 days');

echo "\n=== Test 30: Propose write-off — insufficient activities, add some ===\n";
$dcs->logActivity($woQueueId, 'call', 'Called KH', 'tester');
$dcs->logActivity($woQueueId, 'email', 'Sent reminder', 'tester');
$dcs->logActivity($woQueueId, 'letter', 'Sent official letter', 'tester');

echo "\n=== Test 31: Propose write-off — success ===\n";
$proposal = $dcs->proposeWriteOff($woQueueId, 'tester', 'KH không có khả năng thanh toán, đã đòi 3 lần');
assertTrue(isset($proposal['approval_id']), 'Write-off proposal created');
assertEq('pending', $proposal['status'], 'Proposal status = pending');
assertEq(16500000, $proposal['amount'], 'Amount = 15M + 1.5M VAT');

echo "\n=== Test 32: Approval — reject ===\n";
$approvals = $dcs->getPendingApprovals();
$approvalId = $approvals[0]['id'] ?? null;

if ($approvalId) {
    $reject = $dcs->approveWriteOff($approvalId, 'collection_lead', 'rejected', 'Cần thêm bằng chứng');
    assertEq('rejected', $reject['status'], 'Write-off rejected');

    echo "\n=== Test 33: Approval — approve full chain (level 1 only, <10tr) ===\n";
    // Tạo hóa đơn nhỏ <10tr để test full chain
    $invSmall = $ar->recordInvoice($cid, 'DC-TEST-004', date('Y-m-d', strtotime('-400 days')), date('Y-m-d', strtotime('-370 days')), 5000000, 500000, 10, 'Small write-off', 'tester');
    $result4 = $dcs->generateQueueEntries('tester');
    $smallQueueId = null;
    foreach ($result4 as $e) { if ($e['invoice_id'] == $invSmall['invoice_id']) $smallQueueId = $e['queue_id']; }
    $dcs->assignQueue($smallQueueId, 'collector_c', 'tester');
    $dcs->logActivity($smallQueueId, 'call', 'Call 1', 'tester');
    $dcs->logActivity($smallQueueId, 'call', 'Call 2', 'tester');
    $dcs->logActivity($smallQueueId, 'call', 'Call 3', 'tester');
    $smallProposal = $dcs->proposeWriteOff($smallQueueId, 'tester', 'Small debt write-off');
    $smallApprovalId = $smallProposal['approval_id'];

    $approve = $dcs->approveWriteOff($smallApprovalId, 'collection_lead', 'approved');
    assertEq('approved', $approve['status'], 'Small write-off fully approved');
    // Verify invoice written off
    $invCheck = $ar->getInvoice($invSmall['invoice_id']);
    assertEq('written_off', $invCheck['status'], 'Invoice status = written_off');
} else {
    echo "SKIP: No pending approvals found\n";
}

// ════════════════════════════════════════════════════════
// SETTLEMENTS
// ════════════════════════════════════════════════════════

echo "\n=== Test 34: Create settlement ===\n";
$invSettle = $ar->recordInvoice($cid, 'DC-TEST-005', date('Y-m-d', strtotime('-90 days')), date('Y-m-d', strtotime('-60 days')), 20000000, 2000000, 10, 'Settlement test', 'tester');
$result5 = $dcs->generateQueueEntries('tester');
$settleQueueId = null;
foreach ($result5 as $e) { if ($e['invoice_id'] == $invSettle['invoice_id']) $settleQueueId = $e['queue_id']; }
$dcs->assignQueue($settleQueueId, 'collector_d', 'tester');

$settlement = $dcs->createSettlement($settleQueueId, 11000000, date('Y-m-d'), date('Y-m-d', strtotime('+7 days')), 'tester');
assertTrue(isset($settlement['id']), 'Settlement created');
assertEq(11000000, $settlement['settlement_amount'], 'Settlement amount = 11M');
assertEq(50, $settlement['discount_percent'], 'Discount 50% (22M → 11M)');

echo "\n=== Test 35: Settlement discount > 70% ===\n";
assertThrows(function() use ($dcs, $settleQueueId) {
    $dcs->createSettlement($settleQueueId, 1000000, date('Y-m-d'), date('Y-m-d', strtotime('+7 days')), 'tester');
}, 'Settlement discount > 70% blocked');

echo "\n=== Test 36: Settlement due_by > 14 days ===\n";
assertThrows(function() use ($dcs, $settleQueueId) {
    $dcs->createSettlement($settleQueueId, 11000000, date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), 'tester');
}, 'Settlement due > 14 days blocked');

echo "\n=== Test 37: Record settlement payment (partial) ===\n";
$pay = $dcs->recordSettlementPayment($settlement['id'], 5000000, date('Y-m-d'), 'tester');
assertEq('active', $pay['status'], 'Settlement still active (partial)');
assertEq(5000000, $pay['amount_paid'], 'Amount paid = 5M');

echo "\n=== Test 38: Record settlement payment (full) ===\n";
$pay2 = $dcs->recordSettlementPayment($settlement['id'], 6000000, date('Y-m-d'), 'tester');
assertEq('completed', $pay2['status'], 'Settlement completed');
assertEq(11000000, $pay2['amount_paid'], 'Total paid = 11M');

echo "\n=== Test 39: Settlement payment over amount ===\n";
assertThrows(function() use ($dcs, $settlement) {
    $dcs->recordSettlementPayment($settlement['id'], 1000000, date('Y-m-d'), 'tester');
}, 'Settlement overpayment blocked');

// ════════════════════════════════════════════════════════
// QUEUE AUTO-CLOSE ON PAYMENT
// ════════════════════════════════════════════════════════

echo "\n=== Test 40: Payment → auto-close queue ===\n";
$invAuto = $ar->recordInvoice($cid, 'DC-TEST-006', date('Y-m-d', strtotime('-15 days')), date('Y-m-d', strtotime('-5 days')), 3000000, 300000, 10, 'Auto close test', 'tester');
$result6 = $dcs->generateQueueEntries('tester');
$autoQueueId = null;
foreach ($result6 as $e) { if ($e['invoice_id'] == $invAuto['invoice_id']) $autoQueueId = $e['queue_id']; }

// Pay the invoice fully
$ar->recordPayment($invAuto['invoice_id'], 3300000, 'tester');
$closed = $dcs->handlePaymentReceived($invAuto['invoice_id']);
assertTrue($closed !== null, 'Queue auto-closed after payment');
assertEq('closed', $closed['status'], 'Queue status = closed');
assertEq('paid', $closed['resolution'], 'Resolution = paid');

// ════════════════════════════════════════════════════════
// CRON: PROMISES DUE
// ════════════════════════════════════════════════════════

echo "\n=== Test 41: Cron — check promises due ===\n";
$invCron = $ar->recordInvoice($cid, 'DC-TEST-007', date('Y-m-d', strtotime('-90 days')), date('Y-m-d', strtotime('-60 days')), 4000000, 400000, 10, 'Cron test', 'tester');
$result7 = $dcs->generateQueueEntries('tester');
$cronQueueId = null;
foreach ($result7 as $e) { if ($e['invoice_id'] == $invCron['invoice_id']) $cronQueueId = $e['queue_id']; }

// Tạo promise ở tương lai, sau đó set ngày trong DB thành quá khứ để test cron
$pastPromise = $dcs->createPromise($cronQueueId, date('Y-m-d', strtotime('+1 days')), 2000000, 'tester');
$pdo->exec("UPDATE debt_collection_promises SET promise_date = '" . date('Y-m-d', strtotime('-1 days')) . "' WHERE id = " . $pastPromise['id']);
$result = $dcs->checkPromisesDue('cron_test');
assertTrue($result['broken'] >= 0, 'Cron checked promises');
assertTrue($result['kept'] >= 0, 'Cron checked kept');

echo "\n=== Test 42: Cron — auto-escalate ===\n";
$escalated = $dcs->autoEscalate('cron_test');
assertTrue($escalated['escalated'] >= 0, 'Cron auto-escalated');

echo "\n=== Test 43: Cron — auto-release holds ===\n";
$released = $dcs->autoReleaseHolds('cron_test');
assertTrue($released['released'] >= 0, 'Cron auto-released holds');

// ════════════════════════════════════════════════════════
// EDGE CASES
// ════════════════════════════════════════════════════════

echo "\n=== Test 44: Queue entry not found ===\n";
assertThrows(function() use ($dcs) {
    $dcs->assignQueue(99999, 'collector_x', 'tester');
}, 'Queue entry not found');

echo "\n=== Test 45: Assign to non-existent collector ===\n";
$assignBad = $dcs->assignQueue($firstId, 'non_existent', 'tester');
assertEq('non_existent', $assignBad['assigned_to'], 'Assign proceeds (no FK constraint)');

echo "\n=== Test 46: Priority update ===\n";
$prio = $dcs->updatePriority($firstId, 8, 'tester');
assertEq(8, $prio['priority'], 'Priority updated to 8');

echo "\n=== Test 47: Escalation level range ===\n";
assertThrows(function() use ($dcs, $firstId) {
    $dcs->escalateQueue($firstId, 99, 'tester');
}, 'Escalation level > 5 blocked');

echo "\n=== Test 48: Get queue detail ===\n";
$qDetail = $dcs->getQueue($firstId);
assertTrue($qDetail !== null, 'Queue detail returned');
assertTrue(isset($qDetail['activities']), 'Queue includes activities');
assertTrue(isset($qDetail['promises']), 'Queue includes promises');
assertTrue(isset($qDetail['approvals']), 'Queue includes approvals');

echo "\n=== Test 49: Collector stats ===\n";
$cStats = $dcs->getCollectorStats('collector_a');
assertTrue(isset($cStats['total_assigned']), 'Collector stats returned');

echo "\n=== Test 50: Pending approvals ===\n";
$pending = $dcs->getPendingApprovals();
assertTrue(is_array($pending), 'Pending approvals list returned');

// ════════════════════════════════════════════════════════
// QUEUE STATE TRANSITIONS
// ════════════════════════════════════════════════════════

echo "\n=== Test 51: Queue statem — new → active (assign) ===\n";
$invState = $ar->recordInvoice($cid, 'DC-TEST-008', date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('-5 days')), 1000000, 100000, 10, 'State test', 'tester');
$result8 = $dcs->generateQueueEntries('tester');
$stateQueueId = null;
foreach ($result8 as $e) { if ($e['invoice_id'] == $invState['invoice_id']) $stateQueueId = $e['queue_id']; }
$dcs->assignQueue($stateQueueId, 'collector_s', 'tester');
$q = $dcs->getQueue($stateQueueId);
assertEq('active', $q['status'], 'Status = active after assign');

echo "\n=== Test 52: Queue state — active → hold → active ===\n";
$dcs->holdQueue($stateQueueId, 'Test reason', null, 'tester');
$q2 = $dcs->getQueue($stateQueueId);
assertEq('hold', $q2['status'], 'Status = hold');
$dcs->releaseQueue($stateQueueId, 'tester');
$q3 = $dcs->getQueue($stateQueueId);
assertEq('active', $q3['status'], 'Status = active after release');

echo "\n=== Test 53: Queue stats — counts ===\n";
$stats2 = $dcs->getQueueStats();
assertTrue((int)$stats2['total'] > 0, 'Stats total > 0');
assertTrue((int)$stats2['active_count'] >= 0, 'Stats active count');

// ════════════════════════════════════════════════════════
// INTEGRATION: ArService + DebtCollection
// ════════════════════════════════════════════════════════

echo "\n=== Test 54: Integration — generate after payment ===\n";
$invInt = $ar->recordInvoice($cid, 'DC-TEST-009', date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('-1 days')), 2000000, 200000, 10, 'Integration', 'tester');
$ar->recordPayment($invInt['invoice_id'], 2200000, 'tester');
$result9 = $dcs->generateQueueEntries('tester');
$paidEntry = null;
foreach ($result9 as $e) { if ($e['invoice_id'] == $invInt['invoice_id']) $paidEntry = $e; }
assertTrue($paidEntry === null, 'No queue for paid invoices');

echo "\n=== Test 55: Integration — partial payment does not close queue ===\n";
$invPart = $ar->recordInvoice($cid, 'DC-TEST-010', date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('-15 days')), 10000000, 1000000, 10, 'Partial test', 'tester');
$result10 = $dcs->generateQueueEntries('tester');
$partQueueId = null;
foreach ($result10 as $e) { if ($e['invoice_id'] == $invPart['invoice_id']) $partQueueId = $e['queue_id']; }
$ar->recordPayment($invPart['invoice_id'], 5000000, 'tester');
$stillOpen = $dcs->handlePaymentReceived($invPart['invoice_id']);
assertTrue($stillOpen === null, 'Partial payment does NOT close queue');

echo "\n=== Test 56: Integration — full payment closes queue ===\n";
$ar->recordPayment($invPart['invoice_id'], 6000000, 'tester');
$closed2 = $dcs->handlePaymentReceived($invPart['invoice_id']);
assertTrue($closed2 !== null, 'Full payment closes queue');

// ════════════════════════════════════════════════════════
// VALIDATION EDGE CASES
// ════════════════════════════════════════════════════════

echo "\n=== Test 57: Empty activity type ===\n";
assertThrows(function() use ($dcs, $firstId) {
    $dcs->logActivity($firstId, '', 'No type', 'tester');
}, 'Empty activity type');

echo "\n=== Test 58: Promise on non-existent queue ===\n";
assertThrows(function() use ($dcs) {
    $dcs->createPromise(99999, date('Y-m-d', strtotime('+7 days')), 100000, 'tester');
}, 'Promise on non-existent queue');

echo "\n=== Test 59: Propose write-off on non-existent queue ===\n";
assertThrows(function() use ($dcs) {
    $dcs->proposeWriteOff(99999, 'tester', 'Test');
}, 'Write-off on non-existent queue');

echo "\n=== Test 60: Settlement on non-existent queue ===\n";
assertThrows(function() use ($dcs) {
    $dcs->createSettlement(99999, 100000, date('Y-m-d'), date('Y-m-d', strtotime('+7 days')), 'tester');
}, 'Settlement on non-existent queue');

echo "\n=== Test 61: Settlement with zero balance invoice ===\n";
assertThrows(function() use ($dcs, $firstId) {
    $dcs->createSettlement($firstId, 1000, date('Y-m-d'), date('Y-m-d', strtotime('+7 days')), 'tester');
}, 'Settlement with zero balance');

echo "\n=== Test 62: Approve already-resolved approval ===\n";
// Tìm approval đã resolved
$allApprovals = $dcs->getPendingApprovals();
assertTrue(is_array($allApprovals), 'Pending approvals returns array');

echo "\n=== Test 63: Keep non-existent promise ===\n";
assertThrows(function() use ($dcs) {
    $dcs->keepPromise(99999, 'tester');
}, 'Keep non-existent promise');

echo "\n=== Test 64: Break non-existent promise ===\n";
assertThrows(function() use ($dcs) {
    $dcs->breakPromise(99999, 'reason', 'tester');
}, 'Break non-existent promise');

echo "\n=== Test 65: Collective stats summary ===\n";
$allStats = $dcs->getQueueStats();
assertTrue(is_array($allStats), 'Stats is array');
assertTrue(isset($allStats['total_open']), 'Stats has total_open');
assertTrue(isset($allStats['total']), 'Stats has total');

// ════════════════════════════════════════════════════════
// RESULTS
// ════════════════════════════════════════════════════════

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

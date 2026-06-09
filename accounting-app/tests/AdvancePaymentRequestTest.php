<?php
// Test: Mẫu 03-TT (Giấy đề nghị tạm ứng) theo TT99 — AdvancePaymentRequestService
// Nghiệp vụ: Tạo nháp → Gửi duyệt → Duyệt → Đánh dấu đã chi → Hủy
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\AdvancePaymentRequestService;
use Accounting\Domain\Service\VoucherService;
use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Infrastructure\Database\AuditLogger;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$auditLogger = new AuditLogger($pdo);
$voucherService = new VoucherService($pdo);
$service = new AdvancePaymentRequestService($pdo, $voucherService, $auditLogger);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if($a !== $b){echo"FAIL: {$m} — expected ".var_export($b,true).", got ".var_export($a,true)."\n";$failed++;}
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertFalse($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"PASS: {$m}\n";}else{echo"FAIL: {$m}\n";$failed++;}
}
function assertNear($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)<1){echo"PASS: {$m}\n";}else{echo"FAIL: {$m} — diff: ".abs((float)$a-(float)$b)."\n";$failed++;}
}
function results() { global $total, $failed;
    echo "\n=== Results: {$total} tests, {$failed} failed ===\n"; exit($failed>0?1:0);
}

// Xóa dữ liệu test cũ
$pdo->exec('DELETE FROM advance_payment_requests WHERE id LIKE "apr_%"');

echo "=== AdvancePaymentRequestService Test (Mẫu 03-TT) ===\n";

echo "\n--- Test 1: createDraft — tạo đề nghị tạm ứng mới ---\n";
$result = $service->createDraft([
    'request_date' => '2026-06-08',
    'requester_name' => 'Nguyễn Văn A',
    'requester_department' => 'Phòng Kinh doanh',
    'amount' => 5000000,
    'reason' => 'Đi công tác Hà Nội',
    'repayment_term' => 'Ngày 30/06/2026',
    'created_by' => 'tester'
]);

assertTrue(isset($result['id']), 'createDraft returns id');
assertTrue(str_starts_with($result['request_number'], 'TA'), 'Request number starts with TA');
assertEq('draft', $result['status'], 'Status = draft');
assertEq('Nguyễn Văn A', $result['requester_name'], 'Requester name saved');
assertEq('Phòng Kinh doanh', $result['requester_department'], 'Department saved');
assertNear(5000000, $result['amount'], 'Amount = 5,000,000');
assertTrue(!empty($result['amount_in_words']), 'Amount in words auto-generated');
assertEq('Đi công tác Hà Nội', $result['reason'], 'Reason saved');
assertEq('Ngày 30/06/2026', $result['repayment_term'], 'Repayment term saved');

$requestId = $result['id'];

echo "\n--- Test 2: getRequest — lấy chi tiết ---\n";
$detail = $service->getRequest($requestId);
assertEq($result['request_number'], $detail['request_number'], 'getRequest returns correct number');
assertEq('draft', $detail['status'], 'Status still draft');
assertNear(5000000, $detail['amount'], 'Amount matches');

echo "\n--- Test 3: submitRequest — gửi duyệt ---\n";
$submitted = $service->submitRequest($requestId, 'tester');
assertEq('submitted', $submitted['status'], 'Status = submitted after submit');

echo "\n--- Test 4: approveRequest — duyệt đề nghị ---\n";
$approved = $service->approveRequest($requestId, 'manager');
assertEq('approved', $approved['status'], 'Status = approved after approve');

echo "\n--- Test 5: markAsPaid — đánh dấu đã chi ---\n";
$paid = $service->markAsPaid($requestId, 'cashier');
assertEq('paid', $paid['status'], 'Status = paid after mark paid');

echo "\n--- Test 6: listRequests — danh sách ---\n";
$list = $service->listRequests();
assertTrue(count($list) >= 1, 'listRequests returns at least 1 record');

$paidList = $service->listRequests('paid');
$found = false;
foreach ($paidList as $r) { if ($r['id'] === $requestId) $found = true; }
assertTrue($found, 'Paid request in paid list');

$draftList = $service->listRequests('draft');
$draftFound = false;
foreach ($draftList as $r) { if ($r['id'] === $requestId) $draftFound = true; }
assertFalse($draftFound, 'Paid request not in draft list');

echo "\n--- Test 7: Failure — tạo với số tiền <= 0 ---\n";
try {
    $service->createDraft(['requester_name' => 'Test', 'amount' => 0, 'created_by' => 'tester']);
    assertTrue(false, 'Should throw on zero amount');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Throws on zero amount: ' . $e->getMessage());
}

echo "\n--- Test 8: Failure — tạo thiếu tên ---\n";
try {
    $service->createDraft(['requester_name' => '', 'amount' => 100000, 'created_by' => 'tester']);
    assertTrue(false, 'Should throw on empty name');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Throws on empty name: ' . $e->getMessage());
}

echo "\n--- Test 9: Failure — submit không phải draft ---\n";
try {
    $service->submitRequest($requestId, 'tester');
    assertTrue(false, 'Should throw on re-submit');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Throws on re-submit: ' . $e->getMessage());
}

echo "\n--- Test 10: Failure — approve không phải submitted ---\n";
try {
    $service->approveRequest($requestId, 'manager');
    assertTrue(false, 'Should throw on approve non-submitted');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Throws on approve non-submitted: ' . $e->getMessage());
}

echo "\n--- Test 11: cancelDraft — hủy đề nghị draft ---\n";
$draft2 = $service->createDraft([
    'requester_name' => 'Trần Thị B',
    'amount' => 2000000,
    'reason' => 'Mua văn phòng phẩm',
    'created_by' => 'tester'
]);
$cancelled = $service->cancelDraft($draft2['id'], 'tester');
assertEq('cancelled', $cancelled['status'], 'Status = cancelled after cancel');

echo "\n--- Test 12: Failure — không thể hủy đề nghị đã gửi duyệt ---\n";
try {
    $service->cancelDraft($draft2['id'], 'tester');
    assertTrue(false, 'Should throw on cancel submitted');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Throws on cancel submitted: ' . $e->getMessage());
}

echo "\n--- Test 13: rejectRequest — từ chối đề nghị ---\n";
$draft3 = $service->createDraft([
    'requester_name' => 'Lê Văn C',
    'amount' => 3000000,
    'reason' => 'Tạm ứng công tác',
    'created_by' => 'tester'
]);
$service->submitRequest($draft3['id'], 'tester');
$rejected = $service->rejectRequest($draft3['id'], 'manager', 'Không đủ căn cứ');
assertEq('cancelled', $rejected['status'], 'Status = cancelled after reject');
assertTrue(strpos($rejected['notes'] ?? '', 'Không đủ căn cứ') !== false, 'Reject reason in notes');

echo "\n--- Test 14: Failure — không thể markAsPaid nếu không phải approved ---\n";
try {
    $service->markAsPaid($draft3['id'], 'cashier');
    assertTrue(false, 'Should throw on mark paid non-approved');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Throws on mark paid non-approved: ' . $e->getMessage());
}

echo "\n--- Test 15: getRequest — lỗi nếu không tồn tại ---\n";
try {
    $service->getRequest('nonexistent_id');
    assertTrue(false, 'Should throw on nonexistent');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Throws on nonexistent: ' . $e->getMessage());
}

echo "\n--- Test 16: createDraft với số tiền có amount_in_words tự động ---\n";
$result2 = $service->createDraft([
    'requester_name' => 'Test Auto Words',
    'amount' => 12500000,
    'created_by' => 'tester'
]);
assertTrue(!empty($result2['amount_in_words']), 'Amount in words auto-generated for 12,500,000');
$pdo->prepare("DELETE FROM advance_payment_requests WHERE id = ?")->execute([$result2['id']]);

results();

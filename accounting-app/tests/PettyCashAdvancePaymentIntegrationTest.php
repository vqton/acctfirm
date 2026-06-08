<?php
// Test: Tích hợp Petty Cash ↔ Mẫu 03-TT
// Nghiệp vụ: Chi tiền từ đề nghị tạm ứng đã duyệt qua quỹ tạm ứng
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\PettyCashService;
use Accounting\Domain\Service\AdvancePaymentRequestService;
use Accounting\Domain\Service\VoucherService;
use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Infrastructure\Database\AuditLogger;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$auditLogger = new AuditLogger($pdo);
$voucherService = new VoucherService($pdo);
$advancePaymentService = new AdvancePaymentRequestService($pdo, $voucherService, $auditLogger);

// Mock repositories for PettyCashService (only needs PDO for DB ops)
$mockAccountRepo = new class implements \Accounting\Domain\Repository\AccountRepositoryInterface {
    public function findByCode(string $code): ?\Accounting\Domain\Model\Account { return null; }
    public function findById(string $id): ?\Accounting\Domain\Model\Account { return null; }
    public function findAll(): array { return []; }
    public function isControlAccount(string $code): bool { return false; }
    public function findByCodeOrId(string $code): ?\Accounting\Domain\Model\Account { return null; }
    public function save(\Accounting\Domain\Model\Account $a): void {}
    public function delete(string $id): void {}
    public function findByFsMapping(string $mapping): array { return []; }
    public function findControlAccounts(): array { return []; }
    public function findLocked(): array { return []; }
    public function findByType(string $type): array { return []; }
    public function search(string $query): array { return []; }
    public function count(): int { return 0; }
    public function getTreeBalance(string $code): float { return 0.0; }
};
$mockTxnRepo = new class implements \Accounting\Domain\Repository\TransactionRepositoryInterface {
    public function findById(string $id): ?\Accounting\Domain\Model\Transaction { return null; }
    public function findByReference(string $reference): ?\Accounting\Domain\Model\Transaction { return null; }
    public function save(\Accounting\Domain\Model\Transaction $t): void {}
    public function getAll(): array { return []; }
    public function getTransactionsByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array { return []; }
    public function getTransactionsByPeriod(string $periodCode): array { return []; }
    public function getCorrectionsByOriginalId(string $originalId): array { return []; }
};
$mockJournal = new class implements \Accounting\Domain\Contract\JournalServiceInterface {
    public function postEntry(string $description, string $reference, array $lines, string $createdBy, bool $allowControl = false, ?string $module = null, ?string $date = null, ?string $voucherType = null, ?string $sourceModule = null, string $currency = 'VND', float $exchangeRate = 1.0): \Accounting\Domain\Model\Transaction {
        return new \Accounting\Domain\Model\Transaction(uniqid('jrn_'), new \DateTimeImmutable($date ?? 'now'), $description, $reference);
    }
    public function createDraft(string $description, string $reference, array $lines, string $createdBy, bool $allowControl = false, ?string $module = null, ?string $date = null, ?string $voucherType = null, ?string $sourceModule = null, string $currency = 'VND', float $exchangeRate = 1.0): \Accounting\Domain\Model\Transaction {
        return new \Accounting\Domain\Model\Transaction(uniqid('jrn_'), new \DateTimeImmutable($date ?? 'now'), $description, $reference);
    }
    public function submitEntry(string $txnId, string $submittedBy): \Accounting\Domain\Model\Transaction { throw new \BadMethodCallException('not used'); }
    public function approveEntry(string $txnId, string $approverId, ?string $comment = null): \Accounting\Domain\Model\Transaction { throw new \BadMethodCallException('not used'); }
    public function rejectEntry(string $txnId, string $approverId, string $reason): \Accounting\Domain\Model\Transaction { throw new \BadMethodCallException('not used'); }
    public function returnEntry(string $txnId, string $userId, ?string $comment = null): \Accounting\Domain\Model\Transaction { throw new \BadMethodCallException('not used'); }
    public function approveDraft(string $txnId, string $approvedBy): \Accounting\Domain\Model\Transaction { throw new \BadMethodCallException('not used'); }
    public function generateVoucherNo(string $prefix = 'JV'): string { return uniqid('JV-'); }
    public function createSupplementaryEntry(string $originalTxnId, array $correctLines, string $reason, string $createdBy, bool $allowControl = false): \Accounting\Domain\Model\Transaction { throw new \BadMethodCallException('not used'); }
    public function createNegativeEntry(string $originalTxnId, string $reason, string $createdBy, bool $allowControl = false): \Accounting\Domain\Model\Transaction { throw new \BadMethodCallException('not used'); }
    public function createAdjustingEntry(string $originalTxnId, array $movingLines, string $reason, string $createdBy, bool $allowControl = false): \Accounting\Domain\Model\Transaction { throw new \BadMethodCallException('not used'); }
    public function getCorrectionHistory(string $transactionId): array { return []; }
};

// Xóa dữ liệu test cũ
$pdo->exec('DELETE FROM petty_cash_transactions WHERE id LIKE "pctx_%"');
$pdo->exec('DELETE FROM petty_cash_funds WHERE id LIKE "pc_%"');
$pdo->exec('DELETE FROM advance_payment_requests WHERE id LIKE "apr_%"');

$pettyCash = new PettyCashService($mockAccountRepo, $mockTxnRepo, $mockJournal, $pdo, $advancePaymentService);

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

echo "=== PettyCash ↔ AdvancePaymentRequest Integration Test ===\n";

// Setup: tạo quỹ tạm ứng
echo "\n--- Setup: Tạo quỹ tạm ứng ---\n";
$fund = $pettyCash->establishPettyCash('Quỹ TM Test', 20000000, 'tester');
$fundId = $fund['fund_id'];
assertTrue(isset($fundId), 'Fund created with id');
assertNear(20000000, $fund['imprest_amount'], 'Fund imprest = 20M');

// Setup: tạo đề nghị tạm ứng → gửi duyệt → duyệt
echo "\n--- Setup: Tạo đề nghị tạm ứng (Mẫu 03-TT) ---\n";
$request = $advancePaymentService->createDraft([
    'request_date' => '2026-06-08',
    'requester_name' => 'Nguyễn Văn Test',
    'requester_department' => 'Phòng Kỹ thuật',
    'amount' => 5000000,
    'reason' => 'Mua vật tư',
    'repayment_term' => '15 ngày',
    'created_by' => 'tester'
]);
$requestId = $request['id'];
$requestNumber = $request['request_number'];
assertEq('draft', $request['status'], 'Request status = draft');

$request = $advancePaymentService->submitRequest($requestId, 'tester');
assertEq('submitted', $request['status'], 'Request status = submitted');

$request = $advancePaymentService->approveRequest($requestId, 'manager');
assertEq('approved', $request['status'], 'Request status = approved');

// Test 1: Happy path — chi tiền từ đề nghị đã duyệt
echo "\n--- Test 1: disburseFromRequest — happy path ---\n";
$result = $pettyCash->disburseFromRequest(
    $fundId, $requestId, $requestNumber, 5000000,
    'Chi tạm ứng mua vật tư', 'tester'
);
assertTrue(isset($result['transaction_id']), 'Returns transaction_id');
assertEq($requestId, $result['request_id'], 'Returns request_id');
assertEq($requestNumber, $result['request_number'], 'Returns request_number');
assertNear(5000000, $result['amount'], 'Amount = 5M');
assertEq('disbursement', $result['type'], 'Type = disbursement');

// Verify: fund balance giảm
$funds = $pettyCash->getPettyCashFunds();
$updatedFund = null;
foreach ($funds as $f) { if ($f['id'] === $fundId) { $updatedFund = $f; break; } }
assertNear(15000000, $updatedFund['current_balance'], 'Fund balance = 15M (20M - 5M)');

// Verify: request status = paid
$paidRequest = $advancePaymentService->getRequest($requestId);
assertEq('paid', $paidRequest['status'], 'Request status = paid');

// Verify: transaction có request_id trong DB
$txnStmt = $pdo->prepare('SELECT * FROM petty_cash_transactions WHERE id = ?');
$txnStmt->execute([$result['transaction_id']]);
$txnRow = $txnStmt->fetch(PDO::FETCH_ASSOC);
assertEq($requestId, $txnRow['request_id'], 'DB: transaction.request_id saved');
assertEq($requestNumber, $txnRow['request_number'], 'DB: transaction.request_number saved');

// Test 2: Failure — chi tiền từ request đã paid
echo "\n--- Test 2: disburseFromRequest — request already paid ---\n";
// Tạo request mới
$req2 = $advancePaymentService->createDraft([
    'request_date' => '2026-06-08',
    'requester_name' => 'Trần Văn B',
    'requester_department' => 'Phòng Kế toán',
    'amount' => 3000000,
    'reason' => 'Mua VPP',
    'created_by' => 'tester'
]);
$req2 = $advancePaymentService->submitRequest($req2['id'], 'tester');
$req2 = $advancePaymentService->approveRequest($req2['id'], 'manager');
// Chi lần 1 — thành công
$pettyCash->disburseFromRequest($fundId, $req2['id'], $req2['request_number'], 3000000, 'Test', 'tester');
// Chi lần 2 — phải thất bại
try {
    $pettyCash->disburseFromRequest($fundId, $req2['id'], $req2['request_number'], 3000000, 'Test again', 'tester');
    assertFalse(true, 'Should throw: request already paid');
} catch (\InvalidArgumentException $e) {
    assertTrue(str_contains($e->getMessage(), 'chưa được duyệt'), 'Error: not approved (already paid -> submitted/paid cycle)');
}

// Test 3: Failure — request chưa duyệt
echo "\n--- Test 3: disburseFromRequest — request not approved ---\n";
$req3 = $advancePaymentService->createDraft([
    'request_date' => '2026-06-08',
    'requester_name' => 'Lê Văn C',
    'amount' => 2000000,
    'reason' => 'Test',
    'created_by' => 'tester'
]);
try {
    $pettyCash->disburseFromRequest($fundId, $req3['id'], $req3['request_number'], 2000000, 'Test', 'tester');
    assertFalse(true, 'Should throw: request not approved');
} catch (\InvalidArgumentException $e) {
    assertTrue(str_contains($e->getMessage(), 'chưa được duyệt'), 'Error: not approved');
}

// Test 4: Failure — request không tồn tại
echo "\n--- Test 4: disburseFromRequest — request not found ---\n";
try {
    $pettyCash->disburseFromRequest($fundId, 'apr_nonexistent', 'TA-NONE', 1000000, 'Test', 'tester');
    assertFalse(true, 'Should throw: request not found');
} catch (\InvalidArgumentException $e) {
    assertTrue(str_contains($e->getMessage(), 'Không tìm thấy'), 'Error: request not found');
}

// Test 5: Failure — quỹ không đủ số dư
echo "\n--- Test 5: disburseFromRequest — insufficient fund balance ---\n";
$req5 = $advancePaymentService->createDraft([
    'request_date' => '2026-06-08',
    'requester_name' => 'Phạm Văn D',
    'amount' => 50000000,
    'reason' => 'Test — large amount',
    'created_by' => 'tester'
]);
$req5 = $advancePaymentService->submitRequest($req5['id'], 'tester');
$req5 = $advancePaymentService->approveRequest($req5['id'], 'manager');
try {
    $pettyCash->disburseFromRequest($fundId, $req5['id'], $req5['request_number'], 50000000, 'Test', 'tester');
    assertFalse(true, 'Should throw: insufficient balance');
} catch (\InvalidArgumentException $e) {
    assertTrue(str_contains($e->getMessage(), 'Số dư quỹ không đủ'), 'Error: insufficient balance');
}

// Test 6: Failure — amount <= 0
echo "\n--- Test 6: disburseFromRequest — amount <= 0 ---\n";
try {
    $pettyCash->disburseFromRequest($fundId, $requestId, $requestNumber, 0, 'Test', 'tester');
    assertFalse(true, 'Should throw: amount <= 0');
} catch (\InvalidArgumentException $e) {
    assertTrue(str_contains($e->getMessage(), 'phải lớn hơn 0'), 'Error: amount <= 0');
}

// Test 7: Failure — không inject AdvancePaymentService
echo "\n--- Test 7: disburseFromRequest — no advance payment service ---\n";
$pettyCashNoAdvance = new PettyCashService($mockAccountRepo, $mockTxnRepo, $mockJournal, $pdo);
try {
    $pettyCashNoAdvance->disburseFromRequest($fundId, 'apr_x', 'TA-X', 1000, 'Test', 'tester');
    assertFalse(true, 'Should throw: no service injected');
} catch (\RuntimeException $e) {
    assertTrue(str_contains($e->getMessage(), 'chưa được inject'), 'Error: service not injected');
}

// Test 8: Failure — fund không tồn tại
echo "\n--- Test 8: disburseFromRequest — fund not found ---\n";
try {
    $pettyCash->disburseFromRequest('pc_nonexistent', $requestId, $requestNumber, 1000000, 'Test', 'tester');
    assertFalse(true, 'Should throw: fund not found');
} catch (\InvalidArgumentException $e) {
    assertTrue(str_contains($e->getMessage(), 'Không tìm thấy quỹ'), 'Error: fund not found');
}

// Dọn dẹp
$pdo->exec('DELETE FROM petty_cash_transactions WHERE id LIKE "pctx_%"');
$pdo->exec('DELETE FROM petty_cash_funds WHERE id LIKE "pc_%"');
$pdo->exec('DELETE FROM advance_payment_requests WHERE id LIKE "apr_%"');

results();

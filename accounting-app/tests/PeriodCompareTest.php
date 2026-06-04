<?php
//
// Test: PeriodService::comparePeriods (R-7)
// Cover: validate periods, summary by_type, variance by_type, by_account variance
//        failure cases: missing period, same period
//
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Service\PeriodService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Service\PostingRuleService;
use Accounting\Domain\Service\VoucherService;
use Accounting\Infrastructure\Database\AuditLogger;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$audit = new AuditLogger($pdo);
$postRule = new PostingRuleService($pdo);
$voucher = new VoucherService($pdo);
$journal = new JournalService($accountRepo, $txnRepo, $pdo, $audit, $postRule, $voucher);
$svc = new PeriodService($pdo, $accountRepo, $txnRepo, $journal, $audit);

// Helper: tạo period mới
function ensurePeriod(PDO $pdo, string $code, string $start, string $end): void {
    $pdo->prepare("DELETE FROM accounting_periods WHERE period_code=?")->execute([$code]);
    $pdo->prepare("INSERT INTO accounting_periods (period_type, period_code, name, start_date, end_date, status) VALUES ('month', ?, ?, ?, ?, 'open')")
        ->execute([$code, $code, $start, $end]);
}

function ensureAccount(PDO $pdo, string $code, string $name, string $type, int $isControl = 0): void {
    $pdo->prepare("DELETE FROM accounts WHERE code=?")->execute([$code]);
    $pdo->prepare("INSERT INTO accounts (id, code, name, type, normal_balance, is_control, status) VALUES (?, ?, ?, ?, 'D', ?, 1)")
        ->execute([uniqid('acc_'), $code, $name, $type, $isControl]);
}

function insertTestTxn(PDO $pdo, string $date, string $desc, string $accDr, string $accCr, float $amount): void {
    $txnId = 'cmp_t_' . uniqid('', true);
    $pdo->prepare("INSERT INTO transactions (id, date, transaction_date, description, reference, status, created_by) VALUES (?, ?, ?, ?, ?, 'posted', 'test')")
        ->execute([$txnId, $date, $date, $desc, 'CMP-' . substr($txnId, -6)]);
    $accStmt = $pdo->prepare("SELECT id FROM accounts WHERE code = ?");
    $accStmt->execute([$accDr]);
    $accDrId = $accStmt->fetchColumn();
    $accStmt->execute([$accCr]);
    $accCrId = $accStmt->fetchColumn();
    $pdo->prepare("INSERT INTO ledger_entries (id, transaction_id, account_id, amount, is_debit, line_order) VALUES (?, ?, ?, ?, 1, 1)")
        ->execute([uniqid('le_'), $txnId, $accDrId, $amount]);
    $pdo->prepare("INSERT INTO ledger_entries (id, transaction_id, account_id, amount, is_debit, line_order) VALUES (?, ?, ?, ?, 0, 2)")
        ->execute([uniqid('le_'), $txnId, $accCrId, $amount]);
}

// Setup: 2 periods với data khác nhau
ensurePeriod($pdo, '2099-01', '2099-01-01', '2099-01-31');
ensurePeriod($pdo, '2099-02', '2099-02-01', '2099-02-28');
ensureAccount($pdo, 'CMPDR1', 'Compare Asset A', 'asset');
ensureAccount($pdo, 'CMPCR1', 'Compare Revenue A', 'revenue');
ensureAccount($pdo, 'CMPDR2', 'Compare Asset B', 'asset');

// Kỳ A: 1 transaction 100,000 Dr asset, Cr revenue
insertTestTxn($pdo, '2099-01-15', 'Period A txn 1', 'CMPDR1', 'CMPCR1', 100000);
insertTestTxn($pdo, '2099-01-20', 'Period A txn 2', 'CMPDR1', 'CMPCR1', 50000);

// Kỳ B: 2 transactions khác
insertTestTxn($pdo, '2099-02-10', 'Period B txn 1', 'CMPDR1', 'CMPCR1', 200000);
insertTestTxn($pdo, '2099-02-15', 'Period B txn 2', 'CMPDR2', 'CMPCR1', 300000);

// === TEST 1: Happy path — compare returns valid structure ===
$res = $svc->comparePeriods('2099-01', '2099-02');
assertTrue(isset($res['period_a']), 'Có period_a');
assertTrue(isset($res['period_b']), 'Có period_b');
assertTrue(isset($res['variance']), 'Có variance');
assertEq($res['period_a']['period_code'], '2099-01', 'period_a code = 2099-01');
assertEq($res['period_b']['period_code'], '2099-02', 'period_b code = 2099-02');

// === TEST 2: Period A summary — 2 transactions, 150,000 Dr/Cr ===
assertEq($res['period_a']['txn_count'], 2, 'Period A có 2 transactions');
assertNear($res['period_a']['total_debit'], 150000, 'Period A total_debit = 150,000');
assertNear($res['period_a']['total_credit'], 150000, 'Period A total_credit = 150,000');

// === TEST 3: Period B summary — 2 transactions, 500,000 Dr/Cr ===
assertEq($res['period_b']['txn_count'], 2, 'Period B có 2 transactions');
assertNear($res['period_b']['total_debit'], 500000, 'Period B total_debit = 500,000');
assertNear($res['period_b']['total_credit'], 500000, 'Period B total_credit = 500,000');

// === TEST 4: by_type có asset + revenue ===
assertTrue(isset($res['variance']['by_type']['asset']), 'Có variance by_type.asset');
assertTrue(isset($res['variance']['by_type']['revenue']), 'Có variance by_type.revenue');

// === TEST 5: Asset variance — A=150K, B=500K, diff=+350K, pct=+233.33% ===
$assetV = $res['variance']['by_type']['asset'];
assertNear($assetV['a_debit'], 150000, 'Asset a_debit = 150K');
assertNear($assetV['b_debit'], 500000, 'Asset b_debit = 500K');
assertNear($assetV['debit_diff'], 350000, 'Asset debit_diff = +350K');
assertNear($assetV['debit_pct'], 233.33, 'Asset debit_pct = +233.33%');

// === TEST 6: by_account list có CMPDR1 (variance 150K → 200K) ===
$cmpFound = false;
foreach ($res['variance']['by_account'] as $acc) {
    if ($acc['code'] === 'CMPDR1') {
        $cmpFound = true;
        assertNear($acc['a_debit'], 150000, 'CMPDR1 a_debit = 150K');
        assertNear($acc['b_debit'], 200000, 'CMPDR1 b_debit = 200K');
        assertNear($acc['debit_diff'], 50000, 'CMPDR1 debit_diff = +50K');
    }
}
assertTrue($cmpFound, 'CMPDR1 có trong by_account');

// === TEST 7: by_account_count chính xác ===
$byAccCount = $res['variance']['by_account_count'];
assertTrue($byAccCount >= 2, 'by_account_count >= 2 (CMPDR1 + CMPDR2)');

// === TEST 8: Kỳ không tồn tại → exception ===
try {
    $svc->comparePeriods('2099-01', '9999-99');
    assertFalse(true, 'Phải throw khi kỳ B không tồn tại');
} catch (\InvalidArgumentException $e) {
    assertTrue(str_contains($e->getMessage(), '9999-99') || str_contains($e->getMessage(), 'không tồn tại'),
        'Exception message: ' . $e->getMessage());
}

// === TEST 9: Cả 2 kỳ không tồn tại → exception ===
try {
    $svc->comparePeriods('7777-77', '8888-88');
    assertFalse(true, 'Phải throw khi cả 2 kỳ không tồn tại');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Throw khi cả 2 kỳ không tồn tại');
}

// === TEST 10: Compare cùng kỳ (kỳ A = kỳ B) — variance = 0 ===
$resSame = $svc->comparePeriods('2099-01', '2099-01');
$assetSame = $resSame['variance']['by_type']['asset'];
assertNear($assetSame['debit_diff'], 0, 'Same period: debit_diff = 0');
assertNear((float)$assetSame['debit_pct'], 0.0, 'Same period: pct = 0 (diff/a*100)');

// === TEST 11: Period với 0 transactions — vẫn trả structure OK ===
ensurePeriod($pdo, '2099-03', '2099-03-01', '2099-03-31');
$resEmpty = $svc->comparePeriods('2099-01', '2099-03');
assertEq($resEmpty['period_b']['txn_count'], 0, 'Period rỗng = 0 txn');
assertNear($resEmpty['period_b']['total_debit'], 0, 'Period rỗng total_debit = 0');

// === TEST 12: Audit log ghi nhận compare ===
$auditRows = $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action='period.compare' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)")
    ->fetchColumn();
assertTrue($auditRows >= 1, 'Có ít nhất 1 audit log period.compare trong 1 phút qua');

// === TEST 13: by_account chỉ chứa accounts có variance (filter) ===
// → không có account nào a=0, b=0 (variance=0)
foreach ($res['variance']['by_account'] as $acc) {
    $hasVariance = abs($acc['debit_diff']) > 0.01 || abs($acc['credit_diff']) > 0.01;
    assertTrue($hasVariance, "Account {$acc['code']} có variance");
}

// Cleanup
$pdo->exec("DELETE FROM ledger_entries WHERE transaction_id IN (SELECT id FROM transactions WHERE description LIKE 'Period %')");
$pdo->exec("DELETE FROM transactions WHERE description LIKE 'Period %'");
$pdo->exec("DELETE FROM accounts WHERE code IN ('CMPDR1','CMPCR1','CMPDR2')");
$pdo->exec("DELETE FROM accounting_periods WHERE period_code IN ('2099-01','2099-02','2099-03')");

results();

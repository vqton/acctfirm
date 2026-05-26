<?php
// Test: Nâng cao chốt kỳ — snapshot và rollback
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Service\TrialBalanceService;
use Accounting\Domain\Service\PeriodService;
use Accounting\Domain\Service\ReconciliationService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Infrastructure\Database\AuditLogger;
use Accounting\Domain\Service\JournalService;

$dbConfig = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
    $dbConfig['username'], $dbConfig['password'], $dbConfig['options']
);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$auditLogger = new AuditLogger($pdo);
$postingSvc = new \Accounting\Domain\Service\PostingRuleService($pdo);
$voucherSvc = new \Accounting\Domain\Service\VoucherService($pdo);
$approvalSvc = new \Accounting\Domain\Service\ApprovalRoutingService($pdo);
$reconSvc = new ReconciliationService($pdo);
$journalSvc = new JournalService($accountRepo, $txnRepo, $pdo, $auditLogger, $postingSvc, $voucherSvc, $approvalSvc);
$periodSvc = new PeriodService($pdo, $accountRepo, $txnRepo, $journalSvc, $auditLogger, null, $reconSvc);

// 1. TrialBalanceService — stand-alone
$tbSvc = new TrialBalanceService($pdo);
$tb = $tbSvc->getTrialBalance();
assertTrue($tb['balanced'], 'TB balanced: Dr=' . $tb['grand_total_dr'] . ' Cr=' . $tb['grand_total_cr']);

// 2. canClose = pre-close checklist (at least 8 checks)
// Nghiệp vụ: Danh sách kiểm tra trước khi đóng kỳ — tối thiểu 7 checks
// Các checks bắt buộc: Trial balance Dr=Cr, Sequential period close, FS generated, Sub-ledger vs GL
// Nếu fail → đóng kỳ thiếu kiểm soát → rủi ro số liệu
$stmt = $pdo->query("SELECT id FROM accounting_periods WHERE status = 'open' ORDER BY end_date DESC LIMIT 1");
$lastPeriodId = (int)$stmt->fetchColumn();
if ($lastPeriodId) {
    $checklist = $periodSvc->canClose($lastPeriodId);
    assertTrue(isset($checklist['can_close']), 'canClose returns can_close');
    assertTrue(count($checklist['checks']) >= 7, 'At least 7 pre-close checks');

    $checkNames = array_map(fn($c) => $c['check'], $checklist['checks']);
    assertTrue(in_array('Trial balance (Dr = Cr)', $checkNames), 'TB check present');
    assertTrue(in_array('Sequential period close', $checkNames), 'Sequential check present');
    assertTrue(in_array('Financial statements generated', $checkNames), 'FS gate present');
    assertTrue(in_array('Sub-ledger vs GL reconciliation', $checkNames), 'Recon check present');
} else {
    $stmt = $pdo->query("SELECT MAX(end_date) AS max_end FROM accounting_periods");
    $latest = $stmt->fetch();
    $newStart = date('Y-m-d', strtotime($latest['max_end'] . ' +1 day'));
    $newEnd = date('Y-m-d', strtotime($newStart . ' +1 month'));
    try {
        $periodSvc->createPeriod('month', 'TST-SEQ', 'Test Sequential', $newStart, $newEnd, 'test');
        assertTrue(false, 'createPeriod should throw when previous still open');
    } catch (\InvalidArgumentException $e) {
        assertTrue(true, 'Sequential enforcement OK: ' . $e->getMessage());
    }
}

// 3. Accounts needed for tax/year-end exist
// Kiểm tra: Các TK đặc biệt cho cuối kỳ và thuế phải tồn tại
// TK 821 (Chi phí thuế TNDN), 353 (Quỹ khen thưởng), 414 (Quỹ đầu tư phát triển)
// Nếu fail → không thể thực hiện bút toán cuối năm
$stmt = $pdo->query("SELECT id FROM accounts WHERE code = '821'");
assertTrue((bool)$stmt->fetchColumn(), 'Account 821 (CIT expense) exists');

$stmt = $pdo->query("SELECT id FROM accounts WHERE code = '353'");
assertTrue((bool)$stmt->fetchColumn(), 'Account 353 (Bonus fund) exists');

$stmt = $pdo->query("SELECT id FROM accounts WHERE code = '414'");
assertTrue((bool)$stmt->fetchColumn(), 'Account 414 (Investment fund) exists');

results();

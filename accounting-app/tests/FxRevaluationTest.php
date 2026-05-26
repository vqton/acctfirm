<?php
// Test: Đánh giá lại ngoại tệ cuối kỳ
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Service\FxRevaluationService;

$dbConfig = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
    $dbConfig['username'], $dbConfig['password'], $dbConfig['options']
);

$accountRepo = new \Accounting\Infrastructure\Persistence\PDOAccountRepository($pdo);
$txnRepo = new \Accounting\Infrastructure\Persistence\PDOTransactionRepository($pdo);
$auditLogger = new \Accounting\Infrastructure\Database\AuditLogger($pdo);
$postingSvc = new \Accounting\Domain\Service\PostingRuleService($pdo);
$voucherSvc = new \Accounting\Domain\Service\VoucherService($pdo);
$approvalSvc = new \Accounting\Domain\Service\ApprovalRoutingService($pdo);
$journalSvc = new \Accounting\Domain\Service\JournalService($accountRepo, $txnRepo, $pdo, $auditLogger, $postingSvc, $voucherSvc, $approvalSvc);
$fxSvc = new FxRevaluationService($pdo, $accountRepo, $journalSvc);

// Nghiệp vụ: Đánh giá lại ngoại tệ cuối kỳ — điều chỉnh số dư các TK tiền tệ
// Yêu cầu: Các TK điều chỉnh tỷ giá phải tồn tại: 515, 635, 413
// Kiểm tra: TK tiền tệ có gốc ngoại tệ: 131, 331, 341
// Nếu fail → không thể đánh giá lại ngoại tệ cuối kỳ → sai BC01
// Giả định: Bảng exchange_rates có dữ liệu tỷ giá
echo "\n";
foreach (['515', '635', '413'] as $code) {
    $acct = $accountRepo->findByCode($code);
    assertTrue($acct !== null, "FX adjustment account {$code} exists");
}

// Verify FC monetary accounts that exist
foreach (['131', '331', '341'] as $code) {
    $acct = $accountRepo->findByCode($code);
    assertTrue($acct !== null, "FC monetary account {$code} exists");
}

// Verify exchange rates table has data
$stmt = $pdo->query("SELECT COUNT(*) FROM exchange_rates");
$rateCount = (int)$stmt->fetchColumn();
assertTrue($rateCount > 0, 'At least 1 exchange rate exists (got ' . $rateCount . ')');

// Verify non-VND currencies exist
$stmt = $pdo->query("SELECT DISTINCT currency_code FROM exchange_rates WHERE currency_code != 'VND' LIMIT 5");
$fcCurrencies = $stmt->fetchAll(\PDO::FETCH_COLUMN);
assertTrue(count($fcCurrencies) > 0, 'Non-VND exchange rates exist: ' . implode(', ', $fcCurrencies));

// Kiểm tra: Báo cáo đánh giá lại ngoại tệ trả về cấu trúc đúng
echo "\n";
$stmt = $pdo->query("SELECT id FROM accounting_periods ORDER BY end_date DESC LIMIT 1");
$periodId = (int)$stmt->fetchColumn();
if ($periodId) {
    $report = $fxSvc->getRevaluationReport($periodId);
    assertTrue(isset($report['items']), 'Revaluation report returns items array');
    assertTrue($report['period_code'] !== '', 'Revaluation report has period_code');
}

results();

<?php
// Test: Giao dịch nội bộ — hạch toán giữa các đơn vị
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Service\IntercompanyService;

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

$icSvc = new IntercompanyService($pdo, $journalSvc);

// Nghiệp vụ: Giao dịch nội bộ giữa các đơn vị hạch toán phụ thuộc
// 1. Entities table exists and has seed data
// Kiểm tra: bảng accounting_entities có dữ liệu seed
// Nếu fail → không thể hạch toán giao dịch nội bộ
echo "\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM accounting_entities");
$entityCount = (int)$stmt->fetchColumn();
assertTrue($entityCount > 0, 'Accounting entities have seed data (got ' . $entityCount . ')');

// 2. Transactions table has entity fields
$stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'entity_id'");
assertTrue((bool)$stmt->fetchColumn(), 'transactions has entity_id column');

$stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'is_intercompany'");
assertTrue((bool)$stmt->fetchColumn(), 'transactions has is_intercompany column');

// 3. IC receivable/payable accounts exist
foreach (['136', '336'] as $code) {
    $acct = $accountRepo->findByCode($code);
    assertTrue($acct !== null, "IC account {$code} exists");
}

// 4. getEntities — danh sách đơn vị hạch toán
echo "\n";
$entities = $icSvc->getEntities();
assertTrue(count($entities) > 0, 'getEntities returns entities');

// 5. matchBalances — đối chiếu số dư nội bộ (ngay cả khi chưa có giao dịch)
// Kiểm tra cấu trúc trả về: items, total_unmatched
echo "\n";
if (count($entities) > 0) {
    $entityId = (int)$entities[0]['id'];
    $match = $icSvc->matchBalances($entityId);
    assertTrue(isset($match['items']), 'matchBalances returns items');
    assertTrue(isset($match['total_unmatched']), 'matchBalances returns total_unmatched');

    // 6. consolidatedReport — báo cáo hợp nhất các cặp giao dịch nội bộ
    // Kiểm tra cấu trúc: pairs, entity_count
    echo "\n";
    $report = $icSvc->consolidatedReport();
    assertTrue(isset($report['pairs']), 'consolidatedReport returns pairs');
    assertTrue($report['entity_count'] > 0, 'consolidatedReport has entity_count');
}

results();

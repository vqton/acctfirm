<?php
require __DIR__ . '/bootstrap.php';
use Accounting\Domain\Service\ManufacturingService;
use Accounting\Domain\Service\ReportExportService;
use Accounting\Infrastructure\Persistence\PDOBomRepository;
use Accounting\Infrastructure\Persistence\PDOProductionOrderRepository;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Service\VoucherService;
use Accounting\Domain\Service\PostingRuleService;
use Accounting\Infrastructure\Database\AuditLogger;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Domain\Repository\TransactionRepositoryInterface;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$bomRepo = new PDOBomRepository($pdo);
$poRepo = new PDOProductionOrderRepository($pdo);
$export = new ReportExportService();
$acctRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$auditLogger = new AuditLogger($pdo);
$voucherService = new VoucherService($pdo);
$postingRule = new PostingRuleService($pdo);
$journalService = new JournalService($acctRepo, $txnRepo, $pdo, $auditLogger, $postingRule, $voucherService);
$service = new ManufacturingService($bomRepo, $poRepo, $pdo, $export, $journalService);

// Cleanup (FK-safe order)
$pdo->exec("DELETE FROM bom_lines WHERE bom_id IN (SELECT id FROM bom WHERE id LIKE 'test_%')");
$pdo->exec("DELETE FROM bom WHERE id LIKE 'test_%'");
$pdo->exec("DELETE FROM production_order_materials WHERE production_order_id LIKE 'test_%'");
$pdo->exec("DELETE FROM production_order_labor WHERE production_order_id LIKE 'test_%'");
$pdo->exec("DELETE FROM production_order_overhead WHERE production_order_id LIKE 'test_%'");
$pdo->exec("DELETE FROM production_orders WHERE id LIKE 'test_%'");
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$pdo->exec("DELETE FROM items WHERE id IN ('test_item_mat','test_item_prod')");
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");

// Seed test items
$pdo->prepare("INSERT INTO items (id,code,name,item_type,unit) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)")->execute(['test_item_mat', 'TEST_MAT', 'Vật liệu test', 'material', 'cai']);
$pdo->prepare("INSERT INTO items (id,code,name,item_type,unit) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)")->execute(['test_item_prod', 'TEST_PROD', 'Sản phẩm test', 'product', 'cai']);

// === TEST 1: Create BOM ===
$lines = [
    ['id' => uniqid('l_'), 'material_id' => 'test_item_mat', 'qty_per_unit' => 2, 'wastage_pct' => 5, 'unit' => 'cai'],
];
$bom = $service->createBom('test_item_prod', '2026-01-01', $lines, 'Test BOM', 'admin');
assertTrue(strlen($bom->getId()) > 0, 'BOM created');
assertTrue($bom->getVersion() >= 1, 'BOM version >= 1');

// === TEST 2: Activate BOM ===
$service->activateBom($bom->getId());
$active = $bomRepo->findActiveByProduct('test_item_prod');
assertTrue($active !== null, 'Active BOM found');
assertEq($active->getStatus(), 'active', 'BOM status = active');

// === TEST 3: Get BOM details ===
$details = $service->getBomDetails($bom->getId());
assertTrue(isset($details['lines']), 'BOM details has lines');
assertEq(count($details['lines']), 1, 'BOM has 1 line');

// === TEST 4: Create production order ===
$po = $service->createProductionOrder('test_item_prod', 100, $bom->getId(), '2026-06-01', '2026-06-30', 'admin');
assertTrue(strlen($po->getId()) > 0, 'PO created');
assertEq($po->getStatus(), 'draft', 'PO status = draft');
assertTrue($po->getQty() == 100, 'PO qty = 100');

// === TEST 5: Release PO ===
$service->releaseProductionOrder($po->getId());
$released = $poRepo->findById($po->getId());
assertEq($released->getStatus(), 'released', 'PO status = released');

// === TEST 6: Issue material ===
$service->issueMaterial($po->getId(), 'test_item_mat', 200, 5000, 1000000);
$materials = $poRepo->getMaterials($po->getId());
assertEq(count($materials), 1, '1 material issued');
assertEq((int)$materials[0]['total_cost'], 1000000, 'Material total cost = 1M');

// === TEST 7: Record labor ===
$service->recordLabor($po->getId(), 'direct', 40, 50000);
$labor = $poRepo->getLabor($po->getId());
assertEq(count($labor), 1, '1 labor record');
assertEq((int)$labor[0]['total_cost'], 2000000, 'Labor total cost = 2M');

// === TEST 8: Record overhead ===
$service->recordOverhead($po->getId(), 'electricity', 1000, 500);
$overhead = $poRepo->getOverhead($po->getId());
assertEq(count($overhead), 1, '1 overhead record');
assertEq((int)$overhead[0]['total_cost'], 500000, 'Overhead total cost = 500K');

// === TEST 9: Complete PO ===
$service->completeProductionOrder($po->getId(), 90, '2026-06-28');
$completed = $poRepo->findById($po->getId());
assertEq($completed->getStatus(), 'completed', 'PO status = completed');
assertTrue($completed->getCompletedQty() == 90, 'Completed qty = 90');

// === TEST 10: Calculate cost ===
$cost = $service->calculateCost($po->getId());
assertNear($cost['total_cost'], 3500000, 'Total cost = 3.5M');
assertNear($cost['unit_cost'], 38888.8889, 'Unit cost calculated');

// === TEST 11: Get production report ===
$report = $service->getProductionReport($po->getId());
assertTrue(isset($report['order']), 'Report has order');
assertTrue(isset($report['materials']), 'Report has materials');
assertTrue(isset($report['labor']), 'Report has labor');
assertTrue(isset($report['overhead']), 'Report has overhead');

// === TEST 12: Close PO ===
$service->closeProductionOrder($po->getId());
$closed = $poRepo->findById($po->getId());
assertEq($closed->getStatus(), 'closed', 'PO status = closed');

// === TEST 13: Dashboard ===
$dash = $service->getDashboard();
assertTrue(isset($dash['stats']), 'Dashboard has stats');
assertTrue(isset($dash['orders']), 'Dashboard has orders');

// === TEST 14: Export ===
$result = $service->exportReport('csv', $po->getId());
assertTrue(isset($result['content']), 'Export has content');

// === TEST 15: Error on release non-draft ===
try {
    $service->releaseProductionOrder($po->getId());
    assertTrue(false, 'Should throw for non-draft');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Correctly rejects releasing non-draft PO');
}

// === TEST 16: Error on complete non-released ===
$po2 = $service->createProductionOrder('test_item_prod', 50);
$poRepo->save($po2);
try {
    $service->completeProductionOrder($po2->getId(), 50, '2026-07-01');
    assertTrue(false, 'Should throw for non-released');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Correctly rejects completing non-released PO');
}

// Cleanup
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$pdo->exec("DELETE FROM bom_lines WHERE bom_id LIKE 'test_%'");
$pdo->exec("DELETE FROM bom WHERE id LIKE 'test_%'");
$pdo->exec("DELETE FROM production_order_materials WHERE production_order_id LIKE 'test_%' OR production_order_id = '{$po2->getId()}'");
$pdo->exec("DELETE FROM production_order_labor WHERE production_order_id LIKE 'test_%' OR production_order_id = '{$po2->getId()}'");
$pdo->exec("DELETE FROM production_order_overhead WHERE production_order_id LIKE 'test_%' OR production_order_id = '{$po2->getId()}'");
$pdo->exec("DELETE FROM production_orders WHERE id LIKE 'test_%' OR id = '{$po2->getId()}'");
$pdo->exec("DELETE FROM items WHERE id IN ('test_item_mat','test_item_prod')");
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");

results();

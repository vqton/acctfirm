<?php
// Test Phase 2: E-invoice import → auto goods receipt + VAT summary

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use Accounting\Domain\Service\EInvoiceImportService;
use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Service\GoodsReceiptService;

$failed = 0;
$total = 0;

function assertEq($a, $b, $msg) { global $total, $failed; $total++; if ($a == $b) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected " . var_export($b, true) . ", got " . var_export($a, true) . "\n"; $failed++; } }
function assertTrue($cond, $msg) { global $total, $failed; $total++; if ($cond) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg}\n"; $failed++; } }
function assertFalse($cond, $msg) { global $total, $failed; $total++; if (!$cond) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg}\n"; $failed++; } }
function assertNotNull($val, $msg) { global $total, $failed; $total++; if ($val !== null) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg}\n"; $failed++; } }
function assertStringContains($haystack, $needle, $msg) { global $total, $failed; $total++; if (strpos($haystack, $needle) !== false || strpos($needle, $haystack) !== false) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg}\n"; $failed++; } }
function results() { global $total, $failed; echo "\n=== Results: {$total} tests, {$failed} failed ===\n"; exit($failed > 0 ? 1 : 0); }

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new \Accounting\Infrastructure\Persistence\PDOAccountRepository($pdo);
$txnRepo = new \Accounting\Infrastructure\Persistence\PDOTransactionRepository($pdo);
$supplierRepo = new \Accounting\Infrastructure\Persistence\PDOSupplierRepository($pdo);
$auditLogger = new \Accounting\Infrastructure\Database\AuditLogger($pdo);
$voucherService = new \Accounting\Domain\Service\VoucherService($pdo);
$postingRuleService = new \Accounting\Domain\Service\PostingRuleService($pdo);
$approvalRoutingService = new \Accounting\Domain\Service\ApprovalRoutingService($pdo, $auditLogger);
$journalService = new JournalService($accountRepo, $txnRepo, $pdo, $auditLogger, $postingRuleService, $voucherService, $approvalRoutingService);

$importService = new EInvoiceImportService($pdo, $supplierRepo, $accountRepo, $journalService, $auditLogger);

// Gắn GoodsReceiptService
$itemRepo = new \Accounting\Infrastructure\Persistence\PDOItemRepository($pdo);
$warehouseRepo = new \Accounting\Infrastructure\Persistence\PDOWarehouseRepository($pdo);
$inventoryService = new \Accounting\Domain\Service\InventoryService($accountRepo, $txnRepo, $itemRepo, $warehouseRepo, $journalService, $pdo);

$grRepo = new \Accounting\Infrastructure\Persistence\PDOGoodsReceiptRepository($pdo);
$grLineRepo = new \Accounting\Infrastructure\Persistence\PDOGoodsReceiptLineRepository($pdo);
$goodsReceiptService = new GoodsReceiptService($pdo, $voucherService, $journalService, $grRepo, $grLineRepo, $itemRepo, $warehouseRepo, $auditLogger, $inventoryService);
$importService->setGoodsReceiptService($goodsReceiptService);

// Test data
$XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<InvoiceData xmlns="http://www.gdt.gov.vn/2025/invoice">
  <TTChung><KHMSHDon>1</KHMSHDon><KHHDon>PH2/001</KHHDon><SHDon>PH200001</SHDon><NLap>2026-06-09</NLap></TTChung>
  <NBan><Ten>Cty Phase 2 Test</Ten><MST>PH200000001</MST><DChi>123 Test St</DChi></NBan>
  <DSHHDVu><HHDVu><STT>1</STT><THHDVu>Hàng Phase 2</THHDVu><DVTinh>Cái</DVTinh><SLuong>5</SLuong><DGia>100000</DGia><ThTien>500000</ThTien><TSuat>10</TSuat></HHDVu></DSHHDVu>
  <TToan><TgTCThue>500000</TgTCThue><TgTThue>50000</TgTThue><TgTTTBSo>550000</TgTTTBSo></TToan>
</InvoiceData>
XML;

$XML2 = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<InvoiceData xmlns="http://www.gdt.gov.vn/2025/invoice">
  <TTChung><KHMSHDon>1</KHMSHDon><KHHDon>PH2/002</KHHDon><SHDon>PH200002</SHDon><NLap>2026-06-10</NLap></TTChung>
  <NBan><Ten>Cty Phase 2 Test</Ten><MST>PH200000001</MST></NBan>
  <DSHHDVu><HHDVu><STT>1</STT><THHDVu>Hàng Phase 2b</THHDVu><DVTinh>Cái</DVTinh><SLuong>3</SLuong><DGia>200000</DGia><ThTien>600000</ThTien><TSuat>10</TSuat></HHDVu></DSHHDVu>
  <TToan><TgTCThue>600000</TgTCThue><TgTThue>60000</TgTThue><TgTTTBSo>660000</TgTTTBSo></TToan>
</InvoiceData>
XML;

// Cleanup
$pdo->exec("DELETE FROM einvoice_imports WHERE supplier_tax_code = 'PH200000001'");
$pdo->exec("DELETE FROM transactions WHERE description LIKE '%PH200001%' OR description LIKE '%PH200002%'");
$pdo->exec("DELETE FROM suppliers WHERE tax_code = 'PH200000001'");
$pdo->exec("DELETE FROM items WHERE name IN ('Hàng Phase 2','Hàng Phase 2b')");

// === Test 1: Import with auto goods receipt ===
$result = $importService->importXml($XML, 'test_user', ['auto_goods_receipt' => true]);
assertNotNull($result['goods_receipt_id'], 'Goods receipt ID returned');
assertStringContains($result['goods_receipt_id'], 'gr_', 'GR ID prefix');
assertNotNull($result['transaction_id'], 'Transaction ID returned');

// Verify GR exists in DB
$grStmt = $pdo->prepare("SELECT id, status, total_amount, supplier_name FROM goods_receipts WHERE id = ?");
$grStmt->execute([$result['goods_receipt_id']]);
$gr = $grStmt->fetch(PDO::FETCH_ASSOC);
assertNotNull($gr, 'Goods receipt exists in DB');
assertEq('posted', $gr['status'], 'Goods receipt status = posted');
assertEq('Cty Phase 2 Test', $gr['supplier_name'], 'Supplier name matches');
assertEq(500000, $gr['total_amount'], 'GR total = 500K');

// Verify import record has goods_receipt_id
$impStmt = $pdo->prepare("SELECT goods_receipt_id FROM einvoice_imports WHERE id = ?");
$impStmt->execute([$result['import_id']]);
assertEq($result['goods_receipt_id'], $impStmt->fetchColumn(), 'Import record linked to GR');

// Verify stock updated via cost layers
$itemStmt = $pdo->prepare("SELECT id FROM items WHERE name = 'Hàng Phase 2'");
$itemStmt->execute();
$itemId = $itemStmt->fetchColumn();
$clStmt = $pdo->prepare("SELECT SUM(qty) FROM inventory_cost_layers WHERE item_id = ?");
$clStmt->execute([$itemId]);
assertEq(5, (int)$clStmt->fetchColumn(), 'Stock qty = 5 after GR (cost layers)');

// === Test 2: Import without auto goods receipt (opt-out) ===
$result2 = $importService->importXml($XML2, 'test_user', ['auto_goods_receipt' => false]);
assertTrue(!isset($result2['goods_receipt_id']), 'No GR when auto_goods_receipt=false');
$impStmt2 = $pdo->prepare("SELECT goods_receipt_id FROM einvoice_imports WHERE id = ?");
$impStmt2->execute([$result2['import_id']]);
assertTrue(empty($impStmt2->fetchColumn()), 'Import record has no GR link');

// === Test 3: VAT summary ===
$summary = $importService->getVatSummary('2026-06');
assertEq(2, $summary['total_invoices'], 'VAT summary: 2 invoices');
assertEq(1100000, $summary['total_purchases'], 'VAT summary: total purchases = 500K + 600K');
assertEq(110000, $summary['total_vat_input'], 'VAT summary: total VAT input = 50K + 60K');
assertEq(1210000, $summary['total_payable'], 'VAT summary: total payable = 550K + 660K');
assertEq(1, $summary['supplier_count'], 'VAT summary: 1 supplier');
assertEq(1, $summary['with_goods_receipt'], 'VAT summary: 1 with GR (PH200001 has GR, PH200002 does not)');

// === Test 4: VAT summary for empty period ===
$emptySummary = $importService->getVatSummary('2099-12');
assertEq(0, $emptySummary['total_invoices'], 'Empty period: 0 invoices');
assertEq(0, $emptySummary['total_purchases'], 'Empty period: 0 purchases');
assertEq(0, $emptySummary['total_vat_input'], 'Empty period: 0 VAT');
assertEq(0, $emptySummary['supplier_count'], 'Empty period: 0 suppliers');

// === Test 5: VAT summary yearly ===
$yearSummary = $importService->getVatSummary('2026');
assertTrue($yearSummary['total_invoices'] >= 2, 'Year summary has at least 2 invoices');

// Cleanup
$pdo->exec("DELETE FROM einvoice_imports WHERE supplier_tax_code = 'PH200000001'");
$pdo->exec("DELETE FROM transactions WHERE description LIKE '%PH200001%' OR description LIKE '%PH200002%'");
$pdo->exec("DELETE FROM suppliers WHERE tax_code = 'PH200000001'");
$pdo->exec("DELETE FROM items WHERE name IN ('Hàng Phase 2','Hàng Phase 2b')");

results();

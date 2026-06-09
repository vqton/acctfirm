<?php
// Test Phase 5: E-invoice import → production order allocation (Sản xuất)

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

$failed = 0;
$total = 0;

function assertEq($a, $b, $msg) { global $total, $failed; $total++; if ($a == $b) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected " . var_export($b, true) . ", got " . var_export($a, true) . "\n"; $failed++; } }
function assertTrue($cond, $msg) { global $total, $failed; $total++; if ($cond) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg}\n"; $failed++; } }
function assertNotNull($val, $msg) { global $total, $failed; $total++; if ($val !== null) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg}\n"; $failed++; } }
function assertNull($val, $msg) { global $total, $failed; $total++; if ($val === null) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg}\n"; $failed++; } }
function assertStringContains($haystack, $needle, $msg) { global $total, $failed; $total++; if (strpos($haystack, $needle) !== false) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg}\n"; $failed++; } }
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
$journalService = new \Accounting\Domain\Service\JournalService($accountRepo, $txnRepo, $pdo, $auditLogger, $postingRuleService, $voucherService, $approvalRoutingService);
$importService = new \Accounting\Domain\Service\EInvoiceImportService($pdo, $supplierRepo, $accountRepo, $journalService, $auditLogger);

$PROD_XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<InvoiceData xmlns="http://www.gdt.gov.vn/2025/invoice">
  <TTChung><KHMSHDon>1</KHMSHDon><KHHDon>PH5/001</KHHDon><SHDon>PH500001</SHDon><NLap>2026-06-09</NLap></TTChung>
  <NBan><Ten>Cty Phase 5 Prod</Ten><MST>PH500000001</MST></NBan>
  <DSHHDVu><HHDVu><STT>1</STT><THHDVu>Nguyên liệu PH5</THHDVu><DVTinh>Kg</DVTinh><SLuong>100</SLuong><DGia>200000</DGia><ThTien>20000000</ThTien><TSuat>10</TSuat></HHDVu></DSHHDVu>
  <TToan><TgTCThue>20000000</TgTCThue><TgTThue>2000000</TgTThue><TgTTTBSo>22000000</TgTTTBSo></TToan>
</InvoiceData>
XML;

// Cleanup
$pdo->exec("DELETE FROM einvoice_imports WHERE supplier_tax_code = 'PH500000001'");
$pdo->exec("DELETE FROM suppliers WHERE tax_code = 'PH500000001'");
$pdo->exec("DELETE FROM items WHERE name = 'Nguyên liệu PH5'");

// Import
$result = $importService->importXml($PROD_XML, 'test_user', ['auto_goods_receipt' => false]);
$importId = $result['import_id'];
assertNotNull($importId, 'Import created for production test');

// === Test 1: Initial production fields are null ===
$imp = $importService->getImport($importId);
assertNull($imp['production_order_id'] ?? null, 'Initial production_order_id = null');

// === Test 2: Allocate to production as raw_material ===
$alloc = $importService->allocateToProduction($importId, 'PO2026-001', 'raw_material', 'test_user');
assertEq('PO2026-001', $alloc['production_order_id'], 'Alloc returns PO id');
assertEq('raw_material', $alloc['cost_category'], 'Alloc returns cost_category');

$imp2 = $importService->getImport($importId);
assertEq('PO2026-001', $imp2['production_order_id'], 'DB production_order_id set');
assertEq('raw_material', $imp2['cost_category'], 'DB cost_category set');

// === Test 3: Allocate with overhead category ===
$result2 = $importService->importXml(str_replace(['PH5/001','PH500001','PH5 Prod'], ['PH5/002','PH500002','PH5 Overhead'], $PROD_XML), 'test_user');
$id2 = $result2['import_id'];
$importService->allocateToProduction($id2, 'PO2026-001', 'overhead', 'test_user');
$imp3 = $importService->getImport($id2);
assertEq('overhead', $imp3['cost_category'], 'Overhead category set');

// === Test 4: List imports includes production fields ===
$list = $importService->listImports(20);
$foundProd = false;
foreach ($list as $r) {
    if ($r['id'] === $importId) { $foundProd = true; assertEq('PO2026-001', $r['production_order_id'], 'List production_order_id'); }
}
assertTrue($foundProd, 'Import with PO found in list');

// === Test 5: Allocate on non-existent import ===
$threw = false;
try {
    $importService->allocateToProduction('nonexistent', 'PO999', 'raw_material', 'test_user');
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
assertTrue($threw, 'Non-existent allocation rejected');

// Cleanup
$pdo->exec("DELETE FROM einvoice_imports WHERE supplier_tax_code = 'PH500000001'");
$pdo->exec("DELETE FROM suppliers WHERE tax_code = 'PH500000001'");
$pdo->exec("DELETE FROM items WHERE name LIKE 'Nguyên liệu PH5%'");

results();

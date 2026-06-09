<?php
// Test Phase 4: E-invoice import → advance payment (tạm ứng) tracking

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

$PREPAY_XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<InvoiceData xmlns="http://www.gdt.gov.vn/2025/invoice">
  <TTChung><KHMSHDon>1</KHMSHDon><KHHDon>PH4/001</KHHDon><SHDon>PH400001</SHDon><NLap>2026-06-09</NLap></TTChung>
  <NBan><Ten>Cty Phase 4 Prepay</Ten><MST>PH400000001</MST></NBan>
  <DSHHDVu><HHDVu><STT>1</STT><THHDVu>Hàng PH4</THHDVu><DVTinh>Cái</DVTinh><SLuong>1</SLuong><DGia>20000000</DGia><ThTien>20000000</ThTien><TSuat>10</TSuat></HHDVu></DSHHDVu>
  <TToan><TgTCThue>20000000</TgTCThue><TgTThue>2000000</TgTThue><TgTTTBSo>22000000</TgTTTBSo></TToan>
</InvoiceData>
XML;

// Cleanup
$pdo->exec("DELETE FROM einvoice_imports WHERE supplier_tax_code = 'PH400000001'");
$pdo->exec("DELETE FROM suppliers WHERE tax_code = 'PH400000001'");
$pdo->exec("DELETE FROM items WHERE name = 'Hàng PH4'");

// Import
$result = $importService->importXml($PREPAY_XML, 'test_user', ['auto_goods_receipt' => false]);
$importId = $result['import_id'];
assertNotNull($importId, 'Import created for prepay test');

// === Test 1: Initial prepay is zero ===
$imp = $importService->getImport($importId);
assertEq(0, $imp['prepay_amount'], 'Initial prepay_amount = 0');
assertNull($imp['prepay_transaction_id'] ?? null, 'Initial prepay_transaction_id = null');

// === Test 2: Record prepay ===
$prepay = $importService->recordPrepay($importId, 10000000, 'ADV2026-001', 'test_user');
assertEq(10000000, $prepay['prepay_amount'], 'recordPrepay returns amount');
assertEq('ADV2026-001', $prepay['prepay_transaction_id'], 'recordPrepay returns txn id');

$imp2 = $importService->getImport($importId);
assertEq(10000000, $imp2['prepay_amount'], 'DB prepay_amount updated');
assertEq('ADV2026-001', $imp2['prepay_transaction_id'], 'DB prepay_transaction_id set');

// === Test 3: List imports includes prepay fields ===
$list = $importService->listImports(10);
$found = false;
foreach ($list as $r) { if ($r['id'] === $importId) { $found = true; assertEq(10000000, $r['prepay_amount'], 'List prepay_amount'); } }
assertTrue($found, 'Import found in list');

// === Test 4: Record on non-existent import ===
$threw = false;
try {
    $importService->recordPrepay('nonexistent', 1000, 'TXN001', 'test_user');
} catch (\InvalidArgumentException $e) {
    $threw = true;
    assertStringContains($e->getMessage(), 'Không tìm thấy', 'Error mentions Không tìm thấy');
}
assertTrue($threw, 'Non-existent prepay rejected');

// === Test 5: Multiple prepays accumulate ===
$importService->recordPrepay($importId, 5000000, 'ADV2026-002', 'test_user');
$imp3 = $importService->getImport($importId);
assertEq(15000000, $imp3['prepay_amount'], 'Prepay accumulated to 15M');

// Cleanup
$pdo->exec("DELETE FROM einvoice_imports WHERE supplier_tax_code = 'PH400000001'");
$pdo->exec("DELETE FROM suppliers WHERE tax_code = 'PH400000001'");
$pdo->exec("DELETE FROM items WHERE name = 'Hàng PH4'");

results();

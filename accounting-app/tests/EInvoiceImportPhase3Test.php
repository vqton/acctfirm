<?php
// Test Phase 3: E-invoice import → bank payment tracking

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
$journalService = new \Accounting\Domain\Service\JournalService($accountRepo, $txnRepo, $pdo, $auditLogger, $postingRuleService, $voucherService, $approvalRoutingService);
$importService = new \Accounting\Domain\Service\EInvoiceImportService($pdo, $supplierRepo, $accountRepo, $journalService, $auditLogger);

$PAY_XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<InvoiceData xmlns="http://www.gdt.gov.vn/2025/invoice">
  <TTChung><KHMSHDon>1</KHMSHDon><KHHDon>PH3/001</KHHDon><SHDon>PH300001</SHDon><NLap>2026-06-09</NLap></TTChung>
  <NBan><Ten>Cty Phase 3 Pay</Ten><MST>PH300000001</MST></NBan>
  <DSHHDVu><HHDVu><STT>1</STT><THHDVu>Hàng PH3</THHDVu><DVTinh>Cái</DVTinh><SLuong>2</SLuong><DGia>5000000</DGia><ThTien>10000000</ThTien><TSuat>10</TSuat></HHDVu></DSHHDVu>
  <TToan><TgTCThue>10000000</TgTCThue><TgTThue>1000000</TgTThue><TgTTTBSo>11000000</TgTTTBSo></TToan>
</InvoiceData>
XML;

// Cleanup
$pdo->exec("DELETE FROM einvoice_imports WHERE supplier_tax_code = 'PH300000001'");
$pdo->exec("DELETE FROM suppliers WHERE tax_code = 'PH300000001'");
$pdo->exec("DELETE FROM items WHERE name = 'Hàng PH3'");

// Import XML trước
$result = $importService->importXml($PAY_XML, 'test_user', ['auto_goods_receipt' => false]);
$importId = $result['import_id'];
assertNotNull($importId, 'Import created for payment test');

// === Test 1: Initial status is unpaid ===
$imp = $importService->getImport($importId);
assertEq('unpaid', $imp['payment_status'], 'Initial payment_status = unpaid');
assertEq(0, $imp['paid_amount'], 'Initial paid_amount = 0');

// === Test 2: Record full payment ===
$payResult = $importService->recordPayment($importId, 11000000, 'test_user');
assertEq('paid', $payResult['payment_status'], 'Full pay → status = paid');
assertEq(11000000, $payResult['paid_amount'], 'paid_amount = 11M');
assertEq(0, $payResult['remaining'], 'Remaining = 0');

$imp2 = $importService->getImport($importId);
assertEq('paid', $imp2['payment_status'], 'DB payment_status = paid');
assertEq(11000000, $imp2['paid_amount'], 'DB paid_amount = 11M');

// === Test 3: Record partial payment ===
$PAY_XML2 = str_replace(['PH3/001', 'PH300001'], ['PH3/002', 'PH300002'], $PAY_XML);
$result2 = $importService->importXml($PAY_XML2, 'test_user', ['auto_goods_receipt' => false]);
$importId2 = $result2['import_id'];

$payPartial = $importService->recordPayment($importId2, 5000000, 'test_user');
assertEq('partial', $payPartial['payment_status'], 'Partial pay → status = partial');
assertEq(5000000, $payPartial['paid_amount'], 'paid_amount = 5M');
assertEq(6000000, $payPartial['remaining'], 'Remaining = 6M');

// === Test 4: Pay more than remaining → rejected ===
$threw = false;
try {
    $importService->recordPayment($importId2, 7000000, 'test_user');
} catch (\InvalidArgumentException $e) {
    $threw = true;
    assertStringContains($e->getMessage(), 'vượt quá', 'Error mentions vượt quá');
}
assertTrue($threw, 'Over-payment rejected');

// === Test 5: Complete partial payment → paid ===
$payComplete = $importService->recordPayment($importId2, 6000000, 'test_user');
assertEq('paid', $payComplete['payment_status'], 'Second partial → status = paid');
assertEq(11000000, $payComplete['paid_amount'], 'Total paid = 11M');
assertEq(0, $payComplete['remaining'], 'Remaining = 0');

// === Test 6: Record payment on non-existent import ===
$threw = false;
try {
    $importService->recordPayment('nonexistent_import', 1000, 'test_user');
} catch (\InvalidArgumentException $e) {
    $threw = true;
    assertStringContains($e->getMessage(), 'Không tìm thấy', 'Error mentions Không tìm thấy');
}
assertTrue($threw, 'Non-existent import rejected');

// === Test 7: VAT summary includes payment stats ===
$summary = $importService->getVatSummary('2026-06');
assertTrue($summary['paid_count'] >= 2, 'VAT summary paid_count >= 2');
assertTrue($summary['total_paid'] >= 22000000, 'VAT summary total_paid >= 22M');

// Cleanup
$pdo->exec("DELETE FROM einvoice_imports WHERE supplier_tax_code = 'PH300000001'");
$pdo->exec("DELETE FROM suppliers WHERE tax_code = 'PH300000001'");
$pdo->exec("DELETE FROM items WHERE name = 'Hàng PH3'");

results();

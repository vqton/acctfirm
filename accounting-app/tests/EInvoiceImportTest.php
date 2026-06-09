<?php
// Test: EInvoiceImportService — import hóa đơn điện tử XML đầu vào
// Phase 1: Parse XML → create supplier → create items → post journal entry

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

$failed = 0;
$total = 0;

function assertEq($a, $b, $msg) {
    global $total, $failed;
    $total++;
    // Loose comparison — parseXml returns floats, PHP may report as int
    if ($a == $b) {
        echo "PASS: {$msg}\n";
    } else {
        echo "FAIL: {$msg} — expected " . var_export($b, true) . ", got " . var_export($a, true) . "\n";
        $failed++;
    }
}

function assertTrue($cond, $msg) {
    global $total, $failed;
    $total++;
    if ($cond) {
        echo "PASS: {$msg}\n";
    } else {
        echo "FAIL: {$msg} — expected true, got false\n";
        $failed++;
    }
}

function assertFalse($cond, $msg) {
    global $total, $failed;
    $total++;
    if (!$cond) {
        echo "PASS: {$msg}\n";
    } else {
        echo "FAIL: {$msg} — expected false, got true\n";
        $failed++;
    }
}

function assertNotNull($val, $msg) {
    global $total, $failed;
    $total++;
    if ($val !== null) {
        echo "PASS: {$msg}\n";
    } else {
        echo "FAIL: {$msg} — expected non-null, got null\n";
        $failed++;
    }
}

function assertNull($val, $msg) {
    global $total, $failed;
    $total++;
    if ($val === null) {
        echo "PASS: {$msg}\n";
    } else {
        echo "FAIL: {$msg} — expected null, got non-null\n";
        $failed++;
    }
}

function assertStringContains($haystack, $needle, $msg) {
    global $total, $failed;
    $total++;
    if (strpos($haystack, $needle) !== false || strpos($needle, $haystack) !== false) {
        echo "PASS: {$msg}\n";
    } else {
        echo "FAIL: {$msg} — expected '{$haystack}' to contain '{$needle}'\n";
        $failed++;
    }
}

function assertStringStartsWith($prefix, $str, $msg) {
    global $total, $failed;
    $total++;
    if (strpos($str, $prefix) === 0) {
        echo "PASS: {$msg}\n";
    } else {
        echo "FAIL: {$msg} — '{$str}' does not start with '{$prefix}'\n";
        $failed++;
    }
}

function results() {
    global $total, $failed;
    echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
    exit($failed > 0 ? 1 : 0);
}

// Setup: PDO + dependencies
$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new \Accounting\Infrastructure\Persistence\PDOAccountRepository($pdo);
$txnRepo = new \Accounting\Infrastructure\Persistence\PDOTransactionRepository($pdo);
$supplierRepo = new \Accounting\Infrastructure\Persistence\PDOSupplierRepository($pdo);
$auditLogger = new \Accounting\Infrastructure\Database\AuditLogger($pdo);
$voucherService = new \Accounting\Domain\Service\VoucherService($pdo);
$postingRuleService = new \Accounting\Domain\Service\PostingRuleService($pdo);
$approvalRoutingService = new \Accounting\Domain\Service\ApprovalRoutingService($pdo, $auditLogger);

$journalService = new JournalService(
    $accountRepo, $txnRepo, $pdo, $auditLogger,
    $postingRuleService, $voucherService, $approvalRoutingService
);

$importService = new EInvoiceImportService(
    $pdo, $supplierRepo, $accountRepo, $journalService, $auditLogger
);

// === Test data ===
$TCT_XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<InvoiceData xmlns="http://www.gdt.gov.vn/2025/invoice">
  <TTChung>
    <KHMSHDon>1</KHMSHDon>
    <KHHDon>01GTKT0/001</KHHDon>
    <SHDon>00000001</SHDon>
    <NLap>2026-06-09</NLap>
    <DVTTe>VND</DVTTe>
  </TTChung>
  <NBan>
    <Ten>Công ty TNHH Thương mại ABC</Ten>
    <MST>1234567890</MST>
    <DChi>123 Nguyễn Huệ, Q.1, TP.HCM</DChi>
  </NBan>
  <NMua>
    <Ten>Công ty TNHH Sản xuất XYZ</Ten>
    <MST>0987654321</MST>
    <DChi>456 Lê Lợi, TP.Đà Nẵng</DChi>
  </NMua>
  <DSHHDVu>
    <HHDVu>
      <STT>1</STT>
      <MHHDVu>SP001</MHHDVu>
      <THHDVu>Máy tính xách tay Dell XPS 15</THHDVu>
      <DVTinh>Cái</DVTinh>
      <SLuong>2</SLuong>
      <DGia>15000000</DGia>
      <TLCKhau>0</TLCKhau>
      <STCKhau>0</STCKhau>
      <ThTien>30000000</ThTien>
      <TSuat>10</TSuat>
    </HHDVu>
    <HHDVu>
      <STT>2</STT>
      <MHHDVu>SP002</MHHDVu>
      <THHDVu>Màn hình Dell 27 inch</THHDVu>
      <DVTinh>Cái</DVTinh>
      <SLuong>1</SLuong>
      <DGia>5000000</DGia>
      <TLCKhau>0</TLCKhau>
      <STCKhau>0</STCKhau>
      <ThTien>5000000</ThTien>
      <TSuat>10</TSuat>
    </HHDVu>
  </DSHHDVu>
  <TToan>
    <TgTCThue>35000000</TgTCThue>
    <TgTThue>3500000</TgTThue>
    <TgTTTBSo>38500000</TgTTTBSo>
  </TToan>
</InvoiceData>
XML;

$INCOMPLETE_XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<InvoiceData xmlns="http://www.gdt.gov.vn/2025/invoice">
  <NBan><Ten>Cty A</Ten><MST>1234567890</MST></NBan>
</InvoiceData>
XML;

$INVALID_XML = 'not xml at all {{{';

$NO_MST_XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<InvoiceData xmlns="http://www.gdt.gov.vn/2025/invoice">
  <TTChung><KHMSHDon>1</KHMSHDon><KHHDon>AB/001</KHHDon><SHDon>001</SHDon><NLap>2026-06-09</NLap></TTChung>
  <NBan><Ten>Cty Không MST</Ten></NBan>
</InvoiceData>
XML;

$ZERO_VAT_XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<InvoiceData xmlns="http://www.gdt.gov.vn/2025/invoice">
  <TTChung><KHMSHDon>1</KHMSHDon><KHHDon>00GTKT0/001</KHHDon><SHDon>00000999</SHDon><NLap>2026-06-09</NLap></TTChung>
  <NBan><Ten>Cty Zero VAT</Ten><MST>9999999990</MST></NBan>
  <NMua><Ten>Cty Mua</Ten><MST>1111111111</MST></NMua>
  <DSHHDVu><HHDVu><STT>1</STT><THHDVu>Dịch vụ tư vấn</THHDVu><DVTinh>Gói</DVTinh><SLuong>1</SLuong><DGia>20000000</DGia><ThTien>20000000</ThTien><TSuat>0%</TSuat></HHDVu></DSHHDVu>
  <TToan><TgTCThue>20000000</TgTCThue><TgTThue>0</TgTThue><TgTTTBSo>20000000</TgTTTBSo></TToan>
</InvoiceData>
XML;

$NO_BUYER_XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<InvoiceData xmlns="http://www.gdt.gov.vn/2025/invoice">
  <TTChung><KHMSHDon>1</KHMSHDon><KHHDon>NOBUY/001</KHHDon><SHDon>00001111</SHDon><NLap>2026-06-09</NLap></TTChung>
  <NBan><Ten>Cty Không Người Mua</Ten><MST>7777777777</MST></NBan>
  <DSHHDVu><HHDVu><STT>1</STT><THHDVu>Hàng hóa X</THHDVu><DVTinh>Cái</DVTinh><SLuong>1</SLuong><DGia>1000000</DGia><ThTien>1000000</ThTien><TSuat>10</TSuat></HHDVu></DSHHDVu>
  <TToan><TgTCThue>1000000</TgTCThue><TgTThue>100000</TgTThue><TgTTTBSo>1100000</TgTTTBSo></TToan>
</InvoiceData>
XML;

// Cleanup test data
$pdo->exec("DELETE FROM einvoice_imports WHERE supplier_tax_code IN ('1234567890','9999999990','7777777777')");
$pdo->exec("DELETE FROM transactions WHERE description LIKE '%HĐĐT: 00000001%' OR description LIKE '%HĐĐT: 00000999%' OR description LIKE '%HĐĐT: 00001000%' OR description LIKE '%HĐĐT: 00001111%'");
$pdo->exec("DELETE FROM suppliers WHERE tax_code IN ('1234567890','9999999990','7777777777')");
$pdo->exec("DELETE FROM items WHERE name IN ('Máy tính xách tay Dell XPS 15','Màn hình Dell 27 inch','Dịch vụ tư vấn','Hàng hóa X')");

function countImports($pdo) { return (int)$pdo->query("SELECT COUNT(*) FROM einvoice_imports")->fetchColumn(); }
function countSuppliers($pdo) { return (int)$pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn(); }
function countItems($pdo) { return (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn(); }

// === Test 1: Parse XML thành công ===
$parsed = $importService->parseXml($TCT_XML);
assertEq('01GTKT0/001_00000001', $parsed['fkey'], 'FKey = symbol + number');
assertEq('1', $parsed['template_code'], 'Template code');
assertEq('01GTKT0/001', $parsed['template_symbol'], 'Template symbol');
assertEq('00000001', $parsed['invoice_number'], 'Invoice number');
assertEq('2026-06-09', $parsed['invoice_date'], 'Invoice date');
assertEq('VND', $parsed['currency'], 'Currency');
assertEq('1234567890', $parsed['supplier']['tax_code'], 'Supplier tax code');
assertEq('Công ty TNHH Thương mại ABC', $parsed['supplier']['name'], 'Supplier name');
assertEq(2, count($parsed['items']), '2 items');
assertEq('Máy tính xách tay Dell XPS 15', $parsed['items'][0]['product_name'], 'Item 1 name');
assertEq(15000000, $parsed['items'][0]['unit_price'], 'Item 1 unit price');
assertEq(10, $parsed['items'][0]['vat_rate'], 'Item 1 VAT rate');
assertEq(35000000, $parsed['totals']['total_before_vat'], 'Total before VAT');
assertEq(3500000, $parsed['totals']['total_vat'], 'Total VAT');
assertEq(38500000, $parsed['totals']['grand_total'], 'Grand total');

// === Test 2: Parse XML không có TTChung ===
$threw = false;
try { $importService->parseXml($INCOMPLETE_XML); }
catch (\InvalidArgumentException $e) { $threw = true; assertStringContains($e->getMessage(), 'TTChung', 'Error mentions TTChung'); }
assertTrue($threw, 'Throw on missing TTChung');

// === Test 3: Parse XML sai định dạng ===
$threw = false;
try { $importService->parseXml($INVALID_XML); }
catch (\InvalidArgumentException $e) { $threw = true; assertStringContains($e->getMessage(), 'XML', 'Error mentions XML'); }
assertTrue($threw, 'Throw on invalid XML');

// === Test 4: Import XML tạo AP entry ===
$beforeCount = countImports($pdo);
$beforeSuppliers = countSuppliers($pdo);
$beforeItems = countItems($pdo);

$result = $importService->importXml($TCT_XML, 'test_user');
assertStringStartsWith('eimp_', $result['import_id'], 'Import ID prefix');
assertEq('01GTKT0/001_00000001', $result['fkey'], 'FKey returned');
assertNotNull($result['transaction_id'], 'Transaction ID created');
assertTrue(strpos($result['description'], 'HĐĐT: 00000001') !== false, 'Description includes invoice number');
assertEq(38500000, $result['grand_total'], 'Grand total');
assertEq(2, $result['items_count'], 'Items count');
assertEq($beforeCount + 1, countImports($pdo), 'Import record created');
assertEq($beforeSuppliers + 1, countSuppliers($pdo), 'Supplier auto-created');
assertEq($beforeItems + 2, countItems($pdo), 'Items auto-created');

// Kiểm tra ledger entries: 156 Dr 35M + 1331 Dr 3.5M + 331 Cr 38.5M
$leStmt = $pdo->prepare(
    "SELECT a.code, le.is_debit, SUM(le.amount) as amt
     FROM ledger_entries le
     JOIN accounts a ON le.account_id = a.id
     WHERE le.transaction_id = ? GROUP BY a.code, le.is_debit"
);
$leStmt->execute([$result['transaction_id']]);
$ledgers = [];
while ($row = $leStmt->fetch(PDO::FETCH_ASSOC)) {
    $key = $row['code'] . '_' . ($row['is_debit'] ? 'dr' : 'cr');
    $ledgers[$key] = (float)$row['amt'];
}
assertEq(35000000, $ledgers['156_dr'] ?? 0, 'Nợ 156 = 35M');
assertEq(3500000, $ledgers['1331_dr'] ?? 0, 'Nợ 1331 = 3.5M');
assertEq(38500000, $ledgers['331_cr'] ?? 0, 'Có 331 = 38.5M');

// Kiểm tra transaction status
$txnStmt = $pdo->prepare("SELECT status FROM transactions WHERE id = ?");
$txnStmt->execute([$result['transaction_id']]);
assertEq('posted', $txnStmt->fetchColumn(), 'Transaction status = posted');

// === Test 5: Import trùng bị chặn ===
$threw = false;
try { $importService->importXml($TCT_XML, 'test_user'); }
catch (\InvalidArgumentException $e) { $threw = true; assertStringContains($e->getMessage(), '01GTKT0/001_00000001', 'Duplicate FKey'); }
assertTrue($threw, 'Block duplicate import');

// === Test 6: checkDuplicate ===
assertTrue($importService->checkDuplicate('01GTKT0/001_00000001'), 'Duplicate check true');
assertFalse($importService->checkDuplicate('NONEXISTENT_999'), 'Duplicate check false');

// === Test 7: List imports ===
$list = $importService->listImports();
assertTrue(count($list) >= 1, 'At least 1 import in list');
assertEq('00000001', $list[0]['invoice_number'], 'List shows invoice number');
assertEq(38500000, $list[0]['grand_total'], 'List shows grand total');

// === Test 8: Get import detail ===
$detail = $importService->getImport($result['import_id']);
assertNotNull($detail, 'Get import detail exists');
assertEq('processed', $detail['status'], 'Status = processed');
assertEq($result['transaction_id'], $detail['transaction_id'], 'Transaction ID matches');
assertStringContains($detail['original_xml'], 'Máy tính xách tay', 'Original XML stored');
assertEq(2, count($detail['items']), 'Items parsed from JSON');

// === Test 9: Get non-existent import ===
assertNull($importService->getImport('nonexistent'), 'Null for non-existent');

// === Test 10: Parse XML không có MST người bán ===
$threw = false;
try { $importService->parseXml($NO_MST_XML); }
catch (\InvalidArgumentException $e) { $threw = true; assertStringContains($e->getMessage(), 'mã số thuế', 'Error mentions MST'); }
assertTrue($threw, 'Throw on missing supplier tax code');

// === Test 11: Import từ XML có thuế suất 0% ===
$parsed9 = $importService->parseXml($ZERO_VAT_XML);
assertEq(0, $parsed9['items'][0]['vat_rate'], 'VAT rate 0%');
assertEq(20000000, $parsed9['totals']['total_before_vat'], 'Total before VAT');
assertEq(0, $parsed9['totals']['total_vat'], 'Total VAT = 0');
assertEq(20000000, $parsed9['totals']['grand_total'], 'Grand total = 20M');

$result9 = $importService->importXml($ZERO_VAT_XML, 'test_user');
assertNotNull($result9['transaction_id'], 'Zero VAT import creates txn');

$le9Stmt = $pdo->prepare(
    "SELECT a.code, le.is_debit, SUM(le.amount) as amt
     FROM ledger_entries le
     JOIN accounts a ON le.account_id = a.id
     WHERE le.transaction_id = ? GROUP BY a.code, le.is_debit"
);
$le9Stmt->execute([$result9['transaction_id']]);
$ledgers9 = [];
while ($row = $le9Stmt->fetch(PDO::FETCH_ASSOC)) {
    $key = $row['code'] . '_' . ($row['is_debit'] ? 'dr' : 'cr');
    $ledgers9[$key] = (float)$row['amt'];
}
assertEq(20000000, $ledgers9['156_dr'] ?? 0, 'No VAT: Nợ 156 = 20M');
assertTrue(!isset($ledgers9['1331_dr']) || $ledgers9['1331_dr'] === 0, 'No VAT: Không có 1331');
assertEq(20000000, $ledgers9['331_cr'] ?? 0, 'No VAT: Có 331 = 20M');

// === Test 12: Supplier tái sử dụng nếu đã tồn tại (XML mới, supplier cũ) ===
$SUPPLIER_REUSE_XML = str_replace(
    ['00GTKT0/001', '00000999', '2026-06-09'],
    ['00GTKT0/002', '00001001', '2026-06-09'],
    $ZERO_VAT_XML
);
$beforeS2 = countSuppliers($pdo);
$importService->importXml($SUPPLIER_REUSE_XML, 'test_user');
assertEq($beforeS2, countSuppliers($pdo), 'Supplier re-used, not duplicated');

// === Test 13: Import không có NMua section ===
$parsed15 = $importService->parseXml($NO_BUYER_XML);
assertEq('', $parsed15['buyer']['tax_code'] ?? '', 'Buyer empty when no NMua');
$result15 = $importService->importXml($NO_BUYER_XML, 'test_user');
assertNotNull($result15, 'Import works without buyer section');

// Cleanup test data
$pdo->exec("DELETE FROM einvoice_imports WHERE supplier_tax_code IN ('1234567890','9999999990','7777777777')");
$pdo->exec("DELETE FROM transactions WHERE description LIKE '%HĐĐT: 00000001%' OR description LIKE '%HĐĐT: 00000999%' OR description LIKE '%HĐĐT: 00001000%' OR description LIKE '%HĐĐT: 00001111%'");
$pdo->exec("DELETE FROM suppliers WHERE tax_code IN ('1234567890','9999999990','7777777777')");
$pdo->exec("DELETE FROM items WHERE name IN ('Máy tính xách tay Dell XPS 15','Màn hình Dell 27 inch','Dịch vụ tư vấn','Hàng hóa X')");

results();

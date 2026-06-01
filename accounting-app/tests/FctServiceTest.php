<?php
// Test: Thuế nhà thầu nước ngoài (FCT) — tính toán, ghi nhận, tờ khai
// Tuân thủ TT 103/2014

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\FctService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// FctService without JournalService (backward compat — journals not posted)
$fct = new FctService($pdo);

// FctService with JournalService (full integration — journals posted to GL)
$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$journal = new JournalService($accountRepo, $txnRepo, $pdo);
$fctJournal = new FctService($pdo, $journal);

$failed = 0; $total = 0;
function assertEq($a, $b, $msg) { global $total, $failed; $total++; if ($a === $b) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected " . var_export($b, true) . ", got " . var_export($a, true) . "\n"; $failed++; } }
function assertTrue($cond, $msg) { global $total, $failed; $total++; if ($cond) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected true\n"; $failed++; } }
function assertFloatEq($a, $b, $msg) { global $total, $failed; $total++; if (abs((float)$a - (float)$b) < 1) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected {$b}, got {$a}\n"; $failed++; } }
function results() { global $total, $failed; echo "\n=== Results: {$total} tests, {$failed} failed ===\n"; exit($failed > 0 ? 1 : 0); }

// === Test 1: Tính toán khấu trừ cho từng loại dịch vụ ===
echo "\n--- Test 1: Withholding calculation per service type ---\n";

// Dịch vụ (VAT 5% + CIT 5%)
$r = $fct->calculateWithholding('services', 110000000);
assertFloatEq($r['vat_withholding'], 5238095, 'Services: VAT = 5,238,095 (110M * 5/105)');
assertFloatEq($r['cit_withholding'], 5500000, 'Services: CIT = 5,500,000 (110M * 5/100)');
assertFloatEq($r['net_payment'], 99261905, 'Services: Net = 99,261,905');

// Dịch vụ kèm hàng hóa (VAT 3% + CIT 2%)
$r = $fct->calculateWithholding('services_with_goods', 100000000);
assertFloatEq($r['vat_withholding'], 2912621, 'S+W: VAT = 2,912,621 (100M * 3/103)');
assertFloatEq($r['cit_withholding'], 2000000, 'S+W: CIT = 2,000,000 (100M * 2/100)');
assertFloatEq($r['net_payment'], 95087379, 'S+W: Net = 95,087,379');

// Phân phối (VAT 1% + CIT 1%)
$r = $fct->calculateWithholding('trading', 50000000);
assertFloatEq($r['vat_withholding'], 495050, 'Trading: VAT = 495,050 (50M * 1/101)');
assertFloatEq($r['cit_withholding'], 500000, 'Trading: CIT = 500,000 (50M * 1/100)');
assertFloatEq($r['net_payment'], 49004950, 'Trading: Net = 49,004,950');

// Cho thuê (VAT 5% + CIT 5%)
$r = $fct->calculateWithholding('leasing', 20000000);
assertFloatEq($r['vat_withholding'], 952381, 'Leasing: VAT = 952,381 (20M * 5/105)');
assertFloatEq($r['cit_withholding'], 1000000, 'Leasing: CIT = 1,000,000 (20M * 5/100)');
assertFloatEq($r['net_payment'], 18047619, 'Leasing: Net = 18,047,619');

// Khác (VAT 2% + CIT 2%)
$r = $fct->calculateWithholding('other', 30000000);
assertFloatEq($r['vat_withholding'], 588235, 'Other: VAT = 588,235 (30M * 2/102)');
assertFloatEq($r['cit_withholding'], 600000, 'Other: CIT = 600,000 (30M * 2/100)');
assertFloatEq($r['net_payment'], 28811765, 'Other: Net = 28,811,765');

// === Test 2: Validation — service type không hợp lệ ===
echo "\n--- Test 2: Validation errors ---\n";
$threw = false;
try { $fct->calculateWithholding('invalid_type', 1000000); } catch (\InvalidArgumentException $e) { $threw = true; }
assertTrue($threw, 'Invalid service type throws InvalidArgumentException');

$threw = false;
try { $fct->calculateWithholding('services', 0); } catch (\InvalidArgumentException $e) { $threw = true; }
assertTrue($threw, 'Zero contract value throws InvalidArgumentException');

$threw = false;
try { $fct->calculateWithholding('services', -1000); } catch (\InvalidArgumentException $e) { $threw = true; }
assertTrue($threw, 'Negative contract value throws InvalidArgumentException');

// === Test 3: Ghi nhận hợp đồng ===
echo "\n--- Test 3: Record withholding contract ---\n";

// Dọn dẹp dữ liệu test cũ
$testNo = 'FCT-TEST-' . date('YmdHis');
$pdo->exec("DELETE FROM fct_contracts WHERE contract_no LIKE 'FCT-TEST-%'");

$contract = $fct->recordWithholding(
    $testNo, 'Test Contractor Ltd', 'Singapore', 'services', 110000000, 'USD', 25400, '642', 'Test ghi nhan FCt', 'tester'
);

assertTrue(!empty($contract['id']), 'Contract has ID');
assertEq($contract['contract_no'], $testNo, 'Contract number matches');
assertEq($contract['contractor_country'], 'Singapore', 'Country matches');
assertFloatEq($contract['vat_withholding'], 5238095, 'VAT withholding matches calc');
assertFloatEq($contract['cit_withholding'], 5500000, 'CIT withholding matches calc');
assertFloatEq($contract['net_payment'], 99261905, 'Net payment matches calc');
assertEq($contract['status'], 'draft', 'Status is draft');
assertEq($contract['currency'], 'USD', 'Currency is USD');

// === Test 4: Ghi nhận hợp đồng với loại khác ===
echo "\n--- Test 4: Record contract (trading type) ---\n";

$testNo2 = 'FCT-TEST2-' . date('YmdHis');
$c2 = $fct->recordWithholding(
    $testNo2, 'Trading Corp', 'Japan', 'trading', 50000000, 'JPY', 170, '642', 'Test trading contract', 'tester'
);

assertFloatEq($c2['vat_withholding'], 495050, 'Trading VAT withholding');
assertFloatEq($c2['cit_withholding'], 500000, 'Trading CIT withholding');
assertEq($c2['currency'], 'JPY', 'Currency is JPY');

// === Test 5: Lấy danh sách hợp đồng ===
echo "\n--- Test 5: Get contracts list ---\n";
$all = $fct->getContracts();
assertTrue(count($all) >= 2, 'At least 2 contracts in list');
$found = false;
foreach ($all as $c) { if ($c['contract_no'] === $testNo) { $found = true; break; } }
assertTrue($found, 'Test contract found in list');

// === Test 6: Hủy hợp đồng ===
echo "\n--- Test 6: Cancel contract ---\n";
$cancelled = $fct->cancelContract($contract['id']);
assertEq($cancelled['status'], 'cancelled', 'Contract status = cancelled');

// Hủy lần 2 — phải lỗi
$threw = false;
try { $fct->cancelContract($contract['id']); } catch (\RuntimeException $e) { $threw = true; }
assertTrue($threw, 'Cancel already-cancelled contract throws');

// Hủy ID không tồn tại
$threw = false;
try { $fct->cancelContract('nonexistent'); } catch (\RuntimeException $e) { $threw = true; }
assertTrue($threw, 'Cancel nonexistent contract throws');

// === Test 7: Chuẩn bị tờ khai ===
echo "\n--- Test 7: Prepare declaration ---\n";

// Dọn dẹp tờ khai test cũ
$period = date('Y-m');
$pdo->exec("DELETE FROM fct_declarations WHERE period = '{$period}'");

$decl = $fct->prepareDeclaration($period, 'tester');
assertTrue(!empty($decl['id']), 'Declaration has ID');
assertEq($decl['period'], $period, 'Period matches');
assertTrue($decl['contract_count'] >= 0, 'Contract count >= 0');
assertEq($decl['status'], 'draft', 'Declaration status = draft');

// Chuẩn bị lại (ON DUPLICATE KEY) — không lỗi
$decl2 = $fct->prepareDeclaration($period, 'tester');
assertTrue(!empty($decl2['id']), 'Re-prepared declaration OK');

// === Test 8: Khóa tờ khai ===
echo "\n--- Test 8: Finalise declaration ---\n";
// Ensure test period is open
$pdo->exec("UPDATE accounting_periods SET status = 'open' WHERE period_code = '{$period}'");
$finalised = $fct->finalise($decl['id']);
assertEq($finalised['status'], 'finalised', 'Declaration status = finalised');

// Khóa lại — phải lỗi
$threw = false;
try { $fct->finalise($decl['id']); } catch (\RuntimeException $e) { $threw = true; }
assertTrue($threw, 'Finalise already-finalised throws');

// Khóa ID không tồn tại
$threw = false;
try { $fct->finalise('nonexistent'); } catch (\RuntimeException $e) { $threw = true; }
assertTrue($threw, 'Finalise nonexistent throws');

// === Test 9: Danh sách tờ khai ===
echo "\n--- Test 9: Get declarations list ---\n";
$decls = $fct->getDeclarations();
assertTrue(count($decls) >= 1, 'At least 1 declaration');
$found = false;
foreach ($decls as $d) { if ($d['id'] === $decl['id']) { $found = true; break; } }
assertTrue($found, 'Test declaration found in list');

echo "\n--- Test 10: Label mapping exists for all service types ---\n";
$types = ['services' => 'Dịch vụ', 'services_with_goods' => 'Dịch vụ kèm', 'trading' => 'Phân phối', 'leasing' => 'Cho thuê', 'other' => 'Khác'];
foreach ($types as $k => $v) {
    $r = $fct->calculateWithholding($k, 1000000);
    assertTrue(strlen($r['service_type_label']) > 0, "Label exists for {$k}");
}

// === Test 11: Record with journal posting (full integration) ===
echo "\n--- Test 11: Record FCT with journal posting ---\n";
// Ensure period is open
$pdo->exec("UPDATE accounting_periods SET status = 'open' WHERE period_code = '" . date('Y-m') . "'");

$testNo3 = 'FCT-JRNL-TEST-' . date('YmdHis');
$contract3 = $fctJournal->recordWithholding(
    $testNo3, 'Journal Test GmbH', 'Germany', 'services', 55000000,
    'EUR', 26000, '642', 'Test journal posting', 'tester'
);
assertEq($contract3['status'], 'posted', 'Contract status = posted with journal');
assertTrue(!empty($contract3['journal_id']), 'Contract has journal_id');
assertEq($contract3['expense_account_code'], '642', 'Expense account = 642');

// Verify journal entry exists
$stmtJrnl = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
$stmtJrnl->execute([$contract3['journal_id']]);
$jrnl = $stmtJrnl->fetch(PDO::FETCH_ASSOC);
assertTrue(!empty($jrnl), 'Journal transaction exists');
assertEq($jrnl['status'], 'posted', 'Journal status = posted');

// Verify balance impact: 642 (admin expense) should have balance change
$stmtBal642 = $pdo->prepare("SELECT balance FROM accounts WHERE code = '642'");
$stmtBal642->execute();
$bal642 = (float)$stmtBal642->fetchColumn();

$stmtBal331 = $pdo->prepare("SELECT balance FROM accounts WHERE code = '331'");
$stmtBal331->execute();
$bal331 = (float)$stmtBal331->fetchColumn();

// 642 is expense — debit increases, so balance > 0 after posting
// 331 is liability — credit increases, so balance > 0 after posting
assertTrue($bal642 > 0, '642 (admin expense) has balance after FCT posting');
assertTrue($bal331 > 0, '331 (AP) has balance after FCT posting');

// Verify FCT journal has correct Dr/Cr lines
$stmtLines = $pdo->prepare(
    "SELECT a.code, le.amount, le.is_debit
     FROM ledger_entries le
     JOIN accounts a ON a.id = le.account_id
     WHERE le.transaction_id = ?"
);
$stmtLines->execute([$contract3['journal_id']]);
$lines = $stmtLines->fetchAll(PDO::FETCH_ASSOC);
assertEq(count($lines), 2, 'Journal has 2 ledger lines (Dr + Cr)');

$foundDr = false;
$foundCr = false;
foreach ($lines as $l) {
    if ($l['is_debit'] && $l['code'] === '642') $foundDr = true;
    if (!$l['is_debit'] && $l['code'] === '331') $foundCr = true;
}
assertTrue($foundDr, 'Debit line: 642 (admin expense)');
assertTrue($foundCr, 'Credit line: 331 (AP)');

results();

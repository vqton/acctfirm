<?php
// Test: VAT Service — non-deductible VAT scan + GL reconciliation

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\VatService;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$vat = new VatService($pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $msg) { global $total, $failed; $total++; if ($a === $b) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected " . var_export($b, true) . ", got " . var_export($a, true) . "\n"; $failed++; } }
function assertTrue($cond, $msg) { global $total, $failed; $total++; if ($cond) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected true\n"; $failed++; } }
function assertFloatEq($a, $b, $msg) { global $total, $failed; $total++; if (abs((float)$a - (float)$b) < 1) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected {$b}, got {$a}\n"; $failed++; } }
function results() { global $total, $failed; echo "\n=== Results: {$total} tests, {$failed} failed ===\n"; exit($failed > 0 ? 1 : 0); }

// === Test 1: scanNonDeductibleVat — no cash-paid invoices in test period ===
echo "\n--- Test 1: scanNonDeductibleVat (empty result) ---\n";
$result = $vat->scanNonDeductibleVat('2019-01');
assertTrue(is_array($result), 'Returns array');
assertEq(count($result), 0, 'No non-deductible VAT in 2019-01');

// === Test 2: scanNonDeductibleVat — create an invoice ≥5M paid via cash ===
echo "\n--- Test 2: scanNonDeductibleVat (cash-paid invoice) ---\n";

// Tạo một supplier để test
$pdo->exec("DELETE FROM ap_invoices WHERE invoice_number LIKE 'VAT-TEST-%'");
$pdo->exec("DELETE FROM payment_allocations WHERE id LIKE 'pa-vat-test-%'");

// Get supplier for test
$supplierId = $pdo->query("SELECT id FROM suppliers LIMIT 1")->fetchColumn();
if (!$supplierId) $supplierId = 'SUP001';

$pdo->prepare("INSERT INTO ap_invoices (supplier_id, invoice_number, invoice_date, due_date, gross_amount, net_amount, vat_amount, vat_rate, balance, status, created_by)
    VALUES (?, ?, CURDATE(), CURDATE(), 5500000, 5000000, 500000, 10, 5500000, 'unpaid', 'tester')")
    ->execute([$supplierId, 'VAT-TEST-CASH-' . date('YmdHis')]);
$invId = (int)$pdo->lastInsertId();

// Tạo cash payment transaction — dùng TK 111 (Tiền mặt)
$txnId = 'vattxn-' . date('YmdHis');
$pdo->prepare("INSERT INTO transactions (id, reference, date, status, created_by) VALUES (?, 'VAT-TEST-PMT-1', NOW(), 'posted', 'tester')")
    ->execute([$txnId]);

// Lấy account ID cho TK 111 và 331
$cashAccId = $pdo->query("SELECT id FROM accounts WHERE code = '111' LIMIT 1")->fetchColumn();
$apAccId = $pdo->query("SELECT id FROM accounts WHERE code = '331' LIMIT 1")->fetchColumn();

if ($cashAccId && $apAccId) {
    $pdo->prepare("INSERT INTO ledger_entries (id, transaction_id, account_id, is_debit, amount) VALUES (?, ?, ?, 0, 5500000)")->execute([uniqid('le_'), $txnId, $apAccId]);
    $pdo->prepare("INSERT INTO ledger_entries (id, transaction_id, account_id, is_debit, amount) VALUES (?, ?, ?, 1, 5500000)")->execute([uniqid('le_'), $txnId, $cashAccId]);
}

// Tạo payment allocation
$pdo->prepare("INSERT INTO payment_allocations (payment_type, transaction_id, invoice_id, amount) VALUES ('ap', ?, ?, 5500000)")
    ->execute([$txnId, $invId]);

// Scan lại
$period = date('Y-m');
$result = $vat->scanNonDeductibleVat($period);
assertTrue(is_array($result), 'Scan returns array for current period');
if (count($result) > 0) {
    assertTrue($result[0]['vat_amount'] > 0, 'Flagged invoice has VAT amount');
    assertTrue((int)$result[0]['total_amount'] >= 5000000, 'Flagged invoice total >= 5M');
    assertEq(substr($result[0]['cash_account_code'], 0, 3), '111', 'Payment account is 111 (cash)');
}

// === Test 3: reconcileVatDeclaration — no declaration exists ===
echo "\n--- Test 3: reconcileVatDeclaration (no declaration) ---\n";
$recon = $vat->reconcileVatDeclaration('2019-01');
assertEq($recon['period'], '2019-01', 'Period preserved');
assertEq($recon['declaration']['vat_input'], 0, 'No declaration -> vat_input = 0');
assertEq($recon['declaration']['vat_output'], 0, 'No declaration -> vat_output = 0');
assertTrue(isset($recon['general_ledger']['vat_input_1331']), 'GL input key exists');
assertTrue(isset($recon['general_ledger']['vat_output_33311']), 'GL output key exists');
assertTrue(isset($recon['difference']), 'Difference key exists');
assertTrue(isset($recon['has_mismatch']), 'Mismatch flag exists');
assertEq($recon['tolerance'], 500, 'Tolerance = 500');

// === Test 4: reconcileVatDeclaration — prepare declaration then reconcile ===
echo "\n--- Test 4: reconcileVatDeclaration (after prepare) ---\n";
$decl = $vat->prepareDeclaration($period, 'tester');
assertTrue(!empty($decl['id']), 'VAT declaration prepared');

$recon2 = $vat->reconcileVatDeclaration($period);
assertTrue($recon2['declaration']['vat_input'] >= 0, 'Declaration input >= 0');
assertTrue($recon2['declaration']['vat_output'] >= 0, 'Declaration output >= 0');
assertTrue($recon2['declaration']['vat_payable'] !== null, 'Declaration payable exists');

// Dọn dẹp dữ liệu test
$pdo->prepare("DELETE FROM vat_declarations WHERE id = ?")->execute([$decl['id']]);
$pdo->exec("DELETE FROM payment_allocations WHERE transaction_id = '{$txnId}'");
$pdo->exec("DELETE FROM ledger_entries WHERE transaction_id = '{$txnId}'");
$pdo->exec("DELETE FROM transactions WHERE id = '{$txnId}'");
$pdo->exec("DELETE FROM ap_invoices WHERE id = {$invId}");

// === Test 5: finalise with proper rowCount check ===
echo "\n--- Test 5: finalise — proper rowCount behavior ---\n";
$decl2 = $vat->prepareDeclaration('2026-01', 'tester');
$f = $vat->finalise($decl2['id']);
assertEq($f['status'], 'finalised', 'VAT declaration finalised');
$threw = false;
try { $vat->finalise($decl2['id']); } catch (\RuntimeException $e) { $threw = true; }
assertTrue($threw, 'Finalise already-finalised throws');
$pdo->prepare("DELETE FROM vat_declarations WHERE id = ?")->execute([$decl2['id']]);

results();

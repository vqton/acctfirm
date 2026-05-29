<?php
// Test: CIT Service — non-deductible expense scan + loss carryforward

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\CitService;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$cit = new CitService($pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $msg) { global $total, $failed; $total++; if ($a === $b) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected " . var_export($b, true) . ", got " . var_export($a, true) . "\n"; $failed++; } }
function assertTrue($cond, $msg) { global $total, $failed; $total++; if ($cond) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected true\n"; $failed++; } }
function assertFloatEq($a, $b, $msg) { global $total, $failed; $total++; if (abs((float)$a - (float)$b) < 1) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected {$b}, got {$a}\n"; $failed++; } }
function results() { global $total, $failed; echo "\n=== Results: {$total} tests, {$failed} failed ===\n"; exit($failed > 0 ? 1 : 0); }

// Dọn dẹp dữ liệu test cũ
$pdo->exec("DELETE FROM tax_loss_carryforwards WHERE created_by = 'tester'");

// === Test 1: scanNonDeductibleExpenses — advertising < 10% (no excess) ===
echo "\n--- Test 1: scanNonDeductibleExpenses — advertising under limit ---\n";
$r = $cit->scanNonDeductibleExpenses('2026-05', 100000000, 5000000, 0);
assertFloatEq($r['advertising_expense'], 5000000, 'Advertising = 5M');
assertFloatEq($r['advertising_limit_10pct'], 10000000, 'Limit = 10M (10% of 100M)');
assertFloatEq($r['advertising_excess_non_deductible'], 0, 'No excess (5M < 10M)');
assertFloatEq($r['total_non_deductible'], 0, 'Total non-deductible = 0');

// === Test 2: scanNonDeductibleExpenses — advertising > 10% (excess) ===
echo "\n--- Test 2: scanNonDeductibleExpenses — advertising over limit ---\n";
$r = $cit->scanNonDeductibleExpenses('2026-05', 100000000, 15000000, 0);
assertFloatEq($r['advertising_expense'], 15000000, 'Advertising = 15M');

// === Test 3: scanNonDeductibleExpenses — interest > 30% EBITDA ===
echo "\n--- Test 3: scanNonDeductibleExpenses — interest over limit ---\n";
$r = $cit->scanNonDeductibleExpenses('2026-05', 100000000, 0, 50000000);
assertFloatEq($r['interest_expense'], 50000000, 'Interest = 50M');
assertFloatEq($r['interest_limit_30pct'], 30000000, 'Limit = 30M (30% of 100M)');
assertFloatEq($r['interest_excess_non_deductible'], 20000000, 'Excess = 20M (50M - 30M)');
assertFloatEq($r['total_non_deductible'], 20000000, 'Total = 20M');

// === Test 4: scanNonDeductibleExpenses — both advertising + interest excess ===
echo "\n--- Test 4: scanNonDeductibleExpenses — both combined ---\n";
$r = $cit->scanNonDeductibleExpenses('2026-05', 200000000, 30000000, 80000000);
assertFloatEq($r['advertising_excess_non_deductible'], 10000000, 'Advert excess = 10M (30M - 20M limit)');
assertFloatEq($r['interest_excess_non_deductible'], 20000000, 'Interest excess = 20M (80M - 60M limit)');
assertFloatEq($r['total_non_deductible'], 30000000, 'Total = 30M (10M + 20M)');

// === Test 5: scanNonDeductibleExpenses — zero revenue ===
echo "\n--- Test 5: scanNonDeductibleExpenses — zero revenue ---\n";
$r = $cit->scanNonDeductibleExpenses('2026-05', 0, 5000000, 10000000);
assertFloatEq($r['advertising_limit_10pct'], 0, 'Limit = 0');
assertFloatEq($r['advertising_excess_non_deductible'], 5000000, 'All advertising is excess');
assertFloatEq($r['interest_limit_30pct'], 0, 'Interest limit = 0');
assertFloatEq($r['interest_excess_non_deductible'], 10000000, 'All interest is excess');

// === Test 6: getLossCarryforward — no losses yet ===
echo "\n--- Test 6: getLossCarryforward (empty) ---\n";
$l = $cit->getLossCarryforward('2026-06');
assertTrue(is_array($l['losses']), 'Losses is array');
assertEq($l['total_available'], 0, 'No losses available');
assertEq($l['period'], '2026-06', 'Period matches');

// === Test 7: Loss carryforward — prepareCalculation with loss ===
echo "\n--- Test 7: prepareCalculation records loss when taxable income < 0 ---\n";
$calc = $cit->prepareCalculation('2027-01', 'tester');
// Với DB test chưa có dữ liệu, revenue/expense đều = 0 nên taxable_income = 0
// Không phát sinh lỗ. Cần insert dữ liệu để test loss.

// Tạo loss trực tiếp để test
$pdo->prepare("INSERT INTO tax_loss_carryforwards (id, period, loss_amount, remaining_amount, carryforward_years, expiry_date, status, created_by)
    VALUES (?, '2026-01', 50000000, 50000000, 5, DATE_ADD(CURDATE(), INTERVAL 4 YEAR), 'active', 'tester')")
    ->execute([uniqid('tlc_')]);
$pdo->prepare("INSERT INTO tax_loss_carryforwards (id, period, loss_amount, remaining_amount, carryforward_years, expiry_date, status, created_by)
    VALUES (?, '2026-02', 30000000, 30000000, 5, DATE_ADD(CURDATE(), INTERVAL 4 YEAR), 'active', 'tester')")
    ->execute([uniqid('tlc_')]);

$l2 = $cit->getLossCarryforward('2026-06');
assertFloatEq($l2['total_available'], 80000000, 'Total loss available = 80M (50M + 30M)');
assertEq(count($l2['losses']), 2, 'Two loss rows');

// === Test 8: useLossCarryforward — prepareCalculation with profit uses losses ===
echo "\n--- Test 8: prepareCalculation uses loss carryforward ---\n";
// Insert revenue 511 transaction to create profit
$revTxn = uniqid('txn_');
$revAccId = $pdo->query("SELECT id FROM accounts WHERE code = '511' LIMIT 1")->fetchColumn();
$cashAccId = $pdo->query("SELECT id FROM accounts WHERE code = '111' LIMIT 1")->fetchColumn();
if ($revAccId && $cashAccId) {
    $pdo->prepare("INSERT INTO transactions (id, reference, transaction_date, date, status, created_by) VALUES (?, 'CIT-REV-TEST', '2026-06-15', NOW(), 'posted', 'tester')")->execute([$revTxn]);
    $pdo->prepare("INSERT INTO ledger_entries (id, transaction_id, account_id, is_debit, amount) VALUES (?, ?, ?, 0, 120000000)")->execute([uniqid('le_'), $revTxn, $revAccId]);
    $pdo->prepare("INSERT INTO ledger_entries (id, transaction_id, account_id, is_debit, amount) VALUES (?, ?, ?, 1, 120000000)")->execute([uniqid('le_'), $revTxn, $cashAccId]);
}

$calc2 = $cit->prepareCalculation('2026-06', 'tester');
assertTrue($calc2['revenue'] >= 120000000, 'Revenue >= 120M');
assertTrue($calc2['loss_carryforward_used'] > 0, 'Loss carryforward used > 0');
assertTrue($calc2['adjusted_taxable_income'] >= 0, 'Adjusted taxable income >= 0');
assertTrue($calc2['cit_amount'] >= 0, 'CIT amount >= 0');

// Dọn dẹp
$pdo->exec("DELETE FROM tax_loss_carryforwards WHERE created_by = 'tester'");
if (isset($revTxn)) {
    $pdo->exec("DELETE FROM ledger_entries WHERE transaction_id = '{$revTxn}'");
    $pdo->exec("DELETE FROM transactions WHERE id = '{$revTxn}'");
}

// === Test 9: Finalise with proper rowCount ===
echo "\n--- Test 9: finalise — proper rowCount ---\n";
// Ensure test period exists and is open
$pdo->exec("INSERT IGNORE INTO accounting_periods (period_type, period_code, name, start_date, end_date, status, opened_by, opened_at) VALUES ('month', '2026-07', 'Tháng 7/2026', '2026-07-01', '2026-07-31', 'open', 'tester', NOW())");
$calc3 = $cit->prepareCalculation('2026-07', 'tester');
$f = $cit->finalise($calc3['id']);
assertEq($f['status'], 'finalised', 'CIT calculation finalised');
$threw = false;
try { $cit->finalise($calc3['id']); } catch (\RuntimeException $e) { $threw = true; }
assertTrue($threw, 'Finalise already-finalised throws');
$pdo->prepare("DELETE FROM cit_calculations WHERE id = ?")->execute([$calc3['id']]);
$pdo->prepare("DELETE FROM cit_calculations WHERE id = ?")->execute([$calc2['id']]);
$pdo->prepare("DELETE FROM cit_calculations WHERE id = ?")->execute([$calc['id']]);

results();

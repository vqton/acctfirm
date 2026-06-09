<?php
// Test: BC02 — Báo cáo Kết quả Hoạt động Kinh doanh (Mẫu B02-DN theo TT99)
// 14 tests: zero balances, full P&L, manual values, save/load, prior period, XBRL, snapshot, validation,
//           sub-account 6351 cho MS 24 (G02), account_tree cho MS 23, manual values period scope
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\FsService;
use Accounting\Domain\Service\XbrlGenerator;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$fs = new FsService($pdo, $accountRepo);
$xbrl = new XbrlGenerator($pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>1){echo"FAIL: {$m} expected {$b} got {$a}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}
function findValue($items, $maSo): float {
    foreach ($items as $r) { if ($r['ma_so'] === $maSo) return (float)$r['value']; }
    return 0;
}

// Reset DB
$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');
$pdo->exec('DELETE FROM fs_snapshots');
$pdo->exec("DELETE FROM business_config WHERE config_key LIKE 'BC02.manual.%'");
$pdo->exec("INSERT IGNORE INTO accounting_periods (period_code, start_date, end_date, status) VALUES ('2026', '2026-01-01', '2026-12-31', 'open')");

// Helper: post journal entry with balance update
function postEntry(PDO $pdo, string $ref, array $lines): void {
    $txnId = 'txn_' . uniqid();
    $pdo->prepare("INSERT INTO transactions (id,reference,description,`date`,transaction_date,status,created_by,created_at)
        VALUES (?,?,?,NOW(),CURDATE(),'posted','test',NOW())")->execute([$txnId, $ref, 'Test']);
    foreach ($lines as $l) {
        $acct = $pdo->query("SELECT id, type, normal_balance FROM accounts WHERE code = '{$l['code']}'")->fetch(PDO::FETCH_ASSOC);
        if (!$acct) throw new Exception("Account not found: {$l['code']}");
        $entryId = 'le_' . uniqid();
        $isDebit = $l['is_debit'] ? 1 : 0;
        $pdo->prepare("INSERT INTO ledger_entries (id,transaction_id,account_id,amount,is_debit)
            VALUES (?,?,?,?,?)")->execute([$entryId, $txnId, $acct['id'], $l['amount'], $isDebit]);
        $balDelta = $isDebit ? ($acct['normal_balance'] === 'D' ? $l['amount'] : -$l['amount'])
                             : ($acct['normal_balance'] === 'C' ? $l['amount'] : -$l['amount']);
        $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?")->execute([$balDelta, $acct['id']]);
    }
}

echo "\n=== Test 1: Zero balances — 21 items, all = 0 ===\n";
$items = $fs->getLineItems('BC02');
assertEq(21, count($items), 'BC02 has 21 line items');
$bc02 = $fs->generateBC02('2026');
assertEq(21, count($bc02), 'BC02 result has 21 rows');
foreach ($bc02 as $r) assertEq(0, $r['value'], "MS {$r['ma_so']} = 0");
$errors = $fs->validateBC02($bc02);
assertEq(0, count($errors), 'BC02 validation passes');

echo "\n=== Test 2: Revenue + Expense (MS 01=100M, MS 26=30M, MS 60=70M) ===\n";
postEntry($pdo, 'T2-REV', [['code'=>'112','amount'=>100000000,'is_debit'=>true], ['code'=>'511','amount'=>100000000,'is_debit'=>false]]);
postEntry($pdo, 'T2-EXP', [['code'=>'642','amount'=>30000000,'is_debit'=>true], ['code'=>'112','amount'=>30000000,'is_debit'=>false]]);
$bc02 = $fs->generateBC02('2026');
assertEq(100000000, findValue($bc02, '01'), 'MS 01 = 100M');
assertEq(0, findValue($bc02, '02'), 'MS 02 = 0');
assertEq(100000000, findValue($bc02, '10'), 'MS 10 = 100M');
assertEq(0, findValue($bc02, '11'), 'MS 11 = 0');
assertEq(100000000, findValue($bc02, '20'), 'MS 20 = 100M');
assertEq(30000000, findValue($bc02, '26'), 'MS 26 = 30M');
assertEq(70000000, findValue($bc02, '60'), 'MS 60 = 70M (100M rev - 30M expense, no tax)');
$errors = $fs->validateBC02($bc02);
assertEq(0, count($errors), 'BC02 validation passes');

echo "\n=== Test 3: Full P&L — revenue, finance, selling, admin ===\n";
postEntry($pdo, 'T3-FINREV', [['code'=>'112','amount'=>20000000,'is_debit'=>true], ['code'=>'515','amount'=>20000000,'is_debit'=>false]]);
postEntry($pdo, 'T3-FINCOST', [['code'=>'635','amount'=>5000000,'is_debit'=>true], ['code'=>'112','amount'=>5000000,'is_debit'=>false]]);
postEntry($pdo, 'T3-SELL', [['code'=>'641','amount'=>10000000,'is_debit'=>true], ['code'=>'112','amount'=>10000000,'is_debit'=>false]]);
$bc02 = $fs->generateBC02('2026');
assertEq(100000000, findValue($bc02, '01'), 'MS 01 = 100M');
assertEq(20000000, findValue($bc02, '22'), 'MS 22 = 20M');
assertEq(5000000, findValue($bc02, '23'), 'MS 23 = 5M');
assertEq(10000000, findValue($bc02, '25'), 'MS 25 = 10M');
assertEq(30000000, findValue($bc02, '26'), 'MS 26 = 30M');
// MS 30 = 100M+0+20M-(5M+10M+30M) = 75M
assertEq(75000000, findValue($bc02, '30'), 'MS 30 = 75M');
assertEq(75000000, findValue($bc02, '60'), 'MS 60 = 75M');
$errors = $fs->validateBC02($bc02);
assertEq(0, count($errors), 'BC02 validation passes');

echo "\n=== Test 4: Manual values — MS 21, 70, 71 via param ===\n";
$manual = ['21' => 2000000, '70' => 5000, '71' => 4800];
$bc02m = $fs->generateBC02('2026', $manual);
assertEq(2000000, findValue($bc02m, '21'), 'MS 21 = 2M');
assertEq(5000, findValue($bc02m, '70'), 'MS 70 = 5000');
assertEq(4800, findValue($bc02m, '71'), 'MS 71 = 4800');
// MS 30 = 75M + 2M = 77M (150M from prev test + 0 COGS - 30M - 20M - 15M - 5M - 10M...)
assertEq(77000000, findValue($bc02m, '30'), 'MS 30 = 77M (incl MS 21)');
$errors = $fs->validateBC02($bc02m);
assertEq(0, count($errors), 'BC02 validation with manual values');

echo "\n=== Test 5: Save/load manual values from business_config ===\n";
$fs->saveManualValues('BC02', '2026', ['21' => 3000000], 'tester');
$loaded = $fs->getManualValues('BC02', '2026');
assertTrue(isset($loaded['21']), 'MS 21 saved');
assertEq(3000000, $loaded['21'], 'MS 21 = 3M');
$fs->saveManualValues('BC02', '2026', ['21' => 2500000, '71' => 4900], 'tester');
$loaded2 = $fs->getManualValues('BC02', '2026');
assertEq(2500000, $loaded2['21'], 'MS 21 = 2.5M updated');
assertEq(4900, $loaded2['71'], 'MS 71 = 4900 added');

echo "\n=== Test 6: BC02 generate with config-saved manual values ===\n";
$saved = $fs->getManualValues('BC02', '2026');
$bc02e = $fs->generateBC02('2026', $saved);
assertEq(2500000, findValue($bc02e, '21'), 'MS 21 = 2.5M from config');
assertEq(4900, findValue($bc02e, '71'), 'MS 71 = 4900 from config');
$errors = $fs->validateBC02($bc02e);
assertEq(0, count($errors), 'BC02 validation with config values');

echo "\n=== Test 7: Prior period values from snapshot ===\n";
$pdo->prepare("INSERT INTO fs_snapshots (statement, period_code, period_end_date, data, created_by)
    VALUES ('BC02', '2025', '2025-12-31', ?, 'system')
    ON DUPLICATE KEY UPDATE data = VALUES(data)")->execute([json_encode([
    '01' => 80000000, '10' => 80000000, '20' => 80000000,
    '22' => 15000000, '23' => 3000000, '25' => 8000000, '26' => 25000000,
    '30' => 59000000, '50' => 59000000, '60' => 59000000,
    '70' => 4500, '71' => 4300,
])]);
$prior = $fs->getPriorPeriodValues('BC02', '2026');
assertTrue($prior !== null, 'Prior period exists');
assertEq(80000000, $prior['01'], 'Prior MS 01 = 80M');
assertEq(59000000, $prior['60'], 'Prior MS 60 = 59M');
assertEq(4500, $prior['70'], 'Prior MS 70 = 4500');

echo "\n=== Test 8: XBRL BC02 export with manual values ===\n";
$xml = $xbrl->generateBC02($bc02e, '2026', '0123456789', 'Công ty ABC');
assertTrue(str_contains($xml, '<xbrli:startDate>'), 'Has startDate');
assertTrue(str_contains($xml, '<xbrli:endDate>'), 'Has endDate');
assertTrue(str_contains($xml, 'gdt:DoanhThuBanHangVaCungCapDichVu'), 'Has DT tag');
assertTrue(str_contains($xml, 'gdt:ChiPhiQuanLyDoanhNghiep'), 'Has CP QLDN (26)');
assertTrue(str_contains($xml, 'gdt:LaiCoBan_TrenCoPhieu'), 'Has EPS (70)');
assertTrue(str_contains($xml, 'gdt:LaiSuyGiam_TrenCoPhieu'), 'Has EPS suy giảm (71)');
assertTrue(str_contains($xml, 'gdt:LaiLo_TuBanThanhLy_BDS_DauTu'), 'Has BĐS ĐT (21)');
$xbrlErrors = $xbrl->validate($xml);
assertEq(0, count($xbrlErrors), 'XBRL well-formed XML');

echo "\n=== Test 9: Snapshot includes manual values ===\n";
$snap = $pdo->query("SELECT data FROM fs_snapshots WHERE statement='BC02' AND period_code='2026' ORDER BY created_at DESC LIMIT 1")->fetchColumn();
$snapData = json_decode($snap, true);
assertTrue($snapData !== null, 'Snapshot decode OK');
assertTrue(isset($snapData['21']), 'Snapshot has MS 21');
assertEq(2500000, $snapData['21'], 'Snapshot MS 21 = 2.5M');
assertTrue(isset($snapData['71']), 'Snapshot has MS 71');
assertEq(4900, $snapData['71'], 'Snapshot MS 71 = 4900');

echo "\n=== Test 10: Validation detects imbalance ===\n";
$badData = [['ma_so' => '50', 'value' => 0], ['ma_so' => '30', 'value' => 100], ['ma_so' => '40', 'value' => 0]];
$badErrors = $fs->validateBC02($badData);
assertTrue(count($badErrors) > 0, 'Validation catches 50 != 30+40');

echo "\n=== Test 12: Sub-account 6351 — MS 24 only lãi vay, MS 23 tổng all 635* ===\n";
// Post lãi vay vào 6351 và tỷ giá vào 6352 — MS 24 chỉ lấy 6351, MS 23 lấy tổng (account_tree)
$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');
postEntry($pdo, 'T12-REV', [['code'=>'112','amount'=>100000000,'is_debit'=>true], ['code'=>'511','amount'=>100000000,'is_debit'=>false]]);
postEntry($pdo, 'T12-INTEREST', [['code'=>'6351','amount'=>3000000,'is_debit'=>true], ['code'=>'112','amount'=>3000000,'is_debit'=>false]]);
postEntry($pdo, 'T12-FX', [['code'=>'6352','amount'=>2000000,'is_debit'=>true], ['code'=>'112','amount'=>2000000,'is_debit'=>false]]);
$bc02 = $fs->generateBC02('2026');
assertEq(5000000, findValue($bc02, '23'), 'MS 23 = 5M (6351+6352)');
assertEq(3000000, findValue($bc02, '24'), 'MS 24 = 3M (only 6351)');
assertTrue(findValue($bc02, '23') > findValue($bc02, '24'), 'MS 23 > MS 24 (6352 makes MS 23 larger)');
$errors = $fs->validateBC02($bc02);
assertEq(0, count($errors), 'BC02 validation with sub-accounts');

echo "\n=== Test 12b: account_tree sums control + children ===\n";
// Nếu post thêm vào 635 (control account), account_tree vẫn lấy được
postEntry($pdo, 'T12-OTHER', [['code'=>'635','amount'=>1000000,'is_debit'=>true], ['code'=>'112','amount'=>1000000,'is_debit'=>false]]);
$bc02b = $fs->generateBC02('2026');
assertEq(6000000, findValue($bc02b, '23'), 'MS 23 = 6M (635+6351+6352)');
assertEq(3000000, findValue($bc02b, '24'), 'MS 24 = 3M (only 6351)');

echo "\n=== Test 13: Manual values scoped by period ===\n";
$fs->saveManualValues('BC02', '2025', ['21' => 1000000], 'tester');
$fs->saveManualValues('BC02', '2026', ['21' => 5000000], 'tester');
assertEq(1000000, $fs->getManualValues('BC02', '2025')['21'], '2025 MS 21 = 1M');
assertEq(5000000, $fs->getManualValues('BC02', '2026')['21'], '2026 MS 21 = 5M');

echo "\n=== Test 14: Gross loss warning (BR18) ===\n";
$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');
// Revenue 50M, COGS 80M → gross loss
postEntry($pdo, 'T14-REV', [['code'=>'112','amount'=>50000000,'is_debit'=>true], ['code'=>'511','amount'=>50000000,'is_debit'=>false]]);
postEntry($pdo, 'T14-COGS', [['code'=>'632','amount'=>80000000,'is_debit'=>true], ['code'=>'112','amount'=>80000000,'is_debit'=>false]]);
$bc02 = $fs->generateBC02('2026');
assertEq(50000000, findValue($bc02, '01'), 'MS 01 = 50M');
assertEq(80000000, findValue($bc02, '11'), 'MS 11 = 80M');
$warnings = $fs->getBC02Warnings($bc02);
assertTrue(count($warnings) > 0, 'Gross loss warning triggered');
assertTrue(str_contains($warnings[0], 'CẢNH BÁO'), 'Warning mentions gross loss');
$errors = $fs->validateBC02($bc02);
assertEq(0, count($errors), 'BC02 validation still passes');

echo "\n=== Test 15: No gross loss warning when COGS < revenue ===\n";
$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');
postEntry($pdo, 'T15-REV', [['code'=>'112','amount'=>100000000,'is_debit'=>true], ['code'=>'511','amount'=>100000000,'is_debit'=>false]]);
postEntry($pdo, 'T15-COGS', [['code'=>'632','amount'=>60000000,'is_debit'=>true], ['code'=>'112','amount'=>60000000,'is_debit'=>false]]);
$bc02b = $fs->generateBC02('2026');
assertEq(60000000, findValue($bc02b, '11'), 'MS 11 = 60M');
$warn2 = $fs->getBC02Warnings($bc02b);
assertEq(0, count($warn2), 'No gross loss warning');

echo "\n=== Test 16: Loss warning (profit < 0) ===\n";
$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');
postEntry($pdo, 'T16-REV', [['code'=>'112','amount'=>30000000,'is_debit'=>true], ['code'=>'511','amount'=>30000000,'is_debit'=>false]]);
postEntry($pdo, 'T16-EXP', [['code'=>'642','amount'=>50000000,'is_debit'=>true], ['code'=>'112','amount'=>50000000,'is_debit'=>false]]);
$bc02c = $fs->generateBC02('2026');
assertTrue(findValue($bc02c, '50') < 0, 'MS 50 is negative');
$warn3 = $fs->getBC02Warnings($bc02c);
assertTrue(count($warn3) > 0, 'Loss warning triggered');
assertTrue(str_contains($warn3[0], 'âm'), 'Warning mentions loss');

// Cleanup
$pdo->exec("DELETE FROM business_config WHERE config_key LIKE 'BC02.manual.%'");

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);
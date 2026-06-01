<?php
// Integration Smoke Test: 3 complete accounting cycles
// Circular 99/2025/TT-BTC — chứng từ → sổ → báo cáo tài chính

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Service\CashService;
use Accounting\Domain\Service\ApService;
use Accounting\Domain\Service\ArService;
use Accounting\Domain\Service\FsService;
use Accounting\Domain\Service\PeriodService;
use Accounting\Domain\Service\FixedAssetService;
use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Service\FctService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Infrastructure\Persistence\PDOSupplierRepository;
use Accounting\Infrastructure\Persistence\PDOCustomerRepository;
use Accounting\Infrastructure\Persistence\PDOItemRepository;
use Accounting\Infrastructure\Persistence\PDOWarehouseRepository;
use Accounting\Infrastructure\Persistence\PDOFixedAssetRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$supplierRepo = new PDOSupplierRepository($pdo);
$customerRepo = new PDOCustomerRepository($pdo);
$itemRepo = new PDOItemRepository($pdo);
$warehouseRepo = new PDOWarehouseRepository($pdo);
$faRepo = new PDOFixedAssetRepository($pdo);
$journal = new JournalService($accountRepo, $txnRepo, $pdo);
$cash = new CashService($accountRepo, $txnRepo, $journal, $pdo);
$ap = new ApService($pdo, $supplierRepo, $accountRepo, $journal);
$ar = new ArService($pdo, $accountRepo, $journal);
$fs = new FsService($pdo, $accountRepo);
$inventory = new InventoryService($accountRepo, $txnRepo, $itemRepo, $warehouseRepo, $journal, $pdo);
$faSvc = new FixedAssetService($faRepo, $accountRepo, $txnRepo, $journal, $pdo);
$periodSvc = new PeriodService($pdo, $accountRepo, $txnRepo, $journal);
$fctSvc = new FctService($pdo, $journal);

$failed = 0; $total = 0;
// assertEq(actual, expected, msg) — actual first per codebase convention
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if (abs((float)$a-(float)$b) > 1) { echo "FAIL: {$m} — got {$a}, want {$b}\n"; $failed++; } else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if (!$c) { echo "FAIL: {$m}\n"; $failed++; } else echo "PASS: {$m}\n";
}
function bal($code) { global $accountRepo; return $accountRepo->findByCode($code)->getBalance(); }
function tbOk() { global $pdo, $total, $failed;
    $stmt = $pdo->query("SELECT SUM(CASE WHEN is_debit=1 THEN amount ELSE 0 END) AS dr, SUM(CASE WHEN is_debit=0 THEN amount ELSE 0 END) AS cr FROM ledger_entries");
    $r = $stmt->fetch(PDO::FETCH_ASSOC); $total++; $ok = abs((float)$r['dr']-(float)$r['cr']) <= 10;
    if ($ok) { echo "PASS: TB Dr=Cr\n"; return; }
    echo "FAIL: TB Dr=" . $r['dr'] . " Cr=" . $r['cr'] . "\n"; $failed++;
}
function resetAll($pdo) {
    foreach (['debt_collection_settlements','debt_collection_approvals','debt_collection_promises','debt_collection_activities','debt_collection_queue'] as $t)
        $pdo->exec("DELETE FROM {$t}");
    $pdo->exec('DELETE FROM ar_payments'); $pdo->exec('DELETE FROM ar_invoices');
    $pdo->exec('DELETE FROM ap_payments'); $pdo->exec('DELETE FROM ap_invoices');
    $pdo->exec('DELETE FROM fc_transactions');
    $pdo->exec('DELETE FROM ledger_entries'); $pdo->exec('DELETE FROM transactions');
    $pdo->exec('DELETE FROM fs_snapshots');
    $pdo->exec('UPDATE accounts SET balance = 0');
    $pdo->exec('UPDATE customers SET balance = 0'); $pdo->exec('UPDATE suppliers SET balance = 0');
    $pdo->exec('UPDATE items SET stock_qty = 0'); $pdo->exec('DELETE FROM inventory_cost_layers');
}

// ── Setup period ──
$pdo->exec("INSERT IGNORE INTO accounting_periods (period_type,period_code,name,start_date,end_date,status,opened_by,opened_at) VALUES ('month','2026-07','T7/2026','2026-07-01','2026-07-31','open','test',NOW())");

// Test entities
$suppliers = $supplierRepo->findAll();
$supplierId = $suppliers[0]?->getId() ?? (function() use ($supplierRepo) {
    $s = new Accounting\Domain\Model\Supplier('sup_int', 'SUP-INT', 'Test Supplier INT');
    $supplierRepo->save($s); return 'sup_int'; })();
$customers = $ar->getCustomers();
$customerId = $customers[0]['id'] ?? null;
$item = $itemRepo->findByCode('VT001');

// ════════════════════════════════════════════════════════════
// CYCLE A: SERVICE FIRM — Revenue → AR → Cash → Expense → AP → Close → FS
// ════════════════════════════════════════════════════════════
echo "\n═══════ CYCLE A: SERVICE FIRM ═══════\n";
resetAll($pdo);

// A1: Bán dịch vụ 50tr + VAT 5tr (Dr 131 / Cr 511+33311)
echo "\n--- A1: Service revenue on credit ---\n";
$invA = $ar->recordInvoice($customerId, 'SI-INT-001', '2026-07-01', '2026-07-31', 50000000, 5000000, 10, 'Service revenue INT', 'tester');
assertTrue($invA['invoice_id'] > 0, 'AR invoice created');
assertEq(bal('131'), 55000000, 'AR (131) = 55M');
assertEq(bal('511'), 50000000, 'Revenue (511) = 50M');
assertEq(bal('33311'), 5000000, 'VAT output (33311) = 5M');
tbOk();

// A2: KH trả tiền mặt (Dr 111 / Cr 131)
echo "\n--- A2: Customer pays cash ---\n";
$cash->recordReceipt(55000000, '131', 'Customer payment INT', 'PT-INT-001', 'tester');
assertEq(bal('111'), 55000000, 'Cash (111) = 55M');
assertEq(bal('131'), 0, 'AR (131) = 0');
tbOk();

// A3: Chi VP phẩm 10tr + VAT 1tr (Dr 642 + Dr 1331 / Cr 111)
echo "\n--- A3: Office expense paid cash ---\n";
$cash->recordPayment(11000000, '642', 'Office supplies INT', 'PC-INT-001', 'tester', 1000000, 10);
assertEq(bal('111'), 44000000, 'Cash (111) = 44M');
assertEq(bal('642'), 10000000, 'Expense (642) = 10M');
assertEq(bal('1331'), 1000000, 'VAT input (1331) = 1M');
tbOk();

// A4: Mua dịch vụ tư vấn 15tr + VAT 1.5tr (Dr 642 + Dr 1331 / Cr 331)
echo "\n--- A4: AP invoice consulting ---\n";
$invAP = $ap->recordInvoice($supplierId, 'INV-INT-001', '2026-07-15', '2026-08-15', 15000000, 1500000, 10, 'Consulting INT', '642', 'tester');
assertTrue($invAP['invoice_id'] > 0, 'AP invoice created');
assertEq(bal('331'), 16500000, 'AP (331) = 16.5M');
assertEq(bal('642'), 25000000, 'Expense (642) = 25M (10+15)');
assertEq(bal('1331'), 2500000, 'VAT input (1331) = 2.5M');
tbOk();

// A5: Pay supplier via bank (Dr 331 / Cr 112)
echo "\n--- A5: Pay supplier ---\n";
$cash->recordBankReceipt(100000000, '511', 'Initial bank INT', 'BC-INT-001', 'tester');
$ap->recordPayment($invAP['invoice_id'], 16500000, 'tester');
assertEq(bal('331'), 0, 'AP (331) = 0');
assertEq(bal('112'), 83500000, 'Bank (112) = 83.5M (100-16.5)');
tbOk();

// A6: BC02 before closing (BC02 shows period revenue/expense, zeroed after close)
echo "\n--- A6: BC02 before closing entries ---\n";
$bc02 = $fs->generateBC02('2026');
$errors2 = $fs->validateBC02($bc02);
assertEq(count($errors2), 0, 'BC02 before close passes validation');
$netProfit = 0;
foreach ($bc02 as $r) { if ($r['ma_so'] === '60') $netProfit = $r['value']; }
// Revenue: 50M (service) + 100M (bank receipt → 511) = 150M
// Expense: 25M (642)
// Profit: 125M (no CIT yet)
assertEq($netProfit, 125000000, 'BC02 net profit before close = 125M');

// A7: Closing entries
echo "\n--- A7: Closing entries ---\n";
$periodSvc->executeClosingEntries('tester');
assertTrue(abs(bal('511')) < 1, 'Revenue zeroed');
assertTrue(abs(bal('642')) < 1, 'Expense zeroed');
assertTrue(abs(bal('911')) < 1, 'P&L (911) cleared');
assertEq(bal('421'), 125000000, 'RE (421) = 125M');
tbOk();

// A8: BC01 after closing (BC01 shows balances at period end)
echo "\n--- A8: BC01 after close ---\n";
$bc01 = $fs->generateBC01('2026');
assertTrue(count($bc01) >= 80, 'BC01 has 80+ rows');
$errors = $fs->validateBC01($bc01);
$totalAssets = 0; $totalEq = 0;
foreach ($bc01 as $r) {
    if ($r['ma_so'] === '280') $totalAssets = $r['value'];
    if ($r['ma_so'] === '440') $totalEq = $r['value'];
}
assertTrue($totalAssets > 0, 'Total assets > 0');
assertTrue($totalEq > 0, 'Total equity > 0');
$gap = abs($totalAssets - $totalEq);
assertTrue($gap <= 1, 'BC01 balanced after 1331/333 fix (gap=' . $gap . ')');
echo "\n--- CYCLE A COMPLETE ---\n";

// ════════════════════════════════════════════════════════════
// CYCLE B: TRADING FIRM — Purchase → Inventory → Sale → COGS → Close → FS
// ════════════════════════════════════════════════════════════
echo "\n═══════ CYCLE B: TRADING FIRM ═══════\n";
resetAll($pdo);

// B1: Receive goods (VT001 = material → 152, Dr 152 / Cr 331)
echo "\n--- B1: Receive goods into inventory ---\n";
$rcv = $inventory->receiveGoods($item->getId(), 100, 80000, [], 'GR-INT-001', 'tester', 'BATCH-B1', null);
assertTrue(isset($rcv['transaction_id']), 'Goods received via inventory');
assertEq($rcv['total_cost'], 8000000, 'Total cost = 8M');
assertEq(bal('152'), 8000000, 'Materials (152) = 8M');
assertEq(bal('331'), 8000000, 'AP (331) = 8M');
assertEq($itemRepo->findByCode('VT001')->getStockQty(), 100, 'Stock qty = 100');
tbOk();

// B2: Sell on credit (Dr 131 / Cr 511+33311)
echo "\n--- B2: Sell goods on credit ---\n";
$invB = $ar->recordInvoice($customerId, 'SI-INT-B001', '2026-07-10', '2026-07-31', 15000000, 1500000, 10, 'Goods sale INT', 'tester');
assertEq(bal('131'), 16500000, 'AR (131) = 16.5M');
assertEq(bal('511'), 15000000, 'Revenue (511) = 15M');
tbOk();

// B3: Issue goods (COGS, Dr 632 / Cr 152)
echo "\n--- B3: Record COGS ---\n";
$issue = $inventory->issueGoods($item->getId(), 50, 'sale', 'INT-SALE-001', 'tester');
assertTrue(isset($issue['total_cost']), 'Goods issued');
assertEq($itemRepo->findByCode('VT001')->getStockQty(), 50, 'Stock qty = 50');
assertEq(bal('632'), 4000000, 'COGS (632) = 4M (50x80k)');
assertEq(bal('152'), 4000000, 'Materials (152) = 4M (remaining)');
tbOk();

// B4: Customer pays cash
echo "\n--- B4: Customer pays cash ---\n";
$cash->recordReceipt(16500000, '131', 'Customer payment B', 'PT-INT-B001', 'tester');
assertEq(bal('131'), 0, 'AR cleared');
tbOk();

// B5: BC02 before close
echo "\n--- B5: BC02 before close ---\n";
$bc02b = $fs->generateBC02('2026');
$errB2 = $fs->validateBC02($bc02b);
assertEq(count($errB2), 0, 'BC02 OK');
$npB = 0;
foreach ($bc02b as $r) { if ($r['ma_so'] === '60') $npB = $r['value']; }
assertEq($npB, 11000000, 'BC02 net profit = 11M (15M rev - 4M COGS)');

// B6: Closing entries
echo "\n--- B6: Closing entries ---\n";
$periodSvc->executeClosingEntries('tester');
assertTrue(abs(bal('511')) < 1, 'Revenue zeroed');
assertTrue(abs(bal('632')) < 1, 'COGS zeroed');
assertTrue(abs(bal('911')) < 1, 'P&L cleared');
assertTrue(bal('421') > 0, 'RE > 0');
tbOk();
echo "\n--- CYCLE B COMPLETE ---\n";

// ════════════════════════════════════════════════════════════
// CYCLE C: MIXED — FA + FCT → Close → FS
// ════════════════════════════════════════════════════════════
echo "\n═══════ CYCLE C: FA + FCT ═══════\n";
resetAll($pdo);
$pdo->exec('DELETE FROM fixed_asset_depreciation');
$pdo->exec('DELETE FROM fixed_assets');
$pdo->exec('DELETE FROM fct_contracts');

// C1: FA acquisition 120M + VAT 12M via bank (Dr 211 + Dr 1332 / Cr 112)
echo "\n--- C1: FA acquisition via bank ---\n";
$cash->recordBankReceipt(200000000, '511', 'Initial bank C', 'BC-INT-C001', 'tester');
$faResult = $faSvc->recordAcquisition([
    'id' => 'fa_int_c1', 'code' => 'FA-INT-C1', 'name' => 'Computer INT',
    'purchase_date' => '2026-07-01', 'original_cost' => 120000000,
    'useful_life' => 5, 'salvage_value' => 0, 'depreciation_method' => 'straight_line',
    'fa_category' => 'tangible',
], 'purchase_bank', '112', 'tester', 12000000, '1332');
assertTrue(isset($faResult['fixed_asset_id']), 'FA acquired');
assertEq(bal('211'), 120000000, 'FA (211) = 120M');
assertEq(bal('1332'), 12000000, 'VAT 1332 = 12M');
assertEq(bal('112'), 68000000, 'Bank (112) = 68M (200-132)');
tbOk();

// C2: Depreciation (Dr 627 / Cr 2141, 120M/60th=2M)
echo "\n--- C2: Monthly depreciation ---\n";
$depResults = $faSvc->postMonthlyDepreciation('2026-07', 'tester');
assertTrue(count($depResults) >= 1, 'Depreciation posted');
assertEq($depResults[0]['amount'] ?? 0, 2000000, 'Monthly dep = 2M');
// 2141 is contra-asset (credit-normal, type='asset'). Credit entry → debit() → -balance
assertEq(bal('2141'), -2000000, 'Accum dep (2141) = -2M (contra-asset, credit-normal)');
assertEq(bal('627'), 2000000, 'Dep expense (627) = 2M');
tbOk();

// C3: FCT — foreign contractor (Dr 642 / Cr 331, TT 103/2014)
echo "\n--- C3: FCT contract ---\n";
$fct = $fctSvc->recordWithholding('FCT-INT-001', 'Foreign Consulting Ltd', 'US', 'services', 50000000, 'VND', 1, '642', 'Consulting INT', 'tester');
assertTrue(isset($fct['id']), 'FCT contract recorded');
assertEq(bal('642'), 50000000, 'FCT expense (642) = 50M');
assertEq(bal('331'), 50000000, 'AP (331) = 50M');
tbOk();

// C4: BC02 before close
echo "\n--- C4: BC02 before close ---\n";
$bc02c = $fs->generateBC02('2026');
$errC2 = $fs->validateBC02($bc02c);
assertEq(count($errC2), 0, 'BC02 OK');
$npC = 0;
foreach ($bc02c as $r) { if ($r['ma_so'] === '60') $npC = $r['value']; }
// BC02 line items: MS 30 (operating profit) = 20+21+22-(23+25+26)
// 627 (dep) is production cost, not period expense — doesn't flow through BC02
// Expenses in BC02: 642 (FCT 50M) = 50M. Revenue: 200M. Profit: 150M.
assertEq($npC, 150000000, 'BC02 net profit = 150M (200M rev - 50M FCT exp; 627 dep excluded from BC02)');

// C5: Closing entries
echo "\n--- C5: Closing entries ---\n";
$periodSvc->executeClosingEntries('tester');
assertTrue(abs(bal('511')) < 1, 'Revenue zeroed');
assertTrue(abs(bal('642')) < 1, 'Expense zeroed');
assertTrue(abs(bal('627')) < 1, 'Dep expense zeroed');
assertTrue(abs(bal('421')) > 0, 'RE > 0');
assertEq(bal('421'), 148000000, 'RE (421) = 148M');
tbOk();
echo "\n--- CYCLE C COMPLETE ---\n";

// ── Final system-wide invariant ──
echo "\n═══════ FINAL: System-wide Dr = Cr ═══════\n";
$all = $accountRepo->findAll();
$dr = 0; $cr = 0;
foreach ($all as $a) {
    $b = $a->getBalance();
    if (abs($b) < 1) continue;
    if (in_array($a->getType(), ['asset', 'expense'])) $dr += $b;
    else $cr += $b;
}
assertEq(round($cr, 0), round($dr, 0), 'Global TB: Dr=Cr (Dr=' . round($dr) . ' Cr=' . round($cr) . ')');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

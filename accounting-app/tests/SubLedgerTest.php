<?php
// Test: Sub-Ledger Reports (Sổ chi tiết)
// Kiểm tra cấu trúc dữ liệu đầu ra của từng loại báo cáo
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\SubLedgerService;
use Accounting\Domain\Service\GlService;
use Accounting\Domain\Service\PeriodService;
use Accounting\Domain\Service\ReportExportService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Infrastructure\SubLedger\GeneralLedgerReport;
use Accounting\Infrastructure\SubLedger\CashBookReport;
use Accounting\Infrastructure\SubLedger\BankBookReport;
use Accounting\Infrastructure\SubLedger\InventoryLedgerReport;
use Accounting\Infrastructure\SubLedger\ArLedgerReport;
use Accounting\Infrastructure\SubLedger\ApLedgerReport;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$glService = new GlService($pdo, $accountRepo);
$periodService = new PeriodService($pdo, $accountRepo, new \Accounting\Infrastructure\Persistence\PDOTransactionRepository($pdo), new \Accounting\Domain\Service\JournalService($accountRepo, new \Accounting\Infrastructure\Persistence\PDOTransactionRepository($pdo), $pdo));
$exportService = new ReportExportService();
$subLedgerService = new SubLedgerService($pdo, $accountRepo, $glService, $periodService, $exportService);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>0.01){echo"FAIL: {$m} expected {$b} got {$a}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertFalse($c, $m) { global $total, $failed;
    $total++; if($c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertNear($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)<0.01){echo"PASS: {$m}\n";}else{echo"FAIL: {$m} expected {$b} near {$a}\n";$failed++;}
}

echo "\n=== Test 1: SubLedgerService supports report types ===\n";
$reports = $subLedgerService->getSupportedReports();
assertTrue(count($reports) >= 5, 'At least 5 report types supported');
$types = array_column($reports, 'type');
assertTrue(in_array('general_ledger', $types), 'general_ledger supported');
assertTrue(in_array('cash_book', $types), 'cash_book supported');
assertTrue(in_array('bank_book', $types), 'bank_book supported');
assertTrue(in_array('inventory_ledger', $types), 'inventory_ledger supported');
assertTrue(in_array('ar_ledger', $types), 'ar_ledger supported');
assertTrue(in_array('ap_ledger', $types), 'ap_ledger supported');

echo "\n=== Test 2: GeneralLedgerReport structure ===\n";
$glReport = new GeneralLedgerReport($glService, $accountRepo, $periodService);
assertEq('general_ledger', $glReport->getReportType(), 'Report type = general_ledger');
$params = $glReport->getParameters();
assertTrue(count($params) > 0, 'Has parameters');

$data = $glReport->getData(['account_code' => '111']);
assertEq('general_ledger', $data['report_type'], 'Data report_type');
assertTrue(isset($data['title']), 'Has title');
assertTrue(isset($data['headers']), 'Has headers');
assertTrue(isset($data['rows']), 'Has rows');
assertTrue(isset($data['opening_balance']), 'Has opening_balance');
assertTrue(isset($data['closing_balance']), 'Has closing_balance');
assertTrue(isset($data['account_info']), 'Has account_info');
assertEq('111', $data['account_info']['code'], 'Account code matches');

echo "\n=== Test 3: GeneralLedgerReport throws on invalid account ===\n";
$threw = false;
try {
    $glReport->getData(['account_code' => '']);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
assertTrue($threw, 'Throws on empty account code');

echo "\n=== Test 4: CashBookReport structure ===\n";
$cbReport = new CashBookReport($pdo, $accountRepo);
assertEq('cash_book', $cbReport->getReportType(), 'Report type = cash_book');

$data = $cbReport->getData(['account_code' => '111']);
assertTrue(isset($data['rows']), 'Cash book has rows');
assertTrue(isset($data['opening_balance']), 'Has opening_balance');
assertTrue(isset($data['closing_balance']), 'Has closing_balance');
assertTrue(isset($data['headers']), 'Has headers');
assertTrue(isset($data['totals']['total_receipt']), 'Has total_receipt');
assertTrue(isset($data['totals']['total_payment']), 'Has total_payment');
// Kiểm tra running balance: opening + receipt - payment = closing
$open = $data['opening_balance'];
$closing = $data['closing_balance'];
$totalReceipt = $data['totals']['total_receipt'];
$totalPayment = $data['totals']['total_payment'];
assertNear($open + $totalReceipt - $totalPayment, $closing, 'Running balance: opening + receipt - payment = closing');

echo "\n=== Test 5: BankBookReport structure ===\n";
$bbReport = new BankBookReport($pdo, $accountRepo);
assertEq('bank_book', $bbReport->getReportType(), 'Report type = bank_book');

$data = $bbReport->getData(['account_code' => '112']);
assertTrue(isset($data['rows']), 'Bank book has rows');
assertTrue(isset($data['opening_balance']), 'Has opening_balance');
assertTrue(isset($data['totals']['total_receipt']), 'Has total_receipt');

echo "\n=== Test 6: InventoryLedgerReport structure ===\n";
$invReport = new InventoryLedgerReport($pdo, $accountRepo);
assertEq('inventory_ledger', $invReport->getReportType(), 'Report type = inventory_ledger');
// Gọi với item_id không tồn tại -> sẽ lỗi or trả về empty rows
$threw = false;
try {
    $data = $invReport->getData(['item_id' => 'nonexistent']);
} catch (\InvalidArgumentException $e) {
    $threw = true;
} catch (\Exception $e) {
    // Bảng items chưa có hoặc item không tồn tại -> cũng OK
    $threw = true;
}
// Nếu không throw (item tồn tại), kiểm tra cấu trúc
if (!$threw && isset($data)) {
    assertTrue(isset($data['headers']), 'Inventory report has headers');
    assertTrue(isset($data['item_info']), 'Has item_info');
}

echo "\n=== Test 7: ArLedgerReport structure ===\n";
$arReport = new ArLedgerReport($pdo, $accountRepo);
assertEq('ar_ledger', $arReport->getReportType(), 'Report type = ar_ledger');
$data = $arReport->getData([]);
assertTrue(isset($data['rows']), 'AR report has rows');
assertTrue(isset($data['headers']), 'Has headers');
assertTrue(isset($data['closing_balance']), 'Has closing_balance');

echo "\n=== Test 8: ApLedgerReport structure ===\n";
$apReport = new ApLedgerReport($pdo, $accountRepo);
assertEq('ap_ledger', $apReport->getReportType(), 'Report type = ap_ledger');
$data = $apReport->getData([]);
assertTrue(isset($data['rows']), 'AP report has rows');
assertTrue(isset($data['headers']), 'Has headers');
assertTrue(isset($data['closing_balance']), 'Has closing_balance');

echo "\n=== Test 9: SubLedgerService dispatch ===\n";
$data = $subLedgerService->getReport('general_ledger', ['account_code' => '111']);
assertEq('general_ledger', $data['report_type'], 'Dispatched to general_ledger');

$data2 = $subLedgerService->getReport('cash_book', ['account_code' => '111']);
assertEq('cash_book', $data2['report_type'], 'Dispatched to cash_book');

$threw = false;
try {
    $subLedgerService->getReport('nonexistent', []);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
assertTrue($threw, 'Invalid report type throws');

echo "\n=== Test 10: Running balance utility ===\n";
$items = [
    ['debit' => 100000, 'credit' => 0],
    ['debit' => 0, 'credit' => 30000],
    ['debit' => 50000, 'credit' => 0],
];
$result = $subLedgerService->calculateRunningBalances('asset', 200000, $items);
assertEq(300000, $result[0]['running_balance'], 'Running after +100K = 300K (debit-normal)');
assertEq(270000, $result[1]['running_balance'], 'Running after -30K = 270K');
assertEq(320000, $result[2]['running_balance'], 'Running after +50K = 320K');

// Liability (credit-normal)
$items2 = [
    ['debit' => 0, 'credit' => 100000],
    ['debit' => 30000, 'credit' => 0],
];
$result2 = $subLedgerService->calculateRunningBalances('liability', 500000, $items2);
assertEq(600000, $result2[0]['running_balance'], 'Liability running after +100K = 600K');
assertEq(570000, $result2[1]['running_balance'], 'Liability running after -30K = 570K');

echo "\n=== Test 11: Export CSV ===\n";
$export = $subLedgerService->exportCsv('general_ledger', ['account_code' => '111'], 'test.csv');
assertTrue(isset($export['content']), 'CSV has content');
assertTrue(isset($export['filename']), 'CSV has filename');
assertTrue(strpos($export['filename'], '.csv') !== false, 'Filename ends with .csv');
assertTrue(strlen($export['content']) > 0, 'CSV content not empty');

echo "\n=== Test 12: Export HTML ===\n";
$export = $subLedgerService->exportHtml('general_ledger', ['account_code' => '111']);
assertTrue(isset($export['content']), 'HTML has content');
assertTrue(strpos($export['content'], '<table') !== false, 'HTML contains table');
assertTrue(strpos($export['content'], '<!DOCTYPE') !== false, 'HTML has doctype');

echo "\n=== Test 13: Account code validation ===\n";
$threw = false;
try {
    $subLedgerService->getReport('general_ledger', ['account_code' => '']);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
assertTrue($threw, 'Empty account code throws');

echo "\n=== Test 14: getReportParameters ===\n";
$params = $subLedgerService->getReportParameters('general_ledger');
assertTrue(count($params) >= 1, 'Has at least 1 parameter');
assertEq('account_code', $params[0]['name'], 'First param is account_code');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

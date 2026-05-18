<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\CashReportService;
use Accounting\Domain\Service\CashService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$cash = new CashService($accountRepo, $txnRepo, $pdo);
$report = new CashReportService($pdo, $accountRepo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>1){echo"FAIL: {$m} expected {$a} got {$b}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');

echo "\n=== Test 1: Cash position report ===\n";
$cash->recordReceipt(5000000, '511', 'Cash sale', 'PT-001', 'tester');
$cash->recordBankDeposit(3000000, 'Deposit to bank', 'BC-001', 'tester');
$cash->recordBankInterest(100000, 'Interest', 'BC-002', 'tester');
$cash->recordPayment(1000000, '642', 'Office rent', 'PC-001', 'tester');

$pos = $report->getCashPosition();
assertTrue($pos['cash_balance'] >= 1000000, 'Cash (111) has balance');
assertTrue($pos['bank_balance'] >= 3100000, 'Bank (112) has balance');
assertTrue($pos['total'] > 0, 'Total cash + bank > 0');

echo "\n=== Test 2: Bank ledger ===\n";
$ledger = $report->getBankLedger();
assertTrue(count($ledger) >= 2, 'Bank ledger has entries');
assertTrue(isset($ledger[0]['date']), 'Ledger has date');
assertTrue(isset($ledger[0]['description']), 'Ledger has description');
assertTrue(isset($ledger[0]['amount']), 'Ledger has amount');

echo "\n=== Test 3: Daily cash flow ===\n";
$flow = $report->getDailyCashFlow(date('Y-m-d'), date('Y-m-d'));
assertTrue(count($flow) >= 1, 'Cash flow has entries');
assertTrue($flow[0]['receipts'] > 0, 'Has receipts');
assertTrue($flow[0]['payments'] > 0, 'Has payments');

echo "\n=== Test 4: Cash concentration (bank detail by account) ===\n";
$conc = $report->getCashConcentration();
assertTrue(count($conc) >= 1, 'Concentration has entries');

echo "\n=== Test 5: Cash flow trend (7-day) ===\n";
$trend = $report->getCashFlowTrend(7);
assertTrue(count($trend) >= 1, 'Trend has entries');
assertTrue(isset($trend[0]['date']), 'Trend has date');
assertTrue(isset($trend[0]['receipts']), 'Trend has receipts');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

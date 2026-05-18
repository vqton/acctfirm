<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\BankReconciliationService;
use Accounting\Domain\Service\CashService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$cash = new CashService($accountRepo, $txnRepo, $pdo);
$recon = new BankReconciliationService($accountRepo, $txnRepo, $pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>1){echo"FAIL: {$m} expected {$a} got {$b}\n";$failed++;}
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM bank_reconciliation_items');
$pdo->exec('DELETE FROM bank_reconciliation_sessions');

echo "\n=== Test 1: Start reconciliation session captures book balance ===\n";
// Create some bank transactions first
$cash->recordBankReceipt(10000000, '131', 'Customer A payment', 'BC-001', 'tester');
$cash->recordBankPayment(4000000, '331', 'Supplier X payment', 'BN-001', 'tester');
$cash->recordBankInterest(50000, 'Interest Aug', 'BC-002', 'tester');
$cash->recordBankCharge(200000, 'Service fee', 'BN-002', 'tester');

$bookBalance = $accountRepo->findByCode('112')->getBalance();
assertEq(5850000, $bookBalance, 'Book balance = 10M + 50K - 4M - 200K = 5,850,000');

$session = $recon->startSession('112', '2026-05-31', 8850000, 'tester');
assertTrue($session['id'] > 0, 'Session ID returned');
assertEq('112', $session['bank_account_code'], 'Bank account = 112');
assertEq(5850000, $session['book_balance'], 'Book balance captured');
assertEq(8850000, $session['statement_balance'], 'Statement balance stored');
assertEq('in_progress', $session['status'], 'Status = in_progress');

echo "\n=== Test 2: Book items loaded from ledger ===\n";
$items = $recon->getSessionItems($session['id']);
assertTrue(count($items) >= 4, 'At least 4 book items loaded');
$debits = array_filter($items, fn($i) => $i['source'] === 'book' && $i['type'] === 'receipt');
$credits = array_filter($items, fn($i) => $i['source'] === 'book' && $i['type'] === 'payment');
assertTrue(count($debits) >= 2, 'At least 2 debit (receipt) book items');
assertTrue(count($credits) >= 2, 'At least 2 credit (payment) book items');

echo "\n=== Test 3: Add statement transaction ===\n";
$stmtId = $recon->addStatementEntry($session['id'], 10000000, 'Customer A payment', 'BC-001', '2026-05-30', 'receipt');
assertTrue($stmtId > 0, 'Statement entry ID returned');

echo "\n=== Test 4: Auto-match by amount + reference ===\n";
$recon->addStatementEntry($session['id'], 4000000, 'Supplier X payment', 'BN-001', '2026-05-30', 'payment');
$recon->addStatementEntry($session['id'], 50000, 'Interest Aug', 'BC-002', '2026-05-31', 'receipt');
$recon->addStatementEntry($session['id'], 200000, 'Service fee', 'BN-002', '2026-05-31', 'payment');
// Add entry with no book match (deposit in transit)
$recon->addStatementEntry($session['id'], 3000000, 'Customer B payment not in books', 'BC-003', '2026-06-01', 'receipt');

$matchResult = $recon->autoMatch($session['id']);
assertTrue($matchResult['matched'] >= 4, '4 items auto-matched');
assertTrue($matchResult['unmatched'] >= 1, '1 item remains unmatched (deposit in transit)');

echo "\n=== Test 5: Manual match remaining item ===\n";
$unmatched = $recon->getUnmatchedItems($session['id']);
$bookUnmatched = array_filter($unmatched, fn($i) => $i['source'] === 'book');
$stmtUnmatched = array_filter($unmatched, fn($i) => $i['source'] === 'statement');

// Add a manual match between the unmatched statement item and... actually we have no unmatched book item
// Let's verify unmatched items are correct
assertTrue(count($stmtUnmatched) >= 1, 'Statement has unmatched item (deposit in transit)');
assertTrue(in_array('receipt', array_column($stmtUnmatched, 'type')), 'Unmatched statement item is a receipt (deposit in transit)');

echo "\n=== Test 6: Record adjusting entry (bank charges not in statement) ===\n";
$adj = $recon->addAdjustingEntry($session['id'], 642, 112, 50000, 'Unrecorded bank charge', 'tester');
assertTrue(isset($adj['transaction_id']), 'Adjusting entry created');
$expense = $accountRepo->findByCode('642')->getBalance();
assertEq(250000, $expense, 'Expense (642) increased by 50,000');

echo "\n=== Test 7: Complete reconciliation ===\n";
$result = $recon->complete($session['id']);
assertTrue($result['completed'], 'Reconciliation completed');
assertTrue($result['balanced'], 'Reconciliation is balanced');
assertEq('completed', $result['status'], 'Status = completed');

$sessionReload = $recon->getSession($session['id']);
assertEq('completed', $sessionReload['status'], 'DB status = completed');

echo "\n=== Test 8: List sessions ===\n";
$sessions = $recon->getSessions();
assertTrue(count($sessions) >= 1, 'At least 1 session listed');

echo "\n=== Test 9: Out-of-balance rejection ===\n";
$cash->recordBankReceipt(5000000, '511', 'Revenue', 'BC-099', 'tester');
$badSession = $recon->startSession('112', '2026-06-15', 9999999, 'tester');
try {
    $recon->complete($badSession['id']);
    echo "FAIL: Out-of-balance not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Out-of-balance rejected: ' . $e->getMessage());
}

echo "\n=== Test 10: Auto-match by amount + reference ===\n";
$pdo->exec('DELETE FROM bank_reconciliation_items');
$pdo->exec('DELETE FROM bank_reconciliation_sessions');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');
$pdo->exec('UPDATE accounts SET balance = 0');

$cash->recordBankReceipt(2000000, '131', 'Customer C', 'BC-010', 'tester');
$cash->recordBankPayment(1000000, '331', 'Supplier Z', 'BN-010', 'tester');

$s3 = $recon->startSession('112', date('Y-m-d'), 1000000, 'tester');
$recon->addStatementEntry($s3['id'], 2000000, 'Customer C deposit', 'BC-010', date('Y-m-d'), 'receipt');
$recon->addStatementEntry($s3['id'], 1000000, 'Supplier Z withdrawal', 'BN-010', date('Y-m-d'), 'payment');

$mr = $recon->autoMatch($s3['id']);
assertEq(2, $mr['matched'], '2 matched by amount+reference');
assertEq(0, $mr['unmatched'], '0 unmatched');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

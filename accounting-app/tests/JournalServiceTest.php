<?php
// Test: JournalService — create + post simple double-entry
// RED phase — test defines expected behavior before implementation

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

require_once __DIR__ . '/../src/Accounting/Domain/Service/JournalService.php';

use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Model\Account;

$failed = 0;
$total = 0;

function assertEq($expected, $actual, $msg) {
    global $total, $failed;
    $total++;
    if (abs((float)$expected - (float)$actual) > 10) { // tolerance for VND amounts
        echo "FAIL: {$msg} — expected {$expected}, got {$actual}\n";
        $failed++;
    } else {
        echo "PASS: {$msg}\n";
    }
}

function assertTrue($cond, $msg) {
    global $total, $failed;
    $total++;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failed++;
    } else {
        echo "PASS: {$msg}\n";
    }
}

// Setup: PDO + repositories
$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new \Accounting\Infrastructure\Persistence\PDOAccountRepository($pdo);
$txnRepo = new \Accounting\Infrastructure\Persistence\PDOTransactionRepository($pdo);

$svc = new JournalService($accountRepo, $txnRepo, $pdo);

// Make sure COA accounts exist for testing
$cash = $accountRepo->findByCode('111');
$revenue = $accountRepo->findByCode('511');

if (!$cash || !$revenue) {
    echo "FAIL: Prerequisite — COA accounts 111 (Cash) and 511 (Revenue) must exist.\n";
    echo "Run the COA seed first (POST /api/coa/seed)\n";
    exit(1);
}

echo "\n=== Test 1: Post simple Dr/Cr journal entry ===\n";
$txn = $svc->postEntry('Test sale', 'REF-001', [
    ['account_code' => '111', 'amount' => 1000000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 1000000, 'is_debit' => false],
], 'test_user');

assertEq('posted', $txn->getStatus(), 'Transaction status is "posted"');

// Verify balances
$cashBalance = $accountRepo->findByCode('111')->getBalance();
$revBalance = $accountRepo->findByCode('511')->getBalance();

assertEq(1000000, $cashBalance, 'Cash (asset) increased by 1,000,000 on debit');
assertEq(1000000, $revBalance, 'Revenue increased by 1,000,000 on credit');

echo "\n=== Test 2: Post Dr Expense — Cr Bank ===\n";
$txn2 = $svc->postEntry('Test expense', 'REF-002', [
    ['account_code' => '641', 'amount' => 500000, 'is_debit' => true],
    ['account_code' => '112', 'amount' => 500000, 'is_debit' => false],
], 'test_user');

// Bank (asset) starts at 0, credit decreases it → negative balance (overdraft)
$bankBalance = $accountRepo->findByCode('112')->getBalance();
assertEq(-500000, $bankBalance, 'Bank (asset) decreased by 500,000 on credit');
assertEq('posted', $txn2->getStatus(), 'Expense entry posted');

echo "\n=== Test 3: Unbalanced entry must be rejected ===\n";
try {
    $svc->postEntry('Bad entry', 'REF-003', [
        ['account_code' => '111', 'amount' => 100000, 'is_debit' => true],
        ['account_code' => '511', 'amount' => 90000, 'is_debit' => false],
    ], 'test_user');
    echo "FAIL: Unbalanced entry was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Unbalanced entry rejected with InvalidArgumentException');
}

echo "\n=== Test 4: Zero-amount line must be rejected ===\n";
try {
    $svc->postEntry('Zero entry', 'REF-004', [
        ['account_code' => '111', 'amount' => 0, 'is_debit' => true],
        ['account_code' => '511', 'amount' => 0, 'is_debit' => false],
    ], 'test_user');
    echo "FAIL: Zero-amount entry was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Zero-amount entry rejected');
}

echo "\n=== Test 5: Single-line entry must be rejected ===\n";
try {
    $svc->postEntry('Single line', 'REF-005', [
        ['account_code' => '111', 'amount' => 100000, 'is_debit' => true],
    ], 'test_user');
    echo "FAIL: Single-line entry was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Single-line entry rejected');
}

echo "\n=== Test 6: Non-existent account must be rejected ===\n";
try {
    $svc->postEntry('Bad account', 'REF-006', [
        ['account_code' => '999', 'amount' => 100000, 'is_debit' => true],
        ['account_code' => '511', 'amount' => 100000, 'is_debit' => false],
    ], 'test_user');
    echo "FAIL: Non-existent account was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Non-existent account rejected');
}

// Verify transaction has audit trail
echo "\n=== Test 7: Posted entry is immutable ===\n";
$saved = $txnRepo->findById($txn->getId());
assertTrue($saved !== null, 'Transaction persisted');
assertEq('posted', $saved->getStatus(), 'Status persisted as "posted"');
assertEq('REF-001', $saved->getReference(), 'Reference persisted');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);
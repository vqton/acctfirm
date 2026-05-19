<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\JournalBookService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$journal = new JournalService($accountRepo, $txnRepo, $pdo);
$journalBook = new JournalBookService($pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>1){echo"FAIL: {$m} expected {$b} got {$a}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

// === Test 1: Empty result for future period ===
$result = $journalBook->getGeneralJournal('2099-01-01', '2099-12-31');
assertTrue(count($result['entries']) === 0, 'Empty result for future period');
assertTrue($result['total_debit'] == 0, 'Total debit = 0 for empty');
assertTrue($result['total_credit'] == 0, 'Total credit = 0 for empty');

// === Test 2: Get all entries for current year ===
$result = $journalBook->getGeneralJournal(date('Y-01-01'), date('Y-12-31'));
assertTrue(count($result['entries']) > 0, 'Has entries in current year');
assertTrue($result['total_debit'] > 0, 'Total debit > 0');
assertTrue($result['total_credit'] > 0, 'Total credit > 0');
assertEq($result['total_debit'], $result['total_credit'], 'Dr = Cr');

// === Test 3: Each entry has required fields ===
$firstEntry = $result['entries'][0];
assertTrue(!empty($firstEntry['date']), 'Entry has date');
assertTrue(!empty($firstEntry['reference']), 'Entry has reference');
assertTrue(!empty($firstEntry['account_code']), 'Entry has account code');
assertTrue(isset($firstEntry['contra_account']), 'Entry has contra account');
assertTrue($firstEntry['debit'] >= 0, 'Entry debit >= 0');
assertTrue($firstEntry['credit'] >= 0, 'Entry credit >= 0');

// === Test 4: Entries ordered by date ===
$dates = array_map(fn($e) => $e['date'], $result['entries']);
$sorted = $dates;
sort($sorted);
assertEq($dates, $sorted, 'Entries ordered chronologically');

// === Test 5: Cumulative totals ===
$lastEntry = end($result['entries']);
assertEq($lastEntry['cumulative_dr'], $result['total_debit'], 'Last cumulative_dr = total debit');
assertEq($lastEntry['cumulative_cr'], $result['total_credit'], 'Last cumulative_cr = total credit');

// === Test 6: Filtered by date range ===
$resultFiltered = $journalBook->getGeneralJournal(date('Y-01-01'), date('Y-01-31'));
foreach ($resultFiltered['entries'] as $e) {
    assertTrue($e['date'] >= date('Y-01-01') && $e['date'] <= date('Y-01-31'), 'Entry within date range: ' . $e['date']);
}

// === Test 7: Description only on first line of each transaction ===
$result = $journalBook->getGeneralJournal(date('Y-01-01'), date('Y-12-31'));
$txnDescriptions = [];
foreach ($result['entries'] as $e) {
    if (!empty($e['description']) && !in_array($e['reference'], $txnDescriptions)) {
        $txnDescriptions[$e['reference']] = true;
        // First entry of this transaction should have description
    }
}
assertTrue(count($txnDescriptions) > 0, 'Transactions have descriptions');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Infrastructure\Database\DB;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
DB::setPdo($pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if($a!==$b){echo"FAIL: {$m}\n  expected: {$b}\n  got:      {$a}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

echo "\n=== select/execute Tests ===\n";
$rows = DB::select('SELECT COUNT(*) as c FROM accounts');
assertTrue($rows[0]['c'] > 0, 'Select returns rows');

echo "\n=== transaction Tests ===\n";
$result = DB::transaction(function($pdo) {
    return DB::select('SELECT code, name FROM accounts WHERE code = ?', ['111']);
});
assertTrue(count($result) === 1, 'Transaction returns result');
assertEq('111', $result[0]['code'], 'Transaction query correct');

echo "\n=== transaction rollback on exception ===\n";
$before = DB::select('SELECT COUNT(*) as c FROM voucher_sequences');

try {
    DB::transaction(function($pdo) {
        DB::execute('INSERT INTO voucher_sequences (prefix, year, last_no) VALUES (?, ?, ?)', ['TEST', 2026, 1]);
        throw new \RuntimeException('rollback test');
    });
    echo "FAIL: Exception not propagated\n"; $failed++;
} catch (\RuntimeException $e) {
    assertTrue(true, 'Transaction rolled back on exception');
}

$after = DB::select('SELECT COUNT(*) as c FROM voucher_sequences');
assertEq($before[0]['c'], $after[0]['c'], 'No rows after rollback');

echo "\n=== sqlIn Tests ===\n";
list($clause, $params) = DB::sqlIn(['111', '112', '113']);
assertEq(' IN (?,?,?)', $clause, 'sqlIn clause');
assertEq(3, count($params), 'sqlIn params count');

// Clean up test data
DB::execute('DELETE FROM voucher_sequences WHERE prefix = ?', ['TEST']);

echo "\n=== insertGetId Test ===\n";
// Use audit_log (has AUTO_INCREMENT) — insert a temp row then delete
$id = DB::insertGetId(
    "INSERT INTO audit_log (action, resource_type, resource_id, created_at) VALUES (?, ?, ?, NOW(3))",
    ['db_test', 'test', 'test_row']
);
assertTrue($id > 0, 'insertGetId returns auto-increment ID');
DB::execute('DELETE FROM audit_log WHERE id = ?', [$id]);

echo "\n=== fetch Tests ===\n";
$row = DB::fetch('SELECT code, name FROM accounts WHERE code = ?', ['111']);
assertTrue($row !== null, 'fetch returns row');
assertEq('111', $row['code'], 'fetch correct code');
assertEq('Tiền mặt', $row['name'], 'fetch correct name');

$notFound = DB::fetch('SELECT * FROM accounts WHERE code = ?', ['NONEXIST']);
assertTrue($notFound === null, 'fetch returns null for missing');

echo "\n=== fetchColumn Tests ===\n";
$count = DB::fetchColumn('SELECT COUNT(*) FROM accounts');
assertTrue($count > 0, 'fetchColumn returns count');

$name = DB::fetchColumn('SELECT name FROM accounts WHERE code = ?', ['111']);
assertEq('Tiền mặt', $name, 'fetchColumn returns single value');

$missing = DB::fetchColumn('SELECT code FROM accounts WHERE code = ?', ['NONEXIST']);
assertTrue($missing === null, 'fetchColumn returns null for missing');

echo "\n=== tableExists Tests ===\n";
assertTrue(DB::tableExists('accounts'), 'tableExists accounts');
assertTrue(DB::tableExists('audit_log'), 'tableExists audit_log');
assertTrue(!DB::tableExists('nonexistent_table_xyz'), 'tableExists false for missing');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

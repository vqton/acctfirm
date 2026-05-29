<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\AccountService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$repo = new PDOAccountRepository($pdo);
$svc = new AccountService($repo);

$_SERVER['PHP_AUTH_USER'] = 'test';

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if((string)$a!==(string)$b){echo"FAIL: {$m} expected [{$a}] got [{$b}]\n";$failed++;}
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertFalse($c, $m) { global $total, $failed;
    $total++; if($c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertThrows(callable $fn, string $msg): void {
    global $total, $failed;
    $total++;
    try { $fn(); echo "FAIL: {$msg} — no exception thrown\n"; $failed++; }
    catch (\Throwable $e) { echo "PASS: {$msg} ({$e->getMessage()})\n"; }
}

// Cleanup any leftover test accounts
foreach (['SVC001','SVC002','SVC003','SVC004','SVC005','SVC006','SVC007',
    'MRG01','MRG02','MRG03','MRG04','SVC0051'] as $code) {
    try { $repo->delete($repo->findByCode($code)?->getId()); } catch (\Throwable $e) {}
}

echo "\n=== Test 1: AccountService::create ===\n";
$a = $svc->create('SVC001', 'Service Test 1', 'asset', null, 'D', '1');
assertTrue($a !== null, 'Account created');
assertEq('SVC001', $a->getCode(), 'Code matches');
assertEq('asset', $a->getType(), 'Type matches');
assertTrue($a->isStatus(), 'Status = active by default');

assertThrows(fn() => $svc->create('SVC001', 'Duplicate', 'asset'), 'Duplicate code throws');

assertThrows(fn() => $svc->create('SVC002', 'Bad parent', 'asset', 'NONEXIST'), 'Invalid parent throws');

assertThrows(fn() => $svc->create('SVC003', 'Bad type', 'invalid_type'), 'Invalid type throws');

assertThrows(fn() => $svc->create('SVC004', 'Bad balance', 'asset', null, 'X'), 'Invalid normal_balance throws');

echo "\n=== Test 2: AccountService::update ===\n";
$updated = $svc->update($a->getId(), ['name' => 'Service Test 1 Updated', 'description' => 'Updated desc']);
assertEq('Service Test 1 Updated', $updated->getName(), 'Name updated');
assertEq('Updated desc', $updated->getDescription(), 'Description updated');

assertThrows(fn() => $svc->update('nonexistent', ['name' => 'X']), 'Update nonexistent throws');

$b = $svc->create('SVC002', 'Second account', 'liability', null, 'C', '3');
assertThrows(fn() => $svc->update($a->getId(), ['code' => 'SVC002']), 'Update to duplicate code throws');

echo "\n=== Test 3: AccountService::delete ===\n";
$svc->delete($b->getId());
assertTrue($repo->findByCode('SVC002') === null, 'Account deleted');

assertThrows(fn() => $svc->delete('nonexistent'), 'Delete nonexistent throws');

$sys = $svc->create('SVC003', 'System test', 'equity', null, 'C', '4');
$sys->setIsSystem(true);
$repo->save($sys);
assertThrows(fn() => $svc->delete($sys->getId()), 'Delete system account throws');

$c = $svc->create('SVC004', 'Balance test', 'asset', null, 'D', '1');
$c->setBalance(100000);
$repo->save($c);
assertThrows(fn() => $svc->delete($c->getId()), 'Delete account with balance throws');

// Has children check
$parent = $svc->create('SVC005', 'Parent delete', 'asset', null, 'D', '1');
$child = new \Accounting\Domain\Model\Account(uniqid('coa_'), 'SVC0051', 'Child', 'asset', $parent->getId(), 'D', '1');
$repo->save($child);
assertThrows(fn() => $svc->delete($parent->getId()), 'Delete account with children throws');

echo "\n=== Test 4: AccountService::activate ===\n";
$d = $svc->create('SVC006', 'Activate test', 'asset', null, 'D', '1');
$d->setStatus(false);
$repo->save($d);

// Should fail — no FS mapping
assertThrows(fn() => $svc->activate($d->getId()), 'Activate without FS mapping throws (Q1)');

$d->setFsMappingCode('BC01_110');
$d->setFsMappingType('balance_sheet');
$repo->save($d);
$activated = $svc->activate($d->getId());
assertTrue($activated->isStatus(), 'Account activated after FS mapping set');

assertThrows(fn() => $svc->activate($d->getId()), 'Activate already active throws');

echo "\n=== Test 5: AccountService::deactivate ===\n";
$deactivated = $svc->deactivate($activated->getId());
assertFalse($deactivated->isStatus(), 'Account deactivated');
$svc->activate($deactivated->getId()); // reactive for cleanup

assertThrows(fn() => $svc->deactivate($sys->getId()), 'Deactivate system account throws');

echo "\n=== Test 6: AccountService::lock/unlock ===\n";
$e = $svc->create('SVC007', 'Lock test', 'asset', null, 'D', '1');
$locked = $svc->lock($e->getId(), 'admin', 'Test lock');
assertTrue($locked->isLocked(), 'Account locked');
assertEq('admin', $locked->getLockedBy(), 'Locked by admin');

assertThrows(fn() => $svc->lock($e->getId(), 'admin', 'Double lock'), 'Double lock throws');

$e->unlock();
$repo->save($e);

// Lock with balance — without CFO override
$e->setBalance(500000);
$repo->save($e);
assertThrows(fn() => $svc->lock($e->getId(), 'admin', 'Lock with balance'), 'Lock with balance without override throws (Q3)');

// Lock with balance — WITH CFO override
$locked2 = $svc->lock($e->getId(), 'cfo', 'CFO override lock', true);
assertTrue($locked2->isLocked(), 'Lock with balance + CFO override succeeds');

$unlocked = $svc->unlock($e->getId());
assertFalse($unlocked->isLocked(), 'Account unlocked');

assertThrows(fn() => $svc->unlock($e->getId()), 'Unlock already unlocked throws');

echo "\n=== Test 7: AccountService::getTree ===\n";
$tree = $svc->getTree();
assertTrue(count($tree) > 0, 'Tree has roots');
// Verify at least one root has children
$hasChildren = false;
foreach ($tree as $root) {
    if (!empty($root['children'])) { $hasChildren = true; break; }
}
assertTrue($hasChildren, 'Tree has parent-child relationships');

echo "\n=== Test 8: AccountService::search/count ===\n";
$results = $svc->search('Tiền');
assertTrue(count($results) > 0, 'Search Tiền returns results');

$cnt = $svc->count();
assertTrue($cnt > 0, 'Count positive');

echo "\n=== Test 9: AccountService::getControlAccounts ===\n";
$controls = $svc->getControlAccounts();
assertTrue(count($controls) > 0, 'Control accounts exist');
assertTrue($controls[0]->isControl(), 'First result is control');

// Cleanup all test accounts
foreach (['SVC001','SVC003','SVC004','SVC005','SVC0051','SVC006','SVC007'] as $code) {
    try { $repo->delete($repo->findByCode($code)->getId()); } catch (\Throwable $e) {}
}

echo "\n=== Test 10: mergeAccounts validation ===\n";
$m1 = $svc->create('MRG01', 'Merge source 1', 'asset', null, 'D', '1');
$m2 = $svc->create('MRG02', 'Merge source 2', 'asset', null, 'D', '1');
$m3 = $svc->create('MRG03', 'Merge target', 'asset', null, 'D', '1');
$m4 = $svc->create('MRG04', 'Equity merge target', 'equity', null, 'C', '4');

assertThrows(fn() => $svc->mergeAccounts(['MRG01'], 'MRG03', 'admin', 'test'), 'Merge needs 2+ sources');
assertThrows(fn() => $svc->mergeAccounts(['NONEXIST', 'MRG02'], 'MRG03', 'admin', 'test'), 'Merge nonexistent source throws');
assertThrows(fn() => $svc->mergeAccounts(['MRG01', 'MRG04'], 'MRG03', 'admin', 'test'), 'Merge diff type sources throws');

// Cross-type merge without override
assertThrows(fn() => $svc->mergeAccounts(['MRG01', 'MRG02'], 'MRG04', 'admin', 'test'), 'Cross-type merge without CFO throws');

echo "\n=== Test 11: splitAccount validation ===\n";
assertThrows(fn() => $svc->splitAccount('NONEXIST', [['code' => 'MRG03', 'amount' => 100]], 'admin', 'test'), 'Split nonexistent throws');
assertThrows(fn() => $svc->splitAccount('MRG01', [['code' => 'MRG03', 'amount' => 100]], 'admin', 'test'), 'Split with wrong sum throws (balance=0, target=100)');

echo "\n=== Test 12: createBranchCOA ===\n";
try {
    $result = $svc->createBranchCOA(999, 'test');
    assertTrue($result['copied'] >= 100, 'Branch COA copies at least 100 accounts');
    assertTrue($result['skipped'] > 0, 'Some accounts skipped (equity/IC/911)');
} catch (\Throwable $e) {
    assertTrue(false, 'Branch COA threw: ' . $e->getMessage());
}

// Cleanup test accounts
foreach (['MRG01','MRG02','MRG03','MRG04'] as $code) {
    try { $repo->delete($repo->findByCode($code)->getId()); } catch (\Throwable $e) {}
}

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

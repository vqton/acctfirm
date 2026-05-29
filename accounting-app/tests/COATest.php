<?php
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Model\Account;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$repo = new PDOAccountRepository($pdo);

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

echo "\n=== Test 1: Full Appendix II COA seeded ===\n";
$all = $repo->findAll();
$allCodes = array_map(fn($a) => $a->getCode(), $all);

assertTrue(count($all) >= 140, 'At least 140 accounts (full Appendix II)');

assertTrue(in_array('111', $allCodes), '111 (Cash) exists');
assertTrue(in_array('151', $allCodes), '151 (Goods in transit) exists');
assertTrue(in_array('152', $allCodes), '152 (Raw materials) exists');
assertTrue(in_array('157', $allCodes), '157 (Consignment) exists');
assertTrue(in_array('229', $allCodes), '229 (Impairment provision) exists');
assertTrue(in_array('331', $allCodes), '331 (AP) exists');
assertTrue(in_array('632', $allCodes), '632 (COGS) exists');

assertTrue(in_array('332', $allCodes), '332 (Dividends payable) exists');
assertTrue(in_array('412', $allCodes), '412 (Asset revaluation) exists');
assertTrue(in_array('413', $allCodes), '413 (FX difference) exists');
assertTrue(in_array('414', $allCodes), '414 (Development fund) exists');
assertTrue(in_array('171', $allCodes), '171 (Gov bond repurchase) exists');
assertTrue(in_array('344', $allCodes), '344 (Deposits received) exists');
assertTrue(in_array('347', $allCodes), '347 (Deferred tax liability) exists');
assertTrue(in_array('353', $allCodes), '353 (Bonus/welfare fund) exists');
assertTrue(in_array('357', $allCodes), '357 (Price stabilization) exists');
assertTrue(in_array('8211', $allCodes), '8211 (Current CIT) exists');
assertTrue(in_array('82112', $allCodes), '82112 (Global minimum tax) exists');

assertEq('Tiền gửi không kỳ hạn', $repo->findByCode('112')->getName(), '112 renamed correctly');
assertEq('Sản phẩm', $repo->findByCode('155')->getName(), '155 renamed correctly');
assertEq('Chi phí chờ phân bổ', $repo->findByCode('242')->getName(), '242 renamed correctly');
assertEq('Cổ phiếu mua lại của chính mình', $repo->findByCode('419')->getName(), '419 renamed correctly');

echo "\n=== Test 2: Account CRUD ===\n";
$a = new Account(uniqid('coa_'), '999', 'Test account', 'liability', null, 'C', '9');
$repo->save($a);
$found = $repo->findByCode('999');
assertTrue($found !== null, 'Created account found by code');
assertEq('Test account', $found->getName(), 'Name matches');

$found->setName('Updated test');
$repo->save($found);
$updated = $repo->findByCode('999');
assertEq('Updated test', $updated->getName(), 'Name updated');

$repo->delete($found->getId());
$deleted = $repo->findByCode('999');
assertTrue($deleted === null, 'Deleted account not found');

echo "\n=== Test 3: Account hierarchy ===\n";
$parent = $repo->findByCode('128');
$child = $repo->findByCode('1281');
assertTrue($parent !== null, 'Parent account 128 exists');
assertTrue($child !== null, 'Child account 1281 exists');
assertEq($parent->getId(), $child->getParentId(), '1281 parent_id matches 128');

$parent3331 = $repo->findByCode('3331');
$sub = $repo->findByCode('33311');
assertTrue($parent3331 !== null, 'Parent account 3331 exists');
assertTrue($sub !== null, 'Level 3 account 33311 exists');
assertEq($parent3331->getId(), $sub->getParentId(), '33311 parent_id matches 3331');

echo "\n=== Test 4: Account types and normal balances ===\n";
$cash = $repo->findByCode('111');
assertEq('D', $cash->getNormalBalance(), 'Cash (111) is debit-normal');
$ap = $repo->findByCode('331');
assertEq('C', $ap->getNormalBalance(), 'AP (331) is credit-normal');
$revenue = $repo->findByCode('511');
assertEq('C', $revenue->getNormalBalance(), 'Revenue (511) is credit-normal');
$contraAsset = $repo->findByCode('229');
assertEq('C', $contraAsset->getNormalBalance(), '229 is credit-normal (contra-asset)');
$equity = $repo->findByCode('411');
assertEq('C', $equity->getNormalBalance(), 'Equity (411) is credit-normal');
$expense = $repo->findByCode('632');
assertEq('D', $expense->getNormalBalance(), 'Expense (632) is debit-normal');

echo "\n=== Test 5: Control accounts marked correctly ===\n";
assertTrue($repo->findByCode('128')->isControl(), '128 is control (has sub-accounts)');
assertTrue($repo->findByCode('133')->isControl(), '133 is control (has sub-accounts)');
assertTrue($repo->findByCode('333')->isControl(), '333 is control (has sub-accounts)');
assertTrue($repo->findByCode('411')->isControl(), '411 is control (has sub-accounts)');
assertFalse($repo->findByCode('111')->isControl(), '111 is NOT control (no sub-accounts)');
assertFalse($repo->findByCode('632')->isControl(), '632 is NOT control (no sub-accounts)');

echo "\n=== Test 6: FS mapping ===\n";
$a = new Account(uniqid('coa_'), 'FS001', 'FS test account', 'asset', null, 'D', '1',
    null, 'BC01_110', 'balance_sheet');
$repo->save($a);
$found = $repo->findByCode('FS001');
assertEq('BC01_110', $found->getFsMappingCode(), 'FS mapping code set');
assertEq('balance_sheet', $found->getFsMappingType(), 'FS mapping type set');

$mapped = $repo->findByFsMapping('BC01_110');
assertTrue(count($mapped) >= 1, 'findByFsMapping returns at least 1');
assertTrue(in_array('FS001', array_map(fn($x) => $x->getCode(), $mapped)), 'findByFsMapping includes FS001');

$repo->delete($found->getId());

echo "\n=== Test 7: Lock/unlock ===\n";
$a = new Account(uniqid('coa_'), 'LOCK001', 'Lock test', 'asset', null, 'D', '1');
$a->lock('admin', 'Kiểm tra nghiệp vụ khóa tài khoản');
$repo->save($a);
$found = $repo->findByCode('LOCK001');
assertTrue($found !== null, 'Lock test account exists');
assertTrue($found->isLocked(), 'Account is locked');
assertEq('admin', $found->getLockedBy(), 'Locked by admin');
assertEq('Kiểm tra nghiệp vụ khóa tài khoản', $found->getLockedReason(), 'Lock reason set');
assertTrue($found->getLockedAt() !== null, 'Lock timestamp set');

$found->unlock();
$repo->save($found);
$unlocked = $repo->findByCode('LOCK001');
assertFalse($unlocked->isLocked(), 'Account unlocked');
assertTrue($unlocked->getLockedBy() === null, 'LockedBy cleared');
assertTrue($unlocked->getLockedReason() === null, 'LockReason cleared');
assertTrue($unlocked->getLockedAt() === null, 'LockedAt cleared');

$repo->delete($found->getId());

echo "\n=== Test 8: is_system protection ===\n";
$a = new Account(uniqid('coa_'), 'SYS001', 'System account', 'equity', null, 'C', '4');
$a->setIsSystem(true);
$repo->save($a);
$found = $repo->findByCode('SYS001');
assertTrue($found->isSystem(), 'System account flagged');

$repo->delete($found->getId());
$deleted = $repo->findByCode('SYS001');
assertTrue($deleted === null, 'System account still deletable via repo (controller blocks)');
// Controller-level check tested separately — repo does not enforce

echo "\n=== Test 9: New repository query methods ===\n";
$controlAccounts = $repo->findControlAccounts();
assertTrue(count($controlAccounts) > 0, 'findControlAccounts returns results');
assertTrue($controlAccounts[0]->isControl(), 'Control account has is_control=true');

$count = $repo->count();
assertTrue($count > 0, 'count returns positive number');

$searchResults = $repo->search('Tiền');
assertTrue(count($searchResults) > 0, 'search("Tiền") returns results');

$assetAccounts = $repo->findByType('asset');
assertTrue(count($assetAccounts) > 0, 'findByType(asset) returns results');
assertEq('asset', $assetAccounts[0]->getType(), 'Asset account type matches');

echo "\n=== Test 10: Account model toArray includes new fields ===\n";
$a = new Account(uniqid('coa_'), 'TOARR01', 'ToArray test', 'liability', null, 'C', '3',
    'Test description', 'BC01_330', 'balance_sheet', 'OLD003', 'supplier');
$a->setIsSystem(true);
$a->lock('admin', 'Test lock');
$arr = $a->toArray();
assertTrue(isset($arr['fs_mapping_code']), 'toArray includes fs_mapping_code');
assertEq('BC01_330', $arr['fs_mapping_code'], 'fs_mapping_code value correct');
assertEq('balance_sheet', $arr['fs_mapping_type'], 'fs_mapping_type value correct');
assertTrue($arr['is_locked'], 'is_locked in toArray');
assertTrue($arr['is_system'], 'is_system in toArray');
assertEq('OLD003', $arr['alternative_code'], 'alternative_code in toArray');
assertEq('supplier', $arr['detail_by'], 'detail_by in toArray');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

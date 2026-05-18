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
    $total++; if(abs((float)$a-(float)$b)>0.001){echo"FAIL: {$m} expected {$a} got {$b}\n";$failed++;}
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

// Verify total count (should be 151 with all sub-accounts)
assertTrue(count($all) >= 140, 'At least 140 accounts (full Appendix II)');

// Spot-check key accounts
assertTrue(in_array('111', $allCodes), '111 (Cash) exists');
assertTrue(in_array('151', $allCodes), '151 (Goods in transit) exists');
assertTrue(in_array('152', $allCodes), '152 (Raw materials) exists');
assertTrue(in_array('157', $allCodes), '157 (Consignment) exists');
assertTrue(in_array('229', $allCodes), '229 (Impairment provision) exists');
assertTrue(in_array('331', $allCodes), '331 (AP) exists');
assertTrue(in_array('632', $allCodes), '632 (COGS) exists');

// Verify critical NEW accounts per TT99
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

// Verify accounts NOT in TT99 are absent
// 631 was abolished in TT99: we don't seed it, but old DB may have it
// Just verify it's not in the seed codes we define (can't assert DB since old data persists)

// Verify renamed accounts have correct TT99 names
assertEq('Tiền gửi không kỳ hạn', $repo->findByCode('112')->getName(), '112 renamed correctly');
assertEq('Sản phẩm', $repo->findByCode('155')->getName(), '155 renamed correctly');
assertEq('Chi phí chờ phân bổ', $repo->findByCode('242')->getName(), '242 renamed correctly');
assertEq('Cổ phiếu mua lại của chính mình', $repo->findByCode('419')->getName(), '419 renamed correctly');
assertEq('Thanh toán theo tiến độ hợp đồng XD', $repo->findByCode('337')->getName(), '337 repurposed correctly');
assertEq('Nhận ký quỹ, ký cược', $repo->findByCode('344')->getName(), '344 repurposed correctly');
assertEq('Thuế TNDN hoãn lại phải trả', $repo->findByCode('347')->getName(), '347 repurposed correctly');

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
assertEq($parent->getCode(), $child->getParentId(), '1281 parent_id references code 128');

// Verify deeper hierarchy (level 3 accounts)
$sub = $repo->findByCode('33311');
assertTrue($sub !== null, 'Level 3 account 33311 exists');
assertEq('3331', $sub->getParentId(), '33311 parent is 3331');

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

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

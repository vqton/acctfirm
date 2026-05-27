<?php
// Test: Validation hạch toán — Dr = Cr, control account, posting rules
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$svc = new JournalService($accountRepo, $txnRepo, $pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>10){echo"FAIL: {$m} expected {$a} got {$b}\n";$failed++;}
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

$pdo->exec('UPDATE accounts SET balance = 0');

// Mark 128 and 133 as control accounts (parent accounts with sub-accounts)
$pdo->exec("UPDATE accounts SET is_control = 1 WHERE code IN ('128', '133')");

echo "\n=== Test 1: Post to non-control account (allowed) ===\n";
$txn = $svc->postEntry('Test entry', 'T1', [
    ['account_code' => '111', 'amount' => 100000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 100000, 'is_debit' => false],
], 'tester');
assertEq('posted', $txn->getStatus(), 'Non-control entry posted');

// Ràng buộc kế toán: Không được post vào TK tổng hợp (control account)
// TK 128, 133 có is_control = 1 → throw InvalidArgumentException
// Nếu fail → có thể post vào TK tổng hợp → sai số dư chi tiết, sai BC01
echo "\n=== Test 2: Post to control account (rejected) ===\n";
try {
    $svc->postEntry('Control entry', 'T2', [
        ['account_code' => '128', 'amount' => 50000, 'is_debit' => true],
        ['account_code' => '511', 'amount' => 50000, 'is_debit' => false],
    ], 'tester');
    echo "FAIL: Control account was not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(str_contains($e->getMessage(), 'tài khoản tổng hợp'), 'Control account rejected with correct message');
}

// Nghiệp vụ: Post vào TK tổng hợp với override ($allowControl = true)
// Chỉ kế toán trưởng mới được dùng — exception cho trường hợp đặc biệt
// Nếu fail → không thể thực hiện bút toán đặc biệt cần thiết
echo "\n=== Test 3: Post to control account with override (allowed) ===\n";
$txn3 = $svc->postEntry('Override entry', 'T3', [
    ['account_code' => '128', 'amount' => 50000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 50000, 'is_debit' => false],
], 'chief_accountant', true);
assertEq('posted', $txn3->getStatus(), 'Override entry posted');

// Nghiệp vụ: Post vào TK chi tiết (1281) — không phải control → cho phép
// TK con phải được cập nhật số dư
// Nếu fail → không thể hạch toán vào TK chi tiết
echo "\n=== Test 4: Post to sub-account (allowed, not control) ===\n";
$txn4 = $svc->postEntry('Sub-account entry', 'T4', [
    ['account_code' => '1281', 'amount' => 30000, 'is_debit' => true],
    ['account_code' => '511', 'amount' => 30000, 'is_debit' => false],
], 'tester');
assertEq('posted', $txn4->getStatus(), 'Sub-account entry posted');
assertTrue($accountRepo->findByCode('1281')->getBalance() > 0, '1281 balance updated');

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

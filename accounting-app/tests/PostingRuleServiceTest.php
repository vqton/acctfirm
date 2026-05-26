<?php
// Test: Posting rules validation

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\PostingRuleService;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$svc = new PostingRuleService($pdo);

$failed = 0; $total = 0;
function assertEq($a, $b, $msg) { global $total, $failed; $total++; if ($a === $b) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected " . var_export($b, true) . ", got " . var_export($a, true) . "\n"; $failed++; } }
function assertTrue($cond, $msg) { global $total, $failed; $total++; if ($cond) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected true\n"; $failed++; } }
function results() { global $total, $failed; echo "\n=== Results: {$total} tests, {$failed} failed ===\n"; exit($failed > 0 ? 1 : 0); }

// Ensure seed data exists
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM posting_rules WHERE is_active = 1")->fetchColumn();
assertTrue($cnt >= 50, "At least 50 active rules seeded (got {$cnt})");
echo "\n";

// === Test 1: Known valid pair (Dr 156/Cr 331 — purchase) ===
// Nghiệp vụ: Mua hàng — Nợ 156 (Hàng hóa) / Có 331 (Phải trả NCC)
// Posting rule = block (không cho phép hạch toán trực tiếp vào 156/331 trong module purchase)
// Nếu fail → posting rules sai, có thể hạch toán sai nghiệp vụ mua hàng
$r = $svc->validatePair('156', '331', 'purchase');
assertEq($r['severity'], 'block', 'Dr 156/Cr 331 purchase = block');
assertTrue($r['rule_id'] > 0, 'Rule ID returned');
echo "\n";

// === Test 2: Known valid pair (Dr 632/Cr 156 — COGS) ===
// Nghiệp vụ: Xuất kho bán hàng — Nợ 632 (Giá vốn) / Có 156 (Hàng hóa)
// Rule chặn cặp này trong module inventory → phải qua InventoryService
// Nếu fail → có thể ghi nhận giá vốn sai
$r = $svc->validatePair('632', '156', 'inventory');
assertEq($r['severity'], 'block', 'Dr 632/Cr 156 inventory = block');
echo "\n";

// === Test 3: Unknown pair (passes) ===
// Trường hợp: cặp tài khoản không có rule → pass (cho phép hạch toán)
// Nếu fail → hệ thống chặn các nghiệp vụ mới chưa có rule
$r = $svc->validatePair('111', '511', 'custom');
assertEq($r['severity'], 'pass', 'Unknown pair = pass');
echo "\n";

// === Test 4: Entry validation — single Dr, single Cr ===
// Kiểm tra: validateEntry() trả về block nếu có ít nhất 1 cặp Dr/Cr bị chặn
// Nếu fail → validation tổng thể entry không hoạt động
$lines = [
    ['account_code' => '156', 'is_debit' => true, 'amount' => 100000],
    ['account_code' => '331', 'is_debit' => false, 'amount' => 100000],
];
$results = $svc->validateEntry($lines, 'purchase');
assertTrue($svc->hasBlock($results), 'Purchase entry has block');
echo "\n";

// === Test 5: Entry with unknown pair — no block ===
// Trường hợp: cặp tài khoản mới (1388/111) không có rule → không block
// Nếu fail → hệ thống chặn các nghiệp vụ hợp lệ không có rule
$lines = [
    ['account_code' => '1388', 'is_debit' => true, 'amount' => 100000],
    ['account_code' => '111', 'is_debit' => false, 'amount' => 100000],
];
$results = $svc->validateEntry($lines);
assertTrue(!$svc->hasBlock($results), 'Unknown entry has no block');
echo "\n";

// === Test 6: Module-scoped rule — Dr 641/Cr 214 in fa module ===
// Nghiệp vụ: Chi phí bán hàng (641) / Hao mòn TSCĐ (214)
// Rule chỉ áp dụng khi module = 'fa' (fixed asset); không module → pass
// Nếu fail → module-scoped rules không hoạt động đúng
$r = $svc->validatePair('641', '214', 'fa');
assertEq($r['severity'], 'block', 'Dr 641/Cr 214 fa module = block');
// without module it should pass
$r2 = $svc->validatePair('641', '214');
assertEq($r2['severity'], 'pass', 'Dr 641/Cr 214 no module = pass (module-scoped)');
echo "\n";

// === Test 7: Block rule has max_amount (to be implemented) ===
// Just verify the schema stores it
$hasMax = (int)$pdo->query("SELECT COUNT(*) FROM posting_rules WHERE max_amount IS NOT NULL")->fetchColumn();
assertTrue($hasMax >= 0, 'max_amount column exists (may be null)');
echo "\n";

results();

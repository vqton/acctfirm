<?php
// Test: Mẫu 02-VT (Phiếu xuất kho) theo TT99 — GoodsIssueService
// Nghiệp vụ: Tạo nháp → Ghi sổ (giảm tồn kho) → Hủy
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\GoodsIssueService;
use Accounting\Domain\Service\VoucherService;
use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Service\PostingRuleService;
use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Infrastructure\Persistence\PDOItemRepository;
use Accounting\Infrastructure\Persistence\PDOWarehouseRepository;
use Accounting\Infrastructure\Database\AuditLogger;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$itemRepo = new PDOItemRepository($pdo);
$warehouseRepo = new PDOWarehouseRepository($pdo);
$auditLogger = new AuditLogger($pdo);
$postingRuleService = new PostingRuleService($pdo);
$voucherService = new VoucherService($pdo);
$journalService = new JournalService($accountRepo, $txnRepo, $pdo, $auditLogger, $postingRuleService, $voucherService);
$inventoryService = new InventoryService($accountRepo, $txnRepo, $itemRepo, $warehouseRepo, $journalService, $pdo);
$goodsIssueService = new GoodsIssueService($pdo, $inventoryService, $voucherService, $itemRepo, $auditLogger);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if($a !== $b){echo"FAIL: {$m} — expected ".var_export($b,true).", got ".var_export($a,true)."\n";$failed++;}
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}
function assertFalse($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"PASS: {$m}\n";}else{echo"FAIL: {$m}\n";$failed++;}
}
function assertNear($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)<10){echo"PASS: {$m}\n";}else{echo"FAIL: {$m} — diff: ".abs((float)$a-(float)$b)."\n";$failed++;}
}
function results() { global $total, $failed;
    echo "\n=== Results: {$total} tests, {$failed} failed ===\n"; exit($failed>0?1:0);
}

// Đảm bảo bảng tồn tại (migration 122 phải chạy trước)
try { $pdo->query("SELECT 1 FROM inventory_issues LIMIT 1"); }
catch (\PDOException $e) {
    echo "SKIP: inventory_issues table not found. Run migration 122 first.\n";
    exit(0);
}

echo "=== GoodsIssueService Test ===\n";

// Setup: tạo item test với tồn kho
$itemId = 'test_item_pxk_' . uniqid();
$pdo->prepare("INSERT IGNORE INTO items (id, code, name, item_type, stock_qty, unit, created_at)
    VALUES (?, ?, 'Hàng hóa test PXK', 'goods', 100, 'cái', NOW())")
    ->execute([$itemId, 'PXK_TEST_' . substr($itemId, -4)]);

// Đảm bảo tồn kho đủ
$pdo->prepare("UPDATE items SET stock_qty = 100 WHERE id = ?")->execute([$itemId]);

// Cần có inventory_cost_layer để tính giá
$layerId = 'layer_' . uniqid();
$pdo->prepare("INSERT IGNORE INTO inventory_cost_layers (id, item_id, qty, unit_cost, created_at)
    VALUES (?, ?, 100, 50000, NOW())")->execute([$layerId, $itemId]);

echo "\n--- Test 1: createDraft — tạo PXK nháp ---\n";
$lines = [['item_id' => $itemId, 'requested_qty' => 10, 'actual_qty' => 10]];
$draft = $goodsIssueService->createDraft([
    'issue_date' => '2026-06-08',
    'receiver_name' => 'Nguyễn Văn A',
    'receiver_department' => 'Phòng SX',
    'issue_reason' => 'Xuất NVL sản xuất',
    'issue_type' => 'sale',
    'lines' => $lines,
    'created_by' => 'tester'
]);

assertTrue(isset($draft['id']), 'createDraft returns id');
assertEq('draft', $draft['status'], 'Status = draft');
assertTrue(str_starts_with($draft['issue_number'], 'PXK'), 'Issue number starts with PXK');
assertEq('Nguyễn Văn A', $draft['receiver_name'], 'Receiver name saved');
assertEq('Phòng SX', $draft['receiver_department'], 'Department saved');
assertEq('Xuất NVL sản xuất', $draft['issue_reason'], 'Reason saved');
assertEq(1, count($draft['lines']), 'Has 1 line');
$draftLine = $draft['lines'][0];
assertEq($itemId, $draftLine['item_id'], 'Line item_id matches');
assertNear(10.0, $draftLine['actual_qty'], 'Line actual_qty = 10');

$issueId = $draft['id'];

echo "\n--- Test 2: getIssue — lấy chi tiết PXK ---\n";
$detail = $goodsIssueService->getIssue($issueId);
assertEq($draft['issue_number'], $detail['issue_number'], 'getIssue returns correct number');
assertEq('draft', $detail['status'], 'Status still draft');
assertEq(1, count($detail['lines']), 'Has 1 line from getIssue');

echo "\n--- Test 3: postIssue — ghi sổ PXK (tạo bút toán + giảm tồn) ---\n";
$posted = $goodsIssueService->postIssue($issueId, 'tester');
assertEq('posted', $posted['status'], 'Status = posted after post');
assertTrue($posted['total_amount'] > 0, 'Total amount > 0 after costing');
// Giá 50000 x 10 = 500000
assertTrue(abs($posted['total_amount'] - 500000) < 10, 'Total amount = 500,000 (50000 x 10)');

// Kiểm tra tồn kho giảm
$stmt = $pdo->prepare("SELECT stock_qty FROM items WHERE id = ?");
$stmt->execute([$itemId]);
$remainingQty = (float)$stmt->fetchColumn();
assertTrue(abs($remainingQty - 90) < 0.01, 'Stock reduced from 100 to 90');

// Kiểm tra line có transaction_id
$postedLine = $posted['lines'][0];
assertTrue(!empty($postedLine['transaction_id']), 'Line has transaction_id after posting');
assertTrue($postedLine['unit_price'] > 0, 'Line has unit_price after posting');

echo "\n--- Test 4: listIssues — danh sách PXK ---\n";
$list = $goodsIssueService->listIssues();
assertTrue(count($list) >= 1, 'listIssues returns at least 1 record');
$found = false;
foreach ($list as $r) { if ($r['id'] === $issueId) $found = true; }
assertTrue($found, 'Our issue is in the list');

$draftList = $goodsIssueService->listIssues('draft');
$draftFound = false;
foreach ($draftList as $r) { if ($r['id'] === $issueId) $draftFound = true; }
assertFalse($draftFound, 'Posted issue not in draft list');

$postedList = $goodsIssueService->listIssues('posted');
$postedFound = false;
foreach ($postedList as $r) { if ($r['id'] === $issueId) $postedFound = true; }
assertTrue($postedFound, 'Posted issue in posted list');

echo "\n--- Test 5: postIssue — không thể post PXK đã post ---\n";
try {
    $goodsIssueService->postIssue($issueId, 'tester');
    assertTrue(false, 'Should throw on re-post');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Throws on re-post: ' . $e->getMessage());
}

echo "\n--- Test 6: cancelIssue — hủy PXK (phải là draft) ---\n";
// Tạo PXK mới để hủy
$draft2 = $goodsIssueService->createDraft([
    'issue_date' => '2026-06-08',
    'receiver_name' => 'Trần Thị B',
    'issue_reason' => 'Kiểm tra hủy',
    'lines' => [['item_id' => $itemId, 'requested_qty' => 5, 'actual_qty' => 5]],
    'created_by' => 'tester'
]);
$cancelled = $goodsIssueService->cancelIssue($draft2['id'], 'tester');
assertEq('cancelled', $cancelled['status'], 'Status = cancelled after cancel');

echo "\n--- Test 7: không thể hủy PXK đã posted ---\n";
try {
    $goodsIssueService->cancelIssue($issueId, 'tester');
    assertTrue(false, 'Should throw on cancel posted');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Throws on cancel posted: ' . $e->getMessage());
}

echo "\n--- Test 8: không thể hủy PXK đã cancelled ---\n";
try {
    $goodsIssueService->cancelIssue($draft2['id'], 'tester');
    assertTrue(false, 'Should throw on cancel cancelled');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Throws on cancel cancelled: ' . $e->getMessage());
}

echo "\n--- Test 9: getIssue — lỗi nếu không tồn tại ---\n";
try {
    $goodsIssueService->getIssue('nonexistent_id');
    assertTrue(false, 'Should throw on nonexistent');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Throws on nonexistent: ' . $e->getMessage());
}

results();

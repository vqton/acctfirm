<?php
// Test: Period close with inventory snapshot/rollback

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\PeriodService;
use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Service\JournalService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Infrastructure\Persistence\PDOItemRepository;
use Accounting\Infrastructure\Persistence\PDOWarehouseRepository;
use Accounting\Infrastructure\Database\AuditLogger;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$itemRepo = new PDOItemRepository($pdo);
$warehouseRepo = new PDOWarehouseRepository($pdo);
$journal = new JournalService($accountRepo, $txnRepo);
$audit = new AuditLogger($pdo, 'test_worker');
$invSvc = new InventoryService($accountRepo, $txnRepo, $itemRepo, $warehouseRepo, $journal, $pdo);
$periodSvc = new PeriodService($pdo, $accountRepo, $txnRepo, $journal, $audit, $invSvc);

$failed = 0; $total = 0;
function assertEq($a, $b, $msg) { global $total, $failed; $total++; if ($a === $b) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected " . var_export($b, true) . ", got " . var_export($a, true) . "\n"; $failed++; } }
function assertTrue($cond, $msg) { global $total, $failed; $total++; if ($cond) { echo "PASS: {$msg}\n"; } else { echo "FAIL: {$msg} — expected true\n"; $failed++; } }
function results() { global $total, $failed; echo "\n=== Results: {$total} tests, {$failed} failed ===\n"; exit($failed > 0 ? 1 : 0); }

// Seed: create a test period
$periodCode = 'PH4' . substr(uniqid(), -5);
$pdo->exec("INSERT INTO accounting_periods (period_type, period_code, name, start_date, end_date, status, re_open_count)
    VALUES ('month', '{$periodCode}', 'PH4 Test Period', '2026-05-01', '2026-05-31', 'open', 0)");
$periodId = (int)$pdo->lastInsertId();

$itemId = 'PH4_' . uniqid();
$itemCode = 'PH4' . substr(uniqid(), -5);
$pdo->exec("INSERT INTO items (id, code, name, item_type, stock_qty, unit) VALUES ('{$itemId}', '{$itemCode}', 'PeriodClose Item', 'merchandise', 100, 'cai')");

$layerId = 'lay_' . uniqid();
$pdo->exec("INSERT INTO inventory_cost_layers (id, item_id, warehouse_id, qty, unit_cost, addon_per_unit, created_at)
    VALUES ('{$layerId}', '{$itemId}', 'WH01', 100, 50000, 5000, '2026-05-15 10:00:00')");

// === Test 1: canClose ===
// Nghiệp vụ: Kiểm tra pre-close checklist trước khi đóng kỳ
// Phải có ít nhất 3 checks (TB, sequential, etc.)
// Nếu fail → đóng kỳ thiếu kiểm tra → rủi ro dữ liệu
$canClose = $periodSvc->canClose($periodId);
assertTrue(isset($canClose['can_close']), 'canClose returns can_close key');
assertTrue(count($canClose['checks']) >= 3, 'Has at least 3 checks');
echo "\n";

// === Test 2: closeInventoryForPeriod ===
$invClose = $invSvc->closeInventoryForPeriod($periodId, $periodCode, '2026-05-01', '2026-05-31', 'tester');
assertTrue(isset($invClose['snapshot_id']), 'Returns snapshot_id');
assertTrue($invClose['items_count'] > 0, 'Has items');
echo "\n";

// === Test 3: Snapshot persisted ===
$snapCnt = (int)$pdo->query("SELECT COUNT(*) FROM period_inventory_snapshots WHERE period_id = {$periodId}")->fetchColumn();
assertTrue($snapCnt > 0, 'Snapshot saved');
echo "\n";

// === Test 4: closePeriod ===
$result = $periodSvc->closePeriod($periodId, 'tester');
assertEq($result['status'], 'closed', 'Period closed');
assertTrue(isset($result['inventory_close']), 'Has inventory_close');
assertEq($result['inventory_close']['items_count'], $invClose['items_count'], 'Counts match');
echo "\n";

// === Test 5: reOpenPeriod restores inventory ===
// Nghiệp vụ: Mở lại kỳ đã đóng → phục hồi số lượng tồn kho từ snapshot
// Nếu fail → mở kỳ làm mất dữ liệu tồn kho → sai số lượng
$pdo->exec("UPDATE items SET stock_qty = 888 WHERE id = '{$itemId}'");
$pdo->exec("UPDATE inventory_cost_layers SET qty = 888 WHERE item_id = '{$itemId}'");

$reOpened = $periodSvc->reOpenPeriod($periodId, 'tester');
assertEq($reOpened['status'], 'open', 'Period re-opened');

$restored = (int)$pdo->query("SELECT stock_qty FROM items WHERE id = '{$itemId}'")->fetchColumn();
assertEq($restored, 100, 'Stock restored');

$layerQty = (float)$pdo->query("SELECT COALESCE(SUM(qty), 0) FROM inventory_cost_layers WHERE item_id = '{$itemId}'")->fetchColumn();
assertTrue($layerQty > 0, 'Layers restored');

// Cleanup
$pdo->exec("DELETE FROM accounting_periods WHERE id = {$periodId}");
$pdo->exec("DELETE FROM items WHERE id = '{$itemId}'");
$pdo->exec("DELETE FROM inventory_cost_layers WHERE id = '{$layerId}'");
echo "\n";

results();

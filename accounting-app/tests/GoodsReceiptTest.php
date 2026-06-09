<?php
// Test: Mẫu 01-VT (Phiếu nhập kho) theo TT99 — GoodsReceiptService
// Nghiệp vụ: Tạo nháp → Ghi sổ (tăng tồn kho) → Hủy
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\GoodsReceiptService;
use Accounting\Domain\Service\VoucherService;
use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Service\PostingRuleService;
use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Infrastructure\Persistence\PDOItemRepository;
use Accounting\Infrastructure\Persistence\PDOWarehouseRepository;
use Accounting\Infrastructure\Persistence\PDOGoodsReceiptRepository;
use Accounting\Infrastructure\Persistence\PDOGoodsReceiptLineRepository;
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
$grRepo = new PDOGoodsReceiptRepository($pdo);
$grLineRepo = new PDOGoodsReceiptLineRepository($pdo);
$goodsReceiptService = new GoodsReceiptService(
    $pdo, $voucherService, $journalService,
    $grRepo, $grLineRepo, $itemRepo, $warehouseRepo,
    $auditLogger, $inventoryService
);

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
    $total++; if(abs((float)$a-(float)$b)<10){echo"PASS: {$m}\n";}else{echo"FAIL: {$m} — got ".((float)$a).", expected ".((float)$b)."\n";$failed++;}
}
function results() { global $total, $failed;
    echo "\n=== Results: {$total} tests, {$failed} failed ===\n"; exit($failed>0?1:0);
}

echo "=== GoodsReceiptService Test (Mẫu 01-VT) — v2 với cột 1+2 + new fields ===\n";

// Kiểm tra bảng goods_receipts tồn tại
try { $pdo->query("SELECT 1 FROM goods_receipts LIMIT 1"); }
catch (\PDOException $e) {
    echo "SKIP: goods_receipts table not found. Run migration 127 first.\n";
    exit(0);
}

// Setup: tạo item test
$itemId = 'test_item_pnk_' . uniqid();
$pdo->prepare("INSERT IGNORE INTO items (id, code, name, item_type, stock_qty, created_at)
    VALUES (?, ?, 'Hàng hóa test PNK', 'merchandise', 0, NOW())")
    ->execute([$itemId, 'PNK_TEST_' . substr($itemId, -4)]);

// Test data — with new fields (invoice_ref, deliverer_name, qty_in_document)
$supplierName = 'Công ty TNHH Test Supplier';
$delivererName = 'Nguyễn Văn Giao';
$invoiceRef = 'HD001234';
$invoiceDate = '2026-06-01';
$warehouseLocation = 'Kho A — Tầng 1';
$attachDoc = '01 HĐ GTGT số HD001234';
$lines = [
    [
        'item_id' => $itemId,
        'qty_received' => 10,
        'unit_price' => 50000,
        'item_name' => 'Hàng test',
        'uom' => 'cái',
        'qty_in_document' => 10,  // Cột 1: SL theo CT
    ],
];

echo "\n--- Test 1: createDraft — tạo PNK nháp với Cột 1 + Cột 2 + fields mới ---\n";
$draft = $goodsReceiptService->createDraft(
    null, $supplierName, '123 Đường Test',
    'purchase', null, date('Y-m-d'), 'Phòng Kinh doanh',
    'Ghi chú test', $lines, 'test_user',
    $invoiceRef, $invoiceDate, $delivererName, $warehouseLocation, $attachDoc
);
assertTrue(isset($draft['id']), 'PNK draft có id');
assertEq($draft['status'], 'draft', 'Status = draft');
assertEq($draft['supplier_name'], $supplierName, 'Supplier name chính xác');
assertTrue($draft['total_amount'] > 0, 'Total amount > 0');
assertTrue(!empty($draft['lines']), 'Có lines');
assertEq($draft['receipt_type'], 'purchase', 'Receipt type = purchase');
assertTrue(isset($draft['amount_in_words']), 'Amount in words not set');
assertTrue(strpos($draft['gr_number'], 'PNK') === 0, 'Số PNK bắt đầu bằng PNK');
echo "  PNK number: {$draft['gr_number']}, Total: " . ($draft['total_amount']) . "\n";

// Kiểm tra fields mới
assertEq($draft['invoice_ref'], $invoiceRef, 'invoice_ref chính xác');
assertEq($draft['invoice_date'], $invoiceDate, 'invoice_date chính xác');
assertEq($draft['deliverer_name'], $delivererName, 'deliverer_name chính xác');
assertEq($draft['warehouse_location'], $warehouseLocation, 'warehouse_location chính xác');
assertEq($draft['attach_doc'], $attachDoc, 'attach_doc chính xác');
assertTrue(isset($draft['lines'][0]['qty_in_document']), 'Cột 1 (qty_in_document) có trong line');
assertNear((float)$draft['lines'][0]['qty_in_document'], 10, 'Cột 1 = 10');
assertNear((float)$draft['lines'][0]['qty_received'], 10, 'Cột 2 = 10');

echo "\n--- Test 2: getReceipt — lấy chi tiết PNK ---\n";
$detail = $goodsReceiptService->getReceipt($draft['id']);
assertEq($detail['id'], $draft['id'], 'ID khớp');
assertEq(count($detail['lines']), 1, 'Có 1 dòng hàng');
assertEq($detail['invoice_ref'], $invoiceRef, 'getReceipt có invoice_ref');
assertEq($detail['deliverer_name'], $delivererName, 'getReceipt có deliverer_name');

echo "\n--- Test 3: getPrintData — dữ liệu in Mẫu 01-VT ---\n";
$printData = $goodsReceiptService->getPrintData($draft['id']);
assertTrue(isset($printData['debit_accounts']), 'Print data có debit_accounts');
assertTrue(isset($printData['credit_account']), 'Print data có credit_account');
assertTrue(count($printData['debit_accounts']) > 0, 'Có ít nhất 1 TK Nợ');
assertEq($printData['credit_account'], '331', 'Credit account = 331 (có supplier)');
assertEq($printData['invoice_ref'], $invoiceRef, 'Print data có invoice_ref');
assertEq($printData['deliverer_name'], $delivererName, 'Print data có deliverer_name');

echo "\n--- Test 4: postReceipt — ghi sổ PNK ---\n";
$posted = $goodsReceiptService->postReceipt($draft['id'], 'test_user');
assertEq($posted['status'], 'posted', 'Status = posted sau khi ghi sổ');
assertTrue(isset($posted['lines']), 'Posted có lines');

// Kiểm tra tồn kho tăng
$item = $itemRepo->findById($itemId);
assertNear($item->getStockQty(), 10, 'Stock qty = 10 sau khi nhập');

echo "\n--- Test 5: postReceipt — từ chối ghi sổ PNK đã ghi sổ ---\n";
$exceptionCaught = false;
try {
    $goodsReceiptService->postReceipt($draft['id'], 'test_user');
} catch (\InvalidArgumentException $e) {
    $exceptionCaught = true;
    assertTrue(strpos($e->getMessage(), 'nháp') !== false, 'Exception: chỉ được ghi sổ PNK nháp');
}
assertTrue($exceptionCaught, 'Không cho ghi sổ PNK đã ghi sổ');

echo "\n--- Test 6: cancelReceipt — tạo PNK mới rồi hủy ---\n";
$draft2 = $goodsReceiptService->createDraft(
    null, 'Another Co', null, 'purchase', null, date('Y-m-d'), null, null,
    [['item_id' => $itemId, 'qty_received' => 5, 'unit_price' => 30000, 'item_name' => 'Hàng 2', 'qty_in_document' => 5]],
    'test_user'
);
$cancelled = $goodsReceiptService->cancelReceipt($draft2['id'], 'test_user');
assertEq($cancelled['status'], 'cancelled', 'Status = cancelled');

echo "\n--- Test 7: cancelReceipt — từ chối hủy PNK đã ghi sổ ---\n";
$exceptionCaught = false;
try {
    $goodsReceiptService->cancelReceipt($draft['id'], 'test_user');
} catch (\InvalidArgumentException $e) {
    $exceptionCaught = true;
    assertTrue(strpos($e->getMessage(), 'nháp') !== false, 'Exception: chỉ được hủy PNK nháp');
}
assertTrue($exceptionCaught, 'Không cho hủy PNK đã ghi sổ');

echo "\n--- Test 8: listReceipts — danh sách PNK ---\n";
$list = $goodsReceiptService->listReceipts();
assertTrue(count($list) >= 2, 'Có ít nhất 2 PNK trong danh sách');
$listPosted = $goodsReceiptService->listReceipts('posted');
assertTrue(count($listPosted) >= 1, 'Có ít nhất 1 PNK posted');
$listCancelled = $goodsReceiptService->listReceipts('cancelled');
assertTrue(count($listCancelled) >= 1, 'Có ít nhất 1 PNK cancelled');

echo "\n--- Test 9: createDraft — từ chối tạo PNK không có lines ---\n";
$exceptionCaught = false;
try {
    $goodsReceiptService->createDraft(
        null, 'Test', null, 'purchase', null, date('Y-m-d'), null, null,
        [], 'test_user'
    );
} catch (\InvalidArgumentException $e) {
    $exceptionCaught = true;
    assertTrue(strpos($e->getMessage(), 'ít nhất một dòng') !== false, 'Exception: phải có ít nhất 1 dòng');
}
assertTrue($exceptionCaught, 'Từ chối PNK không có lines');

echo "\n--- Test 10: getReceipt — không tìm thấy PNK ---\n";
$exceptionCaught = false;
try {
    $goodsReceiptService->getReceipt('nonexistent_id');
} catch (\InvalidArgumentException $e) {
    $exceptionCaught = true;
}
assertTrue($exceptionCaught, 'Exception: không tìm thấy PNK');

echo "\n--- Test 11: postReceipt — không tìm thấy PNK ---\n";
$exceptionCaught = false;
try {
    $goodsReceiptService->postReceipt('nonexistent_id', 'test_user');
} catch (\InvalidArgumentException $e) {
    $exceptionCaught = true;
}
assertTrue($exceptionCaught, 'Exception: không tìm thấy PNK để ghi sổ');

echo "\n--- Test 12: createDraft — PNK với qty_in_document ≠ qty_received ---\n";
$draft3 = $goodsReceiptService->createDraft(
    null, 'Co C', null, 'purchase', null, date('Y-m-d'), null, null,
    [['item_id' => $itemId, 'qty_received' => 8, 'unit_price' => 10000, 'item_name' => 'Hàng lệch', 'qty_in_document' => 10]],
    'test_user'
);
assertNear((float)$draft3['lines'][0]['qty_in_document'], 10, 'Cột 1 (CT) = 10');
assertNear((float)$draft3['lines'][0]['qty_received'], 8, 'Cột 2 (thực nhập) = 8');
echo "  Cột 1 (10) ≠ Cột 2 (8) — chênh lệch\n";

echo "\n--- Test 13: getPrintData — PNK chênh lệch có qty_warning ---\n";
$print3 = $goodsReceiptService->getPrintData($draft3['id']);
assertTrue(!empty($print3['qty_warning']), 'Print data có qty_warning khi chênh lệch');
echo "  Cảnh báo: {$print3['qty_warning']}\n";

// Cleanup: xóa dữ liệu test
$pdo->prepare("DELETE FROM goods_receipt_lines WHERE gr_id IN (?, ?, ?)")->execute([$draft['id'], $draft2['id'], $draft3['id']]);
$pdo->prepare("DELETE FROM goods_receipts WHERE id IN (?, ?, ?)")->execute([$draft['id'], $draft2['id'], $draft3['id']]);
$pdo->prepare("DELETE FROM inventory_cost_layers WHERE item_id = ?")->execute([$itemId]);
$pdo->prepare("DELETE FROM items WHERE id = ?")->execute([$itemId]);
// Xóa transaction đã tạo
$pdo->prepare("DELETE FROM ledger_entries WHERE transaction_id IN (SELECT id FROM transactions WHERE reference LIKE ?)")->execute(['PNK%']);
$pdo->prepare("DELETE FROM transactions WHERE reference LIKE ?")->execute(['PNK%']);

results();

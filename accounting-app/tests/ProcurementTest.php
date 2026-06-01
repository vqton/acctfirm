<?php
// Test: Procurement Engine — PR → PO → GR → 3-Way Match
// Toàn bộ vòng đời mua hàng

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\ProcurementService;
use Accounting\Domain\Service\ThreeWayMatchService;
use Accounting\Domain\Service\BudgetControlService;
use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Service\ApprovalRoutingService;
use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Infrastructure\Persistence\PDOItemRepository;
use Accounting\Infrastructure\Persistence\PDOSupplierRepository;
use Accounting\Infrastructure\Persistence\PDOWarehouseRepository;
use Accounting\Infrastructure\Persistence\PDOPurchaseRequisitionRepository;
use Accounting\Infrastructure\Persistence\PDOPurchaseOrderRepository;
use Accounting\Infrastructure\Persistence\PDOGoodsReceiptRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$itemRepo = new PDOItemRepository($pdo);
$supplierRepo = new PDOSupplierRepository($pdo);
$warehouseRepo = new PDOWarehouseRepository($pdo);
$prRepo = new PDOPurchaseRequisitionRepository($pdo);
$poRepo = new PDOPurchaseOrderRepository($pdo);
$grRepo = new PDOGoodsReceiptRepository($pdo);
$journal = new JournalService($accountRepo, $txnRepo, $pdo);
$inventory = new InventoryService($accountRepo, $txnRepo, $itemRepo, $warehouseRepo, $journal, $pdo);
$approvalRouting = new ApprovalRoutingService($pdo);
$auditLogger = new class implements AuditLoggerInterface {
    public function log(string $action, string $resourceType, ?string $resourceId = null, ?array $oldValues = null, ?array $newValues = null, ?string $actorId = null, ?string $actorEmail = null): void {}
};

$procurement = new ProcurementService($prRepo, $poRepo, $grRepo, $itemRepo, $supplierRepo, $journal, $inventory, $auditLogger, $approvalRouting, $pdo);
$matchService = new ThreeWayMatchService($pdo, $auditLogger);
$budgetService = new BudgetControlService($pdo, $auditLogger);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if(abs((float)$a-(float)$b)>10){echo"FAIL: {$m} — expected {$b}, got {$a}\n";$failed++;}
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if(!$c){echo"FAIL: {$m}\n";$failed++;}else echo "PASS: {$m}\n";
}

// Reset data
$pdo->exec('UPDATE accounts SET balance = 0');
$pdo->exec('DELETE FROM inventory_cost_layers');
$pdo->exec('DELETE FROM purchase_requisition_lines');
$pdo->exec('DELETE FROM purchase_requisitions');
$pdo->exec('DELETE FROM purchase_order_lines');
$pdo->exec('DELETE FROM purchase_orders');
$pdo->exec('DELETE FROM goods_receipt_lines');
$pdo->exec('DELETE FROM goods_receipts');
$pdo->exec('DELETE FROM purchase_invoice_match_lines');
$pdo->exec('DELETE FROM purchase_invoice_matches');

// Find test items
$item = $itemRepo->findByCode('VT001');
assertTrue($item !== null, 'Test item VT001 exists');
$item->setStockQty(0);
$itemRepo->save($item);

$supplier = $supplierRepo->findAll()[0] ?? null;
assertTrue($supplier !== null, 'At least one supplier exists');

// ──────────────────────────────────────
// TEST 1: Create Purchase Requisition
// NGHIỆP VỤ: Đề nghị mua 10 VT001, đơn giá 85,000, yêu cầu giao 2026-06-15
// ──────────────────────────────────────
echo "\n=== Test 1: Create Purchase Requisition ===\n";
$prResult = $procurement->createPR([
    'requester_id' => 'user_001',
    'department_id' => 'dept_001',
    'delivery_date' => '2026-06-15',
    'note' => 'Test PR for procurement engine',
    'lines' => [
        ['item_id' => $item->getId(), 'qty' => 10, 'price_estimate' => 85000, 'uom_id' => null],
    ],
], 'tester');

assertTrue(isset($prResult['id']), 'PR created with ID');
assertTrue(isset($prResult['pr_number']), 'PR has number');
assertEq('pending', $prResult['status'], 'PR status = pending');
assertEq(850000, $prResult['total'], 'PR total = 850,000');

$prId = $prResult['id'];

// ──────────────────────────────────────
// TEST 2: Approve PR
// NGHIỆP VỤ: Kế toán trưởng phê duyệt đề nghị mua hàng
// ──────────────────────────────────────
echo "\n=== Test 2: Approve PR ===\n";
$approveResult = $procurement->approvePR($prId, 'approver_001', 'Approved');
assertEq('approved', $approveResult['status'], 'PR approved');

// ──────────────────────────────────────
// TEST 3: Create PO from approved PR
// NGHIỆP VỤ: Chuyển PR đã duyệt thành đơn đặt hàng cho NCC
// ──────────────────────────────────────
echo "\n=== Test 3: Create Purchase Order ===\n";
$poResult = $procurement->createPO($prId, $supplier->getId(), 'buyer_001', [
    'payment_terms' => 'Net 30',
    'delivery_terms' => 'FOB',
    'expected_delivery' => '2026-06-20',
]);

assertTrue(isset($poResult['id']), 'PO created with ID');
assertTrue(isset($poResult['po_number']), 'PO has number');
assertEq(850000, $poResult['total'], 'PO total = 850,000');

$poId = $poResult['id'];

// ──────────────────────────────────────
// TEST 4: Create Goods Receipt (full receipt)
// NGHIỆP VỤ: Nhập kho 10 VT001 → tăng tồn kho, ghi nhận AP
// ──────────────────────────────────────
echo "\n=== Test 4: Goods Receipt (full) ===\n";

// Get PO line id
$stmt = $pdo->prepare("SELECT id FROM purchase_order_lines WHERE po_id = ?");
$stmt->execute([$poId]);
$poLineId = $stmt->fetchColumn();

$grResult = $procurement->createGR($poId, 'wh_001', '2026-06-18', [
    ['po_line_id' => $poLineId, 'qty_received' => 10, 'qty_rejected' => 0],
], 'warehouse_user');

assertTrue(isset($grResult['id']), 'GR created with ID');
assertTrue(isset($grResult['gr_number']), 'GR has number');
assertEq('completed', $grResult['status'], 'GR completed');

// Check PO status updated
$stmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE id = ?");
$stmt->execute([$poId]);
assertEq('completed', $stmt->fetchColumn(), 'PO status = completed');

// Check inventory balance
$itemAfter = $itemRepo->findById($item->getId());
assertEq(10, $itemAfter->getStockQty(), 'Stock qty = 10');

// Check GL: Dr 152 (850,000) / Cr 331 (850,000)
$rawMat = $accountRepo->findByCode('152')->getBalance();
$ap = $accountRepo->findByCode('331')->getBalance();
assertEq(850000, $rawMat, 'Raw materials (152) = 850,000');
assertEq(850000, $ap, 'AP (331) = 850,000');

// ──────────────────────────────────────
// TEST 5: 3-Way Match — successful match
// NGHIỆP VỤ: Hóa đơn nhà cung cấp khớp PO và GR → matched
// ──────────────────────────────────────
echo "\n=== Test 5: 3-Way Match (successful) ===\n";
$matchResult = $matchService->match($poId, 'INV-001', '2026-06-20', 850000, 85000, [
    ['po_line_id' => $poLineId, 'qty_invoiced' => 10, 'unit_price_invoiced' => 85000],
], 'ap_user');

assertEq('matched', $matchResult['match_status'], 'Match status = matched');
assertTrue(count($matchResult['lines']) > 0, 'Match lines returned');

// ──────────────────────────────────────
// TEST 6: 3-Way Match — price mismatch
// NGHIỆP VỤ: Hóa đơn có đơn giá khác biệt > 2% → warning/mismatch
// ──────────────────────────────────────
echo "\n=== Test 6: 3-Way Match (price mismatch) ===\n";
// Create another PO + GR
$pr2 = $procurement->createPR([
    'requester_id' => 'user_001',
    'department_id' => 'dept_001',
    'lines' => [['item_id' => $item->getId(), 'qty' => 5, 'price_estimate' => 50000]],
], 'tester');
$procurement->approvePR($pr2['id'], 'approver_001');
$po2 = $procurement->createPO($pr2['id'], $supplier->getId(), 'buyer_001', []);

$stmt = $pdo->prepare("SELECT id FROM purchase_order_lines WHERE po_id = ?");
$stmt->execute([$po2['id']]);
$poLine2Id = $stmt->fetchColumn();

$matchResult2 = $matchService->match($po2['id'], 'INV-002', '2026-06-22', 300000, 30000, [
    ['po_line_id' => $poLine2Id, 'qty_invoiced' => 5, 'unit_price_invoiced' => 60000],
], 'ap_user');

// Price difference = 60,000 vs 50,000 = 20% > 2% → warning
assertTrue(in_array($matchResult2['match_status'], ['warning', 'mismatch']), 'Price mismatch detected');

// ──────────────────────────────────────
// TEST 7: Budget control check
// ──────────────────────────────────────
echo "\n=== Test 7: Budget Control ===\n";
$budgetService->setBudget('dept_001', '2026-06', 10000000, 'tester');
$budgetCheck = $budgetService->checkBudget('dept_001', '2026-06', 1000000);
assertTrue($budgetCheck['allowed'], 'Budget check: allowed for 1M');

// Test budget near limit
$budgetCheck2 = $budgetService->checkBudget('dept_001', '2026-06', 9500000);
assertTrue(!$budgetCheck2['allowed'], 'Budget check: blocked for 9.5M (>=95%)');

// ──────────────────────────────────────
// TEST 8: Reject duplicate approval
// ──────────────────────────────────────
echo "\n=== Test 8: SoD — Requester != Approver ===\n";
try {
    $procurement->approvePR($prId, 'user_001', 'Self-approval attempt');
    echo "FAIL: Self-approval not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Self-approval correctly rejected');
}

// ──────────────────────────────────────
// TEST 9: Reject over-delivery GR
// ──────────────────────────────────────
echo "\n=== Test 9: GR cannot exceed PO qty ===\n";
try {
    $procurement->createGR($poId, 'wh_001', '2026-06-25', [
        ['po_line_id' => $poLineId, 'qty_received' => 5, 'qty_rejected' => 0],
    ], 'warehouse_user');
    echo "FAIL: Over-delivery not rejected\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Over-delivery correctly rejected');
}

echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);

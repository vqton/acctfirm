<?php
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Service\VatRateService;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// Chạy migration 090
$migrate = require __DIR__ . '/../database/migrations/090_vat_groups.php';
$migrate($pdo);

$service = new VatRateService($pdo);

// === TEST 1: Rate mặc định (hiện tại đang giảm 8%, nên default = 8%) ===
$result = $service->determineRate(['item_id' => 'nonexistent']);
assertNear($result['rate'], 8.0, 'Default rate = 8% (NQ 204/2025 active)');
assertEq($result['group_code'], 'VAT10', 'Default group = VAT10');
assertTrue($result['is_reduction'], 'Reduction active for default vg_10');

// === TEST 2: Gán item vào vg_10 (được giảm 8%) ===
$service->assignItemToGroup('test_item_8', 'vg_10');
$result = $service->determineRate(['item_id' => 'test_item_8']);
assertNear($result['rate'], 8.0, 'vg_10 with reduction eligible = 8%');
assertTrue($result['is_reduction'], 'Reduction flag = true');

// === TEST 3: vg_10_no_reduce — không được giảm, vẫn 10% ===
$service->assignItemToGroup('test_item_10', 'vg_10_no_reduce');
$result = $service->determineRate(['item_id' => 'test_item_10']);
assertNear($result['rate'], 10.0, 'vg_10_no_reduce = 10%');
assertFalse($result['is_reduction'], 'No reduction flag');

// === TEST 4: vg_5 — cố định 5% ===
$service->assignItemToGroup('test_item_5', 'vg_5');
$result = $service->determineRate(['item_id' => 'test_item_5']);
assertNear($result['rate'], 5.0, 'vg_5 = 5%');

// === TEST 5: vg_0 — xuất khẩu 0% ===
$service->assignItemToGroup('test_item_0', 'vg_0');
$result = $service->determineRate(['item_id' => 'test_item_0']);
assertNear($result['rate'], 0.0, 'vg_0 = 0%');

// === TEST 6: vg_exempt — miễn thuế ===
$service->assignItemToGroup('test_item_exempt', 'vg_exempt');
$result = $service->determineRate(['item_id' => 'test_item_exempt']);
assertNear($result['rate'], 0.0, 'Exempt = 0%');
assertTrue($result['is_exempt'], 'Exempt flag = true');

// === TEST 7: Category mapping default ===
$result = $service->determineRate(['category_code' => 'UNKNOWN']);
assertNear($result['rate'], 8.0, 'Unknown category = 8% (default vg_10 with reduction)');

// === TEST 8: Lấy danh sách groups ===
$groups = $service->getGroups();
assertTrue(count($groups) >= 5, 'At least 5 groups seeded');
assertEq($groups[0]['code'], 'VAT10', 'First group = VAT10');

// === TEST 9: Export context override (is_export=true) ===
$service->assignItemToGroup('test_export', 'vg_10');
$result = $service->determineRate(['item_id' => 'test_export'], ['is_export' => true]);
assertNear($result['rate'], 0.0, 'Export override = 0%');

// === TEST 10: 8% eligibility helper ===
assertTrue($service->isEligibleForReduction('test_item_8'), 'Item in vg_10 is eligible for reduction');
assertFalse($service->isEligibleForReduction('test_item_10'),
    'Item in vg_10_no_reduce is NOT eligible for reduction');

results();

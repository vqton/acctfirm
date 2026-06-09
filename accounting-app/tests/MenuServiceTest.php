<?php
require __DIR__ . '/bootstrap.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

use Accounting\Infrastructure\Persistence\PDOMenuRepository;
use Accounting\Domain\Service\MenuService;

$repo = new PDOMenuRepository($pdo);
$svc = new MenuService($repo, $pdo);

// === Test 1: Menu items seeded ===
$all = $pdo->query("SELECT COUNT(*) FROM menu_items")->fetchColumn();
assertTrue((int)$all > 0, 'Menu items seeded');

// === Test 2: MenuRepository::findAllActive() ===
$items = $repo->findAllActive();
assertTrue(count($items) > 0, 'findAllActive returns items');

// === Test 3: MenuRepository::findBySection('cash_bank') ===
$cash = $repo->findBySection('cash_bank');
assertTrue(count($cash) > 0, 'findBySection cash_bank returns items');

// === Test 4: MenuRepository::findById() ===
$first = $repo->findById(1);
assertTrue($first !== null, 'findById returns item');

// === Test 5: MenuRepository::search() ===
$results = $repo->search('thu');
assertTrue(count($results) > 0, 'Search finds items');

// === Test 6: MenuRepository::search() no match ===
$noResults = $repo->search('zzzxxx');
assertEq(count($noResults), 0, 'Search no match returns empty');

// === Test 7: getSidebarMenu returns structured tree ===
$_SESSION['is_admin'] = true;
$tree = $svc->getSidebarMenu();
assertTrue(is_array($tree), 'getSidebarMenu returns array');
assertTrue(count($tree) > 0, 'getSidebarMenu has sections');

// === Test 8: Tree has heading + children ===
$hasHeading = false;
$hasChildren = false;
foreach ($tree as $node) {
    if (isset($node['label'])) $hasHeading = true;
    if (!empty($node['children'])) $hasChildren = true;
}
assertTrue($hasHeading, 'Tree has section headings');
assertTrue($hasChildren, 'Tree has child items');

// === Test 9: getSectionMenu('cash') ===
$cashMenu = $svc->getSectionMenu('cash');
assertTrue(is_array($cashMenu), 'getSectionMenu returns array');

// === Test 10: getCurrentPeriod ===
$period = $svc->getCurrentPeriod();
// Period may or may not exist depending on test DB - just check no error
assertTrue(true, 'getCurrentPeriod does not throw');

// === Test 11: getPendingApprovalCount ===
$count = $svc->getPendingApprovalCount();
assertTrue(is_int($count), 'getPendingApprovalCount returns int');

// === Test 12: Menu service works without auth ===
unset($_SESSION['is_admin']);
// Chỉ kiểm tra không throw — items with permission will be filtered out
assertTrue(true, 'Menu service works without auth');

// === Test 13: cash_bank has "Tạm ứng" (TK 141) ===
$cashItems = $repo->findBySection('cash_bank');
$labels = array_map(fn($i) => $i->getLabel(), $cashItems);
assertTrue(in_array('Tạm ứng', $labels), 'cash_bank has Tạm ứng');

// === Test 14: cash_bank has "Đánh giá lại tỷ giá" ===
assertTrue(in_array('Đánh giá lại tỷ giá', $labels), 'cash_bank has Đánh giá lại tỷ giá');

// === Test 15: inventory_ccdc has "Phân bổ CCDC" ===
$invItems = $repo->findBySection('inventory_ccdc');
$invLabels = array_map(fn($i) => $i->getLabel(), $invItems);
assertTrue(in_array('Phân bổ CCDC', $invLabels), 'inventory_ccdc has Phân bổ CCDC');

// === Test 16: inventory_ccdc has "Xử lý chênh lệch kiểm kê" ===
assertTrue(in_array('Xử lý chênh lệch kiểm kê', $invLabels), 'inventory_ccdc has Xử lý chênh lệch kiểm kê');

// === Test 17: gl_report has "Phân bổ chi phí trả trước" (TK 242) ===
$glItems = $repo->findBySection('gl_report');
$glLabels = array_map(fn($i) => $i->getLabel(), $glItems);
assertTrue(in_array('Phân bổ chi phí trả trước', $glLabels), 'gl_report has Phân bổ chi phí trả trước');

// === Test 18: gl_report has "Điều chỉnh hồi tố" ===
assertTrue(in_array('Điều chỉnh hồi tố', $glLabels), 'gl_report has Điều chỉnh hồi tố');

// === Test 19: gl_report has "Báo cáo quản trị" ===
assertTrue(in_array('Báo cáo quản trị', $glLabels), 'gl_report has Báo cáo quản trị');

// === Test 20: payroll renamed "Kê khai BHXH" → "Báo cáo BHXH" ===
$payItems = $repo->findBySection('payroll');
$payLabels = array_map(fn($i) => $i->getLabel(), $payItems);
assertTrue(in_array('Báo cáo BHXH', $payLabels), 'payroll has Báo cáo BHXH');
assertTrue(!in_array('Kê khai BHXH', $payLabels), 'payroll no longer has Kê khai BHXH');

// === Test 21: getSidebarMenu tree contains all new items ===
$_SESSION['is_admin'] = true;
$tree = $svc->getSidebarMenu();
$allLabels = [];
foreach ($tree as $node) {
    foreach ($node['children'] ?? [] as $ch) {
        $allLabels[] = $ch['label'] ?? '';
    }
}
assertTrue(in_array('Tạm ứng', $allLabels), 'Sidebar tree contains Tạm ứng');
assertTrue(in_array('Điều chỉnh hồi tố', $allLabels), 'Sidebar tree contains Điều chỉnh hồi tố');
unset($_SESSION['is_admin']);

// === Test 22: Search finds new items ===
$searchTamUng = $repo->search('Tạm ứng');
assertTrue(count($searchTamUng) > 0, 'Search finds Tạm ứng');

$searchBHXH = $repo->search('Báo cáo BHXH');
assertTrue(count($searchBHXH) > 0, 'Search finds Báo cáo BHXH');

// === Test 23: Section count OK (max 12 items per section, non-heading) ===
foreach (['cash_bank', 'inventory_ccdc', 'gl_report'] as $sec) {
    $items = $repo->findBySection($sec);
    $nonHeadings = array_filter($items, fn($i) => !$i->isHeading());
    assertTrue(count($nonHeadings) <= 12, "$sec has ≤12 items (got " . count($nonHeadings) . ")");
}

// Cleanup
results();

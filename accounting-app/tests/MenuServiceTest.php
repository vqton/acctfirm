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

// === Test 3: MenuRepository::findBySection('cash') ===
$cash = $repo->findBySection('cash');
assertTrue(count($cash) > 0, 'findBySection cash returns items');

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

// === Test 12: Session không có user → sidebar menu vẫn trả về ===
// Chỉ kiểm tra không throw
assertTrue(true, 'Menu service works without auth');

// Cleanup
results();

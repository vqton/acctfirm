<?php
// Test: ApprovalRoutingService — luồng phê duyệt bút toán
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Service\ApprovalRoutingService;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$svc = new ApprovalRoutingService($pdo);

// Nghiệp vụ: Tạm ứng < 10M → trưởng phòng kế toán duyệt
// Kiểm tra: ApprovalRoutingService::getRequiredRoles theo amount + module
// Nếu fail → luồng phê duyệt không đúng quyền → kiểm soát nội bộ yếu
// Giả định: approval_routing table có seed data
echo "\n";
$roles = $svc->getRequiredRoles(5000000, 'petty_cash');
assertTrue(in_array('chief_accountant', $roles), 'Petty cash 5M -> chief_accountant');

// Nghiệp vụ: Số tiền lớn > 100M → giám đốc duyệt
// Nếu fail → chi tiêu lớn không có phê duyệt đúng cấp
echo "\n";
$roles = $svc->getRequiredRoles(200000000);
assertTrue(in_array('director', $roles), 'Amount 200M -> director');

// Medium amount 50M -> chief_accountant
$roles = $svc->getRequiredRoles(50000000);
assertTrue(in_array('chief_accountant', $roles), 'Amount 50M -> chief_accountant');

// Nghiệp vụ: Giao dịch nội bộ (intercompany) → CFO duyệt bất kể số tiền
// Nếu fail → giao dịch nội bộ không có kiểm soát đặc biệt
echo "\n";
$roles = $svc->getRequiredRoles(1000000, 'intercompany');
assertTrue(in_array('cfo', $roles), 'Intercompany -> cfo');

// Biên: Mọi khoản đều phải có ít nhất 1 role duyệt (fallback)
echo "\n";
$roles = $svc->getRequiredRoles(1000);
assertTrue(count($roles) >= 1, 'Always returns at least 1 role');

$stmt = $pdo->query('SELECT COUNT(*) FROM approval_routing WHERE is_active = 1');
assertTrue((int)$stmt->fetchColumn() >= 4, 'At least 4 active routing rules');

results();

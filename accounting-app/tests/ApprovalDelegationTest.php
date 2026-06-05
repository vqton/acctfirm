<?php
//
// R-17: Delegation / Proxy approval tests
//
require __DIR__ . '/bootstrap.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

use Accounting\Domain\Service\ApprovalRoutingService;
$svc = new ApprovalRoutingService($pdo);

// Cleanup
$pdo->exec("DELETE FROM approval_delegations WHERE delegator_id LIKE 'test_%' OR delegate_id LIKE 'test_%'");

// ─── 1. Create delegation hợp lệ ──────────────────────────────────────────
$now = date('Y-m-d H:i:s');
$future = date('Y-m-d H:i:s', strtotime('+7 days'));
$id1 = $svc->createDelegation('test_ktt', 'test_accountant', 'chief_accountant', $now, $future, 'Đi công tác', 'test_ktt');
assertTrue(str_starts_with($id1, 'dele_'), 'ID có prefix dele_');

// ─── 2. Self-delegation bị từ chối ───────────────────────────────────────
try {
    $svc->createDelegation('test_ktt', 'test_ktt', 'chief_accountant', $now, $future, null, 'test_ktt');
    assertTrue(false, 'Phải throw khi self-delegate');
} catch (\InvalidArgumentException $e) {
    assertTrue(str_contains($e->getMessage(), 'tự ủy quyền'), 'Throw đúng lỗi self-delegate');
}

// ─── 3. end_date <= start_date bị từ chối ─────────────────────────────────
try {
    $svc->createDelegation('test_ktt', 'test_acc', 'chief_accountant', $future, $now, null, 'test_ktt');
    assertTrue(false, 'Phải throw khi end <= start');
} catch (\InvalidArgumentException $e) {
    assertTrue(str_contains($e->getMessage(), 'kết thúc'), 'Throw đúng lỗi end <= start');
}

// ─── 4. Find active delegations ───────────────────────────────────────────
$delegations = $svc->findActiveDelegationsFor('test_accountant', 'chief_accountant');
assertTrue(count($delegations) >= 1, 'Tìm thấy delegation active');
assertEq($delegations[0]['delegator_id'], 'test_ktt', 'Delegator = test_ktt');
assertEq($delegations[0]['role'], 'chief_accountant', 'Role = chief_accountant');

// ─── 5. Find delegation cho role khác → 0 ─────────────────────────────────
$none = $svc->findActiveDelegationsFor('test_accountant', 'director');
assertEq(count($none), 0, 'Không tìm thấy delegation cho role director');

// ─── 6. Find delegation cho user khác → 0 ─────────────────────────────────
$none2 = $svc->findActiveDelegationsFor('test_other_user', 'chief_accountant');
assertEq(count($none2), 0, 'Không tìm thấy delegation cho user khác');

// ─── 7. List delegations của delegator ────────────────────────────────────
$list = $svc->listDelegations('test_ktt');
assertTrue(count($list) >= 1, 'test_ktt có ít nhất 1 delegation');

// ─── 8. List delegations của delegate ─────────────────────────────────────
$list2 = $svc->listDelegations('test_accountant');
assertTrue(count($list2) >= 1, 'test_accountant có ít nhất 1 delegation (as delegate)');

// ─── 9. getEffectiveRolesFor ──────────────────────────────────────────────
$effective = $svc->getEffectiveRolesFor('test_accountant', ['accountant']);
assertTrue(in_array('chief_accountant', $effective), 'Delegate có chief_accountant trong effective roles');
assertTrue(in_array('accountant', $effective), 'Delegate vẫn có role gốc accountant');

// ─── 10. Revoke delegation ────────────────────────────────────────────────
$ok = $svc->revokeDelegation($id1, 'test_ktt');
assertTrue($ok, 'Revoke thành công');
$after = $svc->findActiveDelegationsFor('test_accountant', 'chief_accountant');
assertEq(count($after), 0, 'Sau revoke → không tìm thấy active delegation');

// ─── 11. Revoke lại delegation đã revoke → false ─────────────────────────
$ok2 = $svc->revokeDelegation($id1, 'test_ktt');
assertEq($ok2, false, 'Revoke lần 2 trả về false');

// ─── 12. Delegation với start_date tương lai → không active ───────────────
$pastEnd = date('Y-m-d H:i:s', strtotime('-1 day'));
$pastStart = date('Y-m-d H:i:s', strtotime('-7 days'));
$idPast = $svc->createDelegation('test_ktt2', 'test_acc2', 'chief_accountant', $pastStart, $pastEnd, 'expired', 'test_ktt2');
$expired = $svc->findActiveDelegationsFor('test_acc2', 'chief_accountant');
assertEq(count($expired), 0, 'Delegation hết hạn → không active');

// ─── 13. listDelegations activeOnly=true chỉ trả active ───────────────────
$idActive = $svc->createDelegation('test_ktt', 'test_acc3', 'chief_accountant', $now, $future, 'current', 'test_ktt');
$listActive = $svc->listDelegations('test_ktt', true);
$activeIds = array_column($listActive, 'id');
assertTrue(in_array($idActive, $activeIds), 'Active delegation có trong list');
assertTrue(!in_array($idPast, $activeIds), 'Expired delegation KHÔNG có trong active list');

// Cleanup
$pdo->exec("DELETE FROM approval_delegations WHERE delegator_id LIKE 'test_%' OR delegate_id LIKE 'test_%'");

results();

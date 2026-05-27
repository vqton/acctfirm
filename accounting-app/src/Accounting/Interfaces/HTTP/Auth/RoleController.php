<?php
namespace Accounting\Interfaces\HTTP\Auth;

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Quản lý Vai trò (Role-based Access Control)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD vai trò người dùng (admin, kế toán trưởng, kế toán viên, v.v.)
 *   - Gán quyền mặc định (view) cho module khi tạo vai trò mới
 *   - Quản lý quyền chi tiết theo module + action (view/create/edit/delete/post/print)
 *
 * API endpoints:
 *   GET    /api/roles          — Danh sách vai trò
 *   POST   /api/roles          — Tạo vai trò mới
 *   PUT    /api/roles/{id}     — Cập nhật vai trò
 *   DELETE /api/roles/{id}     — Xóa vai trò (trừ is_system)
 *   GET    /api/roles/{id}/permissions — Quyền của vai trò
 *   PUT    /api/roles/{id}/permissions — Cập nhật quyền
 *
 * Rủi ro:
 *   - Xóa vai trò hệ thống (is_system=1) sẽ phá vỡ phân quyền
 *   - Cấp quyền không phù hợp có thể dẫn đến lộ lọt dữ liệu tài chính
 *   - Role admin có toàn quyền, cần giới hạn người được gán
 *
 * Tích hợp:
 *   - UserController tham chiếu role_id từ bảng user_roles
 *   - Permission check qua Auth::requirePermission() ở mọi endpoint
 */
class RoleController
{
    private \PDO $pdo;
    private array $modules = ['cash','bank','gl','master_data','inventory','reconciliation','report','audit','system'];

    public function __construct(\PDO $pdo) { $this->pdo = $pdo; }

    public function list(): void
    {
        Auth::requirePermission('system', 'view');
        $rows = $this->pdo->query('SELECT * FROM roles ORDER BY is_system DESC, name')->fetchAll(\PDO::FETCH_ASSOC);
        JsonResponse::ok($rows);
    }

    // NGHIỆP VỤ: Tạo vai trò mới + tự động cấp quyền view cho tất cả module
    // Input: { id, name, description? }
    // Output: { id } — 201 Created
    // Permission: system, create
    // Process: INSERT INTO roles + INSERT IGNORE role_permissions (can_view=1) cho mỗi module
    // Rủi ro: FORBIDDEN — Không cho tạo role trùng id. is_system = 0 (mặc định)
    // Modules mặc định: cash, bank, gl, master_data, inventory, reconciliation, report, audit, system
    // Ràng buộc: Role id phải do client cung cấp (string, không tự sinh)
    public function create(): void
    {
        Auth::requirePermission('system', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['id'], $data['name'])) { JsonResponse::error('Vui lòng nhập mã và tên vai trò'); return; }
        $this->pdo->prepare('INSERT INTO roles (id, name, description) VALUES (?, ?, ?)')
            ->execute([$data['id'], $data['name'], $data['description'] ?? null]);

        // Grant default view permissions
        $ins = $this->pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, module, can_view) VALUES (?, ?, 1)');
        foreach ($this->modules as $m) $ins->execute([$data['id'], $m]);

        JsonResponse::ok(['id' => $data['id']], 201);
    }

    public function update(string $id): void
    {
        Auth::requirePermission('system', 'edit');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { JsonResponse::error('Không có dữ liệu đầu vào'); return; }
        $this->pdo->prepare('UPDATE roles SET name = ?, description = ? WHERE id = ?')
            ->execute([$data['name'] ?? '', $data['description'] ?? null, $id]);
        JsonResponse::ok(['message' => 'Đã cập nhật vai trò thành công']);
    }

    public function delete(string $id): void
    {
        Auth::requirePermission('system', 'delete');
        $chk = $this->pdo->prepare('SELECT is_system FROM roles WHERE id = ?');
        $chk->execute([$id]);
        $r = $chk->fetch();
        if (!$r) { JsonResponse::error('Không tìm thấy vai trò', 404); return; }
        if ($r['is_system']) { JsonResponse::error('Không thể xóa vai trò hệ thống mặc định'); return; }
        $this->pdo->prepare('DELETE FROM roles WHERE id = ?')->execute([$id]);
        JsonResponse::ok(['message' => 'Đã xóa vai trò thành công']);
    }

    public function getPermissions(string $id): void
    {
        Auth::requirePermission('system', 'view');
        $stmt = $this->pdo->prepare('SELECT * FROM role_permissions WHERE role_id = ?');
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $r) $result[$r['module']] = [
            'can_view' => (bool)$r['can_view'],
            'can_create' => (bool)$r['can_create'],
            'can_edit' => (bool)$r['can_edit'],
            'can_delete' => (bool)$r['can_delete'],
            'can_post' => (bool)$r['can_post'],
            'can_print' => (bool)$r['can_print'],
        ];
        JsonResponse::ok($result);
    }

    // NGHIỆP VỤ: Cập nhật quyền chi tiết cho vai trò — ghi đè toàn bộ
    // Input: { module: { can_view, can_create, can_edit, can_delete, can_post, can_print } }
    // Output: { message: 'Permissions updated' }
    // Permission: system, edit
    // Process: DELETE all old permissions → INSERT new permissions (full replace)
    // Rủi ro: Nếu input empty ({}), DELETE hết tất cả quyền → role không làm được gì
    // Audit trail: Không log riêng — cần AuditLogger ghi lại thay đổi quyền
    // Ràng buộc: Các module không được gửi lên sẽ bị xóa quyền (không merge, chỉ ghi đè)
    public function updatePermissions(string $id): void
    {
        Auth::requirePermission('system', 'edit');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { JsonResponse::error('Không có dữ liệu đầu vào'); return; }
        $this->pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$id]);
        $ins = $this->pdo->prepare(
            'INSERT INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_post, can_print) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($data as $module => $p) {
            $ins->execute([
                $id, $module,
                (int)($p['can_view'] ?? 0),
                (int)($p['can_create'] ?? 0),
                (int)($p['can_edit'] ?? 0),
                (int)($p['can_delete'] ?? 0),
                (int)($p['can_post'] ?? 0),
                (int)($p['can_print'] ?? 0),
            ]);
        }
        JsonResponse::ok(['message' => 'Đã cập nhật phân quyền thành công']);
    }
}

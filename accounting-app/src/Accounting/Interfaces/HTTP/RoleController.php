<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Infrastructure\Helpers;

class RoleController
{
    private \PDO $pdo;
    private array $modules = ['cash','bank','gl','master_data','inventory','reconciliation','report','audit','system'];

    public function __construct(\PDO $pdo) { $this->pdo = $pdo; }

    public function list(): void
    {
        Helpers::requirePermission('system', 'view');
        $rows = $this->pdo->query('SELECT * FROM roles ORDER BY is_system DESC, name')->fetchAll(\PDO::FETCH_ASSOC);
        Helpers::jsonOk($rows);
    }

    public function create(): void
    {
        Helpers::requirePermission('system', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['id'], $data['name'])) { Helpers::jsonError('id, name required'); return; }
        $this->pdo->prepare('INSERT INTO roles (id, name, description) VALUES (?, ?, ?)')
            ->execute([$data['id'], $data['name'], $data['description'] ?? null]);

        // Grant default view permissions
        $ins = $this->pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, module, can_view) VALUES (?, ?, 1)');
        foreach ($this->modules as $m) $ins->execute([$data['id'], $m]);

        Helpers::jsonOk(['id' => $data['id']], 201);
    }

    public function update(string $id): void
    {
        Helpers::requirePermission('system', 'edit');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { Helpers::jsonError('No data'); return; }
        $this->pdo->prepare('UPDATE roles SET name = ?, description = ? WHERE id = ?')
            ->execute([$data['name'] ?? '', $data['description'] ?? null, $id]);
        Helpers::jsonOk(['message' => 'Updated']);
    }

    public function delete(string $id): void
    {
        Helpers::requirePermission('system', 'delete');
        $chk = $this->pdo->prepare('SELECT is_system FROM roles WHERE id = ?');
        $chk->execute([$id]);
        $r = $chk->fetch();
        if (!$r) { Helpers::jsonError('Not found', 404); return; }
        if ($r['is_system']) { Helpers::jsonError('Cannot delete system role'); return; }
        $this->pdo->prepare('DELETE FROM roles WHERE id = ?')->execute([$id]);
        Helpers::jsonOk(['message' => 'Deleted']);
    }

    public function getPermissions(string $id): void
    {
        Helpers::requirePermission('system', 'view');
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
        Helpers::jsonOk($result);
    }

    public function updatePermissions(string $id): void
    {
        Helpers::requirePermission('system', 'edit');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { Helpers::jsonError('No data'); return; }
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
        Helpers::jsonOk(['message' => 'Permissions updated']);
    }
}

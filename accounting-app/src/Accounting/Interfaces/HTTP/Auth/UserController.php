<?php
namespace Accounting\Interfaces\HTTP\Auth;

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class UserController
{
    private \PDO $pdo;
    public function __construct(\PDO $pdo) { $this->pdo = $pdo; }

    public function list(): void
    {
        $stmt = $this->pdo->query('SELECT u.id, u.username, u.full_name, u.email, u.status, u.last_login, u.created_at,
            GROUP_CONCAT(r.name SEPARATOR ", ") as role_names
            FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id GROUP BY u.id ORDER BY u.created_at DESC');
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function listWithRoles(): void
    {
        Auth::requirePermission('system', 'view');
        $this->list();
    }

    public function create(): void
    {
        Auth::requirePermission('system', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['username'], $data['password'], $data['full_name'])) {
            JsonResponse::error('username, password, full_name required'); return;
        }
        $id = uniqid('u_');
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $email = $data['email'] ?? null;
        $this->pdo->prepare('INSERT INTO users (id, username, password_hash, full_name, email) VALUES (?, ?, ?, ?, ?)')
            ->execute([$id, $data['username'], $hash, $data['full_name'], $email]);

        if (!empty($data['role_ids'])) {
            $ins = $this->pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
            foreach ($data['role_ids'] as $rid) $ins->execute([$id, $rid]);
        }
        JsonResponse::ok(['id' => $id], 201);
    }

    public function update(string $id): void
    {
        Auth::requirePermission('system', 'edit');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { JsonResponse::error('No data'); return; }
        $user = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $user->execute([$id]);
        if (!$user->fetch()) { JsonResponse::error('Not found', 404); return; }

        if (isset($data['full_name']))
            $this->pdo->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$data['full_name'], $id]);
        if (isset($data['email']))
            $this->pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$data['email'], $id]);
        if (isset($data['status']))
            $this->pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$data['status'], $id]);
        if (!empty($data['password']))
            $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($data['password'], PASSWORD_DEFAULT), $id]);

        if (isset($data['role_ids'])) {
            $this->pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$id]);
            $ins = $this->pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
            foreach ($data['role_ids'] as $rid) $ins->execute([$id, $rid]);
        }
        JsonResponse::ok(['message' => 'Updated']);
    }

    public function delete(string $id): void
    {
        Auth::requirePermission('system', 'delete');
        if ($id === 'admin') { JsonResponse::error('Cannot delete admin'); return; }
        $this->pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute(['inactive', $id]);
        JsonResponse::ok(['message' => 'Deactivated']);
    }
}

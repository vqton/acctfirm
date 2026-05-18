<?php
namespace Accounting\Interfaces\HTTP\Auth;

use Accounting\Infrastructure\Helpers;
use Accounting\Infrastructure\JsonResponse;

class AuditLogController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function list(): void
    {
        $action = $_GET['action'] ?? '';
        $resource = $_GET['resource_type'] ?? '';
        $actor = $_GET['actor_id'] ?? '';
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));

        $where = [];
        $params = [];
        if ($action) { $where[] = 'action = ?'; $params[] = $action; }
        if ($resource) { $where[] = 'resource_type = ?'; $params[] = $resource; }
        if ($actor) { $where[] = 'actor_id = ?'; $params[] = $actor; }
        if ($from) { $where[] = 'created_at >= ?'; $params[] = $from; }
        if ($to) { $where[] = 'created_at <= ?'; $params[] = $to . ' 23:59:59'; }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $result = Helpers::paginate(
            $this->pdo,
            "SELECT COUNT(*) FROM audit_log {$whereClause}",
            "SELECT * FROM audit_log {$whereClause} ORDER BY id DESC",
            $params,
            $page
        );

        JsonResponse::ok($result);
    }

    public function get(string $id): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audit_log WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { JsonResponse::error('Not found', 404); return; }
        JsonResponse::ok($row);
    }
}

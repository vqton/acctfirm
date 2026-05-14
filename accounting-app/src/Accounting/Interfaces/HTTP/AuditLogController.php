<?php
namespace Accounting\Interfaces\HTTP;

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
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];
        if ($action) { $where[] = 'action = ?'; $params[] = $action; }
        if ($resource) { $where[] = 'resource_type = ?'; $params[] = $resource; }
        if ($actor) { $where[] = 'actor_id = ?'; $params[] = $actor; }
        if ($from) { $where[] = 'created_at >= ?'; $params[] = $from; }
        if ($to) { $where[] = 'created_at <= ?'; $params[] = $to . ' 23:59:59'; }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_log {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT * FROM audit_log {$whereClause} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    public function get(string $id): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audit_log WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        echo json_encode($row);
    }
}

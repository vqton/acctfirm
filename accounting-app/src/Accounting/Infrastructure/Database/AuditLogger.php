<?php
namespace Accounting\Infrastructure\Database;

class AuditLogger
{
    public static function log(
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $actorId = null,
        ?string $actorEmail = null
    ): void {
        $pdo = $GLOBALS['container']['pdo'] ?? null;
        if (!$pdo) return;

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $requestId = $GLOBALS['request_id'] ?? null;

        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (action, resource_type, resource_id, actor_id, actor_email, old_values, new_values, ip_address, request_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $action,
            $resourceType,
            $resourceId,
            $actorId,
            $actorEmail,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $ip,
            $requestId,
        ]);
    }
}

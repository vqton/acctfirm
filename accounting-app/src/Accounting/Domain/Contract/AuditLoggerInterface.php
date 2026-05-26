<?php
// Interface: hệ thống ghi nhật ký kiểm toán
namespace Accounting\Domain\Contract;

interface AuditLoggerInterface
{
    public function log(
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $actorId = null,
        ?string $actorEmail = null
    ): void;
}

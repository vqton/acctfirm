<?php

declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\PurchaseApproval;

interface PurchaseApprovalRepositoryInterface
{
    public function findById(string $id): ?PurchaseApproval;

    public function findByDoc(string $docType, string $docId): array;

    public function findByApprover(string $approverId): array;

    public function findByStatus(string $status): array;

    public function findAll(): array;

    public function save(PurchaseApproval $approval): void;

    public function delete(string $id): void;
}

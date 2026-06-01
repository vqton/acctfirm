<?php

declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\PurchaseRequisition;

interface PurchaseRequisitionRepositoryInterface
{
    public function findById(string $id): ?PurchaseRequisition;

    public function findOneByPrNumber(string $prNumber): ?PurchaseRequisition;

    public function findByStatus(string $status): array;

    public function findAll(): array;

    public function save(PurchaseRequisition $requisition): void;

    public function delete(string $id): void;
}

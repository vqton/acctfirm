<?php

declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\PurchaseBudget;

interface PurchaseBudgetRepositoryInterface
{
    public function findById(string $id): ?PurchaseBudget;

    public function findByDepartment(string $departmentId): array;

    public function findOneByDeptPeriod(string $departmentId, string $period): ?PurchaseBudget;

    public function findAll(): array;

    public function save(PurchaseBudget $budget): void;

    public function delete(string $id): void;
}

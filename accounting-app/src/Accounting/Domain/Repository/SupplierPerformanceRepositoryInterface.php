<?php

declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\SupplierPerformance;

interface SupplierPerformanceRepositoryInterface
{
    public function findById(string $id): ?SupplierPerformance;

    public function findBySupplier(string $supplierId): array;

    public function findAll(): array;

    public function save(SupplierPerformance $performance): void;
}

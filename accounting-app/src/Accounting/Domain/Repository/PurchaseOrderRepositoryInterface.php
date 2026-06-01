<?php

declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\PurchaseOrder;

interface PurchaseOrderRepositoryInterface
{
    public function findById(string $id): ?PurchaseOrder;

    public function findOneByPoNumber(string $poNumber): ?PurchaseOrder;

    public function findBySupplier(string $supplierId): array;

    public function findByStatus(string $status): array;

    public function findAll(): array;

    public function save(PurchaseOrder $order): void;

    public function delete(string $id): void;
}

<?php

declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\PurchaseInvoiceMatch;

interface PurchaseInvoiceMatchRepositoryInterface
{
    public function findById(string $id): ?PurchaseInvoiceMatch;

    public function findByPoId(string $poId): array;

    public function findByStatus(string $status): array;

    public function findAll(): array;

    public function save(PurchaseInvoiceMatch $match): void;

    public function delete(string $id): void;
}

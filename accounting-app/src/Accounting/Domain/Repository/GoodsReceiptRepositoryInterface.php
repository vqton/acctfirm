<?php

declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\GoodsReceipt;

interface GoodsReceiptRepositoryInterface
{
    public function findById(string $id): ?GoodsReceipt;

    public function findOneByGrNumber(string $grNumber): ?GoodsReceipt;

    public function findByPoId(string $poId): array;

    public function findAll(): array;

    public function save(GoodsReceipt $receipt): void;

    public function delete(string $id): void;
}

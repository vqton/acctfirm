<?php
declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\GoodsReceiptLine;

interface GoodsReceiptLineRepositoryInterface
{
    public function findByGrId(string $grId): array;
    public function save(GoodsReceiptLine $line): void;
    public function deleteByGrId(string $grId): void;
}

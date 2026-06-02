<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Bom;

interface BomRepositoryInterface
{
    public function findById(string $id): ?Bom;
    public function findByProduct(string $productId): array;
    public function findActiveByProduct(string $productId): ?Bom;
    public function findAll(): array;
    public function save(Bom $bom): void;
    public function delete(string $id): void;
}

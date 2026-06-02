<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\ProductionOrder;

interface ProductionOrderRepositoryInterface
{
    public function findById(string $id): ?ProductionOrder;
    public function findByReference(string $ref): ?ProductionOrder;
    public function findAll(): array;
    public function save(ProductionOrder $order): void;
    public function delete(string $id): void;
    public function getMaterials(string $poId): array;
    public function getLabor(string $poId): array;
    public function getOverhead(string $poId): array;
}

<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Warehouse;

interface WarehouseRepositoryInterface
{
    public function findById(string $id): ?Warehouse;
    public function findByCode(string $code): ?Warehouse;
    public function findAll(): array;
    public function save(Warehouse $w): void;
    public function delete(string $id): void;
}
<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Supplier;

interface SupplierRepositoryInterface
{
    public function findById(string $id): ?Supplier;
    public function findByCode(string $code): ?Supplier;
    public function findAll(): array;
    public function save(Supplier $supplier): void;
    public function delete(string $id): void;
}
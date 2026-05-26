<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\SalaryComponent;

interface SalaryComponentRepositoryInterface
{
    public function findById(string $id): ?SalaryComponent;
    public function findByCode(string $code): ?SalaryComponent;
    public function findAll(): array;
    public function findByType(string $type): array;
    public function save(SalaryComponent $c): void;
    public function delete(string $id): void;
}

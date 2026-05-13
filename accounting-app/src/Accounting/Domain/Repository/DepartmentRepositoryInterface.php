<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Department;

interface DepartmentRepositoryInterface
{
    public function findById(string $id): ?Department;
    public function findByCode(string $code): ?Department;
    public function findAll(): array;
    public function save(Department $d): void;
    public function delete(string $id): void;
}
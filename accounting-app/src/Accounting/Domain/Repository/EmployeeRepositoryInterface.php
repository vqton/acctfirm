<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Employee;

interface EmployeeRepositoryInterface
{
    public function findById(string $id): ?Employee;
    public function findByCode(string $code): ?Employee;
    public function findAll(): array;
    public function save(Employee $e): void;
    public function delete(string $id): void;
}
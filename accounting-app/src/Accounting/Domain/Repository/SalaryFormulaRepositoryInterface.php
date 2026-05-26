<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\SalaryFormula;

interface SalaryFormulaRepositoryInterface
{
    public function findById(string $id): ?SalaryFormula;
    public function findByCode(string $code): ?SalaryFormula;
    public function findAll(): array;
    public function findByType(string $type): array;
    public function save(SalaryFormula $f): void;
    public function delete(string $id): void;
}

<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\ValuationMethod;

interface ValuationMethodRepositoryInterface
{
    public function findById(string $id): ?ValuationMethod;
    public function findByCode(string $code): ?ValuationMethod;
    public function findAll(): array;
    public function save(ValuationMethod $method): void;
    public function delete(string $id): void;
}

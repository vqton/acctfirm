<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\TaxRate;

interface TaxRateRepositoryInterface
{
    public function findById(string $id): ?TaxRate;
    public function findByCode(string $code): ?TaxRate;
    public function findAll(): array;
    public function save(TaxRate $taxRate): void;
    public function delete(string $id): void;
}

<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\PayrollPeriod;

interface PayrollPeriodRepositoryInterface
{
    public function findById(string $id): ?PayrollPeriod;
    public function findByCode(string $code): ?PayrollPeriod;
    public function findAll(): array;
    public function findOpen(): array;
    public function save(PayrollPeriod $p): void;
    public function delete(string $id): void;
}

<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\ExchangeRate;

interface ExchangeRateRepositoryInterface
{
    public function findById(string $id): ?ExchangeRate;
    public function findByCode(string $code): ?ExchangeRate;
    public function findAll(): array;
    public function save(ExchangeRate $rate): void;
    public function delete(string $id): void;
}

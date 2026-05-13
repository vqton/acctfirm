<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Customer;

interface CustomerRepositoryInterface
{
    public function findById(string $id): ?Customer;
    public function findByCode(string $code): ?Customer;
    public function findAll(): array;
    public function save(Customer $customer): void;
    public function delete(string $id): void;
}
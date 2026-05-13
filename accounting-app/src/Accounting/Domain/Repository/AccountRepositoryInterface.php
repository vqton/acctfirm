<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Account;

interface AccountRepositoryInterface
{
    public function findById(string $id): ?Account;
    public function findByCode(string $code): ?Account;
    public function findAll(): array;
    public function save(Account $account): void;
    public function delete(string $id): void;
}
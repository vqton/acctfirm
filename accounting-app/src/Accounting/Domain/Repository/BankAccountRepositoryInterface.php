<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\BankAccount;

interface BankAccountRepositoryInterface
{
    public function findById(string $id): ?BankAccount;
    public function findByCode(string $code): ?BankAccount;
    public function findAll(): array;
    public function save(BankAccount $account): void;
    public function delete(string $id): void;
}

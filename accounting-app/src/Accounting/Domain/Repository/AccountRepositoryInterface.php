<?php
// src/Accounting/Domain/Repository/AccountRepositoryInterface.php

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Account;

interface AccountRepositoryInterface
{
    public function findById(string $id): ?Account;
    
    public function findByNumber(string $number): ?Account;
    
    public function save(Account $account): void;
    
    public function getAll(): array;
}
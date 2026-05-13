<?php
// src/Accounting/Domain/Repository/TransactionRepositoryInterface.php

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Transaction;

interface TransactionRepositoryInterface
{
    public function findById(string $id): ?Transaction;
    
    public function findByReference(string $reference): ?Transaction;
    
    public function save(Transaction $transaction): void;
    
    public function getAll(): array;
    
    public function getTransactionsByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array;
}
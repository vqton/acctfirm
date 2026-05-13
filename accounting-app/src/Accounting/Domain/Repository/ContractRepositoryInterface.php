<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Contract;

interface ContractRepositoryInterface
{
    public function findById(string $id): ?Contract;
    public function findByCode(string $code): ?Contract;
    public function findAll(): array;
    public function save(Contract $contract): void;
    public function delete(string $id): void;
}

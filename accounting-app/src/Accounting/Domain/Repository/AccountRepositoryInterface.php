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

    // COA mở rộng — Phase 0
    /** @return Account[] */
    public function findByFsMapping(string $fsMappingCode): array;

    /** @return Account[] */
    public function findControlAccounts(): array;

    /** @return Account[] */
    public function findLocked(): array;

    /** @return Account[] */
    public function findByType(string $type): array;

    /** @return Account[] */
    public function search(string $query): array;

    public function count(): int;
}

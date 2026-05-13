<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\DepreciationPolicy;

interface DepreciationPolicyRepositoryInterface
{
    public function findById(string $id): ?DepreciationPolicy;
    public function findByCode(string $code): ?DepreciationPolicy;
    public function findAll(): array;
    public function save(DepreciationPolicy $policy): void;
    public function delete(string $id): void;
}

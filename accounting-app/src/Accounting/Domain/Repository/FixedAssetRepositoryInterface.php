<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\FixedAsset;

interface FixedAssetRepositoryInterface
{
    public function findById(string $id): ?FixedAsset;
    public function findByCode(string $code): ?FixedAsset;
    public function findAll(): array;
    public function save(FixedAsset $asset): void;
    public function delete(string $id): void;
}

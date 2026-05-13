<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Item;

interface ItemRepositoryInterface
{
    public function findById(string $id): ?Item;
    public function findByCode(string $code): ?Item;
    public function findAll(): array;
    public function save(Item $item): void;
    public function delete(string $id): void;
}
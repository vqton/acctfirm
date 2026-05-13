<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Ccdc;

interface CcdcRepositoryInterface
{
    public function findById(string $id): ?Ccdc;
    public function findByCode(string $code): ?Ccdc;
    public function findAll(): array;
    public function save(Ccdc $ccdc): void;
    public function delete(string $id): void;
}

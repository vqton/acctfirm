<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Uom;

interface UomRepositoryInterface
{
    public function findById(string $id): ?Uom;
    public function findByCode(string $code): ?Uom;
    public function findAll(): array;
    public function save(Uom $uom): void;
    public function delete(string $id): void;
}

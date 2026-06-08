<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\MenuItem;

interface MenuRepositoryInterface
{
    public function findAllActive(): array;
    public function findBySection(string $section): array;
    public function findById(int $id): ?MenuItem;
    public function search(string $keyword): array;
    public function save(MenuItem $item): void;
    public function update(MenuItem $item): void;
    public function deactivate(int $id): void;
}

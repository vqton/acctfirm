<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Project;

interface ProjectRepositoryInterface
{
    public function findById(string $id): ?Project;
    public function findByCode(string $code): ?Project;
    public function findAll(): array;
    public function save(Project $project): void;
    public function delete(string $id): void;
}

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
    public function getCostSummary(string $projectId): array;
    public function getProjectTransactions(string $projectId, ?string $fromDate = null, ?string $toDate = null): array;
    public function getProgressBillings(string $projectId): array;
    public function getProjectBudgets(string $projectId): array;
}

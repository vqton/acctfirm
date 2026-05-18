<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\Project;
use Accounting\Domain\Repository\ProjectRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

class ProjectController
{
    use CrudControllerTrait;

    private ProjectRepositoryInterface $repo;
    public function __construct(ProjectRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'proj_'; }
    protected function requiredFields(): array { return ['code', 'name', 'customer_id', 'start_date']; }

    protected function createEntity(array $data): object
    {
        return new Project(
            $data['id'], $data['code'], $data['name'], $data['customer_id'],
            $data['start_date'], $data['end_date'] ?? null, (float)($data['budget'] ?? 0),
            $data['notes'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['customer_id'])) $entity->setCustomerId($data['customer_id']);
        if (isset($data['start_date'])) $entity->setStartDate($data['start_date']);
        if (isset($data['end_date'])) $entity->setEndDate($data['end_date']);
        if (isset($data['budget'])) $entity->setBudget((float)$data['budget']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
        if (isset($data['notes'])) $entity->setNotes($data['notes']);
    }
}

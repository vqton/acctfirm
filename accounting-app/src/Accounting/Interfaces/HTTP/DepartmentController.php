<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\Department;
use Accounting\Domain\Repository\DepartmentRepositoryInterface;

class DepartmentController
{
    use CrudControllerTrait;

    private DepartmentRepositoryInterface $repo;
    public function __construct(DepartmentRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'dept_'; }

    protected function createEntity(array $data): object
    {
        return new Department($data['id'], $data['code'], $data['name'], $data['parent_id'] ?? null);
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['parent_id'])) $entity->setParentId($data['parent_id']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

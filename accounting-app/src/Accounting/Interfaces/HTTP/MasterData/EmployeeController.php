<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\Employee;
use Accounting\Domain\Repository\EmployeeRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

class EmployeeController
{
    use CrudControllerTrait;

    private EmployeeRepositoryInterface $repo;
    public function __construct(EmployeeRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'emp_'; }

    protected function createEntity(array $data): object
    {
        return new Employee(
            $data['id'], $data['code'], $data['name'],
            $data['department_id'] ?? null, $data['position'] ?? null,
            $data['phone'] ?? null, $data['email'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['department_id'])) $entity->setDepartmentId($data['department_id']);
        if (isset($data['position'])) $entity->setPosition($data['position']);
        if (isset($data['phone'])) $entity->setPhone($data['phone']);
        if (isset($data['email'])) $entity->setEmail($data['email']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

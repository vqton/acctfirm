<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\ValuationMethod;
use Accounting\Domain\Repository\ValuationMethodRepositoryInterface;

class ValuationMethodController
{
    use CrudControllerTrait;

    private ValuationMethodRepositoryInterface $repo;
    public function __construct(ValuationMethodRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'vm_'; }

    protected function createEntity(array $data): object
    {
        return new ValuationMethod($data['id'], $data['code'], $data['name'], $data['description'] ?? null);
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['description'])) $entity->setDescription($data['description']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

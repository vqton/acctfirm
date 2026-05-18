<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\Uom;
use Accounting\Domain\Repository\UomRepositoryInterface;

class UomController
{
    use CrudControllerTrait;

    private UomRepositoryInterface $repo;
    public function __construct(UomRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'uom_'; }

    protected function createEntity(array $data): object
    {
        return new Uom($data['id'], $data['code'], $data['name']);
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

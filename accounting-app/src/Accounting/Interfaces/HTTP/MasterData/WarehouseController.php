<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\Warehouse;
use Accounting\Domain\Repository\WarehouseRepositoryInterface;

class WarehouseController
{
    use CrudControllerTrait;

    private WarehouseRepositoryInterface $repo;
    public function __construct(WarehouseRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'wh_'; }

    protected function createEntity(array $data): object
    {
        return new Warehouse($data['id'], $data['code'], $data['name'], $data['address'] ?? null);
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['address'])) $entity->setAddress($data['address']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\Ccdc;
use Accounting\Domain\Repository\CcdcRepositoryInterface;

class CcdcController
{
    use CrudControllerTrait;

    private CcdcRepositoryInterface $repo;
    public function __construct(CcdcRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'ccdc_'; }

    protected function createEntity(array $data): object
    {
        return new Ccdc(
            $data['id'], $data['code'], $data['name'],
            $data['unit'] ?? 'cai', (float)($data['quantity'] ?? 0),
            $data['allocation_type'] ?? 'direct', (float)($data['total_cost'] ?? 0),
            (float)($data['allocated'] ?? 0)
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['unit'])) $entity->setUnit($data['unit']);
        if (isset($data['quantity'])) $entity->setQuantity((float)$data['quantity']);
        if (isset($data['allocation_type'])) $entity->setAllocationType($data['allocation_type']);
        if (isset($data['total_cost'])) $entity->setTotalCost((float)$data['total_cost']);
        if (isset($data['allocated'])) $entity->setAllocated((float)$data['allocated']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

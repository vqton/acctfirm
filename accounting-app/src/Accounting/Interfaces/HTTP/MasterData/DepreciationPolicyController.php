<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\DepreciationPolicy;
use Accounting\Domain\Repository\DepreciationPolicyRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

class DepreciationPolicyController
{
    use CrudControllerTrait;

    private DepreciationPolicyRepositoryInterface $repo;
    public function __construct(DepreciationPolicyRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'dp_'; }

    protected function createEntity(array $data): object
    {
        return new DepreciationPolicy(
            $data['id'], $data['code'], $data['name'],
            $data['method'] ?? 'straight_line', (int)($data['default_life'] ?? 0),
            (float)($data['default_salvage_rate'] ?? 0)
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['method'])) $entity->setMethod($data['method']);
        if (isset($data['default_life'])) $entity->setDefaultLife((int)$data['default_life']);
        if (isset($data['default_salvage_rate'])) $entity->setDefaultSalvageRate((float)$data['default_salvage_rate']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

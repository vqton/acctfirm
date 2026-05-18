<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\Contract;
use Accounting\Domain\Repository\ContractRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

class ContractController
{
    use CrudControllerTrait;

    private ContractRepositoryInterface $repo;
    public function __construct(ContractRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'ct_'; }
    protected function requiredFields(): array { return ['code', 'name', 'contract_type', 'party_id', 'party_name', 'contract_date']; }

    protected function createEntity(array $data): object
    {
        return new Contract(
            $data['id'], $data['code'], $data['name'], $data['contract_type'],
            $data['party_id'], $data['party_name'], $data['contract_date'],
            (float)($data['total_amount'] ?? 0), $data['currency'] ?? 'VND', $data['notes'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['contract_type'])) $entity->setContractType($data['contract_type']);
        if (isset($data['party_id'])) $entity->setPartyId($data['party_id']);
        if (isset($data['party_name'])) $entity->setPartyName($data['party_name']);
        if (isset($data['contract_date'])) $entity->setContractDate($data['contract_date']);
        if (isset($data['total_amount'])) $entity->setTotalAmount((float)$data['total_amount']);
        if (isset($data['currency'])) $entity->setCurrency($data['currency']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
        if (isset($data['notes'])) $entity->setNotes($data['notes']);
    }
}

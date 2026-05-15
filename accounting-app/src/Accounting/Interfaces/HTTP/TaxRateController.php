<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\TaxRate;
use Accounting\Domain\Repository\TaxRateRepositoryInterface;

class TaxRateController
{
    use CrudControllerTrait;

    private TaxRateRepositoryInterface $repo;
    public function __construct(TaxRateRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'tax_'; }

    protected function createEntity(array $data): object
    {
        return new TaxRate(
            $data['id'], $data['code'], $data['name'],
            (float)($data['rate'] ?? 0), $data['tax_type'] ?? 'vat'
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['rate'])) $entity->setRate((float)$data['rate']);
        if (isset($data['tax_type'])) $entity->setTaxType($data['tax_type']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

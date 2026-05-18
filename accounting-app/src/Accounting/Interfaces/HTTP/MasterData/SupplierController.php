<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\Supplier;
use Accounting\Domain\Repository\SupplierRepositoryInterface;

class SupplierController
{
    use CrudControllerTrait;

    private SupplierRepositoryInterface $repo;
    public function __construct(SupplierRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'sup_'; }

    protected function createEntity(array $data): object
    {
        return new Supplier(
            $data['id'], $data['code'], $data['name'],
            $data['tax_code'] ?? null, $data['phone'] ?? null, $data['email'] ?? null,
            $data['address'] ?? null, $data['contact_person'] ?? null,
            $data['payment_terms'] ?? null, (float)($data['credit_limit'] ?? 0),
            $data['notes'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['tax_code'])) $entity->setTaxCode($data['tax_code']);
        if (isset($data['phone'])) $entity->setPhone($data['phone']);
        if (isset($data['email'])) $entity->setEmail($data['email']);
        if (isset($data['address'])) $entity->setAddress($data['address']);
        if (isset($data['contact_person'])) $entity->setContactPerson($data['contact_person']);
        if (isset($data['payment_terms'])) $entity->setPaymentTerms($data['payment_terms']);
        if (isset($data['credit_limit'])) $entity->setCreditLimit((float)$data['credit_limit']);
        if (isset($data['notes'])) $entity->setNotes($data['notes']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

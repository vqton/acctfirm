<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\Supplier;
use Accounting\Domain\Repository\SupplierRepositoryInterface;

class SupplierController
{
    private SupplierRepositoryInterface $repo;

    public function __construct(SupplierRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function list(): void
    {
        echo json_encode(array_map(fn($s) => $s->toArray(), $this->repo->findAll()));
    }

    public function get(string $id): void
    {
        $s = $this->repo->findById($id);
        if (!$s) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        echo json_encode($s->toArray());
    }

    public function create(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['code'], $data['name'])) {
            http_response_code(400); echo json_encode(['error' => 'code and name required']); return;
        }
        if ($this->repo->findByCode($data['code'])) {
            http_response_code(409); echo json_encode(['error' => 'Code already exists']); return;
        }
        $s = new Supplier(
            $data['id'] ?? uniqid('sup_'), $data['code'], $data['name'],
            $data['tax_code'] ?? null, $data['phone'] ?? null, $data['email'] ?? null,
            $data['address'] ?? null, $data['contact_person'] ?? null,
            $data['payment_terms'] ?? null, (float)($data['credit_limit'] ?? 0),
            $data['notes'] ?? null
        );
        $this->repo->save($s);
        http_response_code(201);
        echo json_encode($s->toArray());
    }

    public function update(string $id): void
    {
        $s = $this->repo->findById($id);
        if (!$s) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { http_response_code(400); echo json_encode(['error' => 'Invalid data']); return; }

        if (isset($data['code'])) $s->setCode($data['code']);
        if (isset($data['name'])) $s->setName($data['name']);
        if (isset($data['tax_code'])) $s->setTaxCode($data['tax_code']);
        if (isset($data['phone'])) $s->setPhone($data['phone']);
        if (isset($data['email'])) $s->setEmail($data['email']);
        if (isset($data['address'])) $s->setAddress($data['address']);
        if (isset($data['contact_person'])) $s->setContactPerson($data['contact_person']);
        if (isset($data['payment_terms'])) $s->setPaymentTerms($data['payment_terms']);
        if (isset($data['credit_limit'])) $s->setCreditLimit((float)$data['credit_limit']);
        if (isset($data['notes'])) $s->setNotes($data['notes']);
        if (isset($data['status'])) $s->setStatus((bool)$data['status']);

        $this->repo->save($s);
        echo json_encode($s->toArray());
    }

    public function delete(string $id): void
    {
        if (!$this->repo->findById($id)) {
            http_response_code(404); echo json_encode(['error' => 'Not found']); return;
        }
        $this->repo->delete($id);
        echo json_encode(['message' => 'Deleted']);
    }
}
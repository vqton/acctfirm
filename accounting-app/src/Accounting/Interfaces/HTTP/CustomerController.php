<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\Customer;
use Accounting\Domain\Repository\CustomerRepositoryInterface;

class CustomerController
{
    private CustomerRepositoryInterface $repo;

    public function __construct(CustomerRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function list(): void
    {
        echo json_encode(array_map(fn($c) => $c->toArray(), $this->repo->findAll()));
    }

    public function get(string $id): void
    {
        $c = $this->repo->findById($id);
        if (!$c) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        echo json_encode($c->toArray());
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
        $c = new Customer(
            $data['id'] ?? uniqid('cus_'), $data['code'], $data['name'],
            $data['tax_code'] ?? null, $data['phone'] ?? null, $data['email'] ?? null,
            $data['address'] ?? null, $data['contact_person'] ?? null,
            $data['payment_terms'] ?? null, (float)($data['credit_limit'] ?? 0),
            $data['notes'] ?? null
        );
        $this->repo->save($c);
        http_response_code(201);
        echo json_encode($c->toArray());
    }

    public function update(string $id): void
    {
        $c = $this->repo->findById($id);
        if (!$c) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { http_response_code(400); echo json_encode(['error' => 'Invalid data']); return; }

        if (isset($data['code'])) $c->setCode($data['code']);
        if (isset($data['name'])) $c->setName($data['name']);
        if (isset($data['tax_code'])) $c->setTaxCode($data['tax_code']);
        if (isset($data['phone'])) $c->setPhone($data['phone']);
        if (isset($data['email'])) $c->setEmail($data['email']);
        if (isset($data['address'])) $c->setAddress($data['address']);
        if (isset($data['contact_person'])) $c->setContactPerson($data['contact_person']);
        if (isset($data['payment_terms'])) $c->setPaymentTerms($data['payment_terms']);
        if (isset($data['credit_limit'])) $c->setCreditLimit((float)$data['credit_limit']);
        if (isset($data['notes'])) $c->setNotes($data['notes']);
        if (isset($data['status'])) $c->setStatus((bool)$data['status']);

        $this->repo->save($c);
        echo json_encode($c->toArray());
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
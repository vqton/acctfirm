<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\Employee;
use Accounting\Domain\Repository\EmployeeRepositoryInterface;

class EmployeeController
{
    private EmployeeRepositoryInterface $repo;

    public function __construct(EmployeeRepositoryInterface $repo) { $this->repo = $repo; }

    public function list(): void { echo json_encode(array_map(fn($x) => $x->toArray(), $this->repo->findAll())); }

    public function get(string $id): void
    {
        $x = $this->repo->findById($id);
        if (!$x) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        echo json_encode($x->toArray());
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
        $x = new Employee(
            $data['id'] ?? uniqid('emp_'), $data['code'], $data['name'],
            $data['department_id'] ?? null, $data['position'] ?? null,
            $data['phone'] ?? null, $data['email'] ?? null
        );
        $this->repo->save($x);
        http_response_code(201);
        echo json_encode($x->toArray());
    }

    public function update(string $id): void
    {
        $x = $this->repo->findById($id);
        if (!$x) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { http_response_code(400); echo json_encode(['error' => 'Invalid data']); return; }
        if (isset($data['code'])) $x->setCode($data['code']);
        if (isset($data['name'])) $x->setName($data['name']);
        if (isset($data['department_id'])) $x->setDepartmentId($data['department_id']);
        if (isset($data['position'])) $x->setPosition($data['position']);
        if (isset($data['phone'])) $x->setPhone($data['phone']);
        if (isset($data['email'])) $x->setEmail($data['email']);
        if (isset($data['status'])) $x->setStatus((bool)$data['status']);
        $this->repo->save($x);
        echo json_encode($x->toArray());
    }

    public function delete(string $id): void
    {
        if (!$this->repo->findById($id)) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        $this->repo->delete($id);
        echo json_encode(['message' => 'Deleted']);
    }
}
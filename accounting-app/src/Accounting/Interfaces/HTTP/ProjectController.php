<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\Project;
use Accounting\Domain\Repository\ProjectRepositoryInterface;

class ProjectController
{
    private ProjectRepositoryInterface $repo;

    public function __construct(ProjectRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function list(): void
    {
        echo json_encode(array_map(fn($i) => $i->toArray(), $this->repo->findAll()));
    }

    public function get(string $id): void
    {
        $item = $this->repo->findById($id);
        if (!$item) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        echo json_encode($item->toArray());
    }

    public function create(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['code'], $data['name'], $data['customer_id'], $data['start_date'])) {
            http_response_code(400); echo json_encode(['error' => 'code, name, customer_id, start_date required']); return;
        }
        if ($this->repo->findByCode($data['code'])) {
            http_response_code(409); echo json_encode(['error' => 'Code already exists']); return;
        }
        $item = new Project(
            $data['id'] ?? uniqid('proj_'), $data['code'], $data['name'], $data['customer_id'],
            $data['start_date'], $data['end_date'] ?? null, (float)($data['budget'] ?? 0),
            $data['notes'] ?? null
        );
        $this->repo->save($item);
        http_response_code(201);
        echo json_encode($item->toArray());
    }

    public function update(string $id): void
    {
        $item = $this->repo->findById($id);
        if (!$item) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { http_response_code(400); echo json_encode(['error' => 'Invalid data']); return; }

        if (isset($data['code'])) $item->setCode($data['code']);
        if (isset($data['name'])) $item->setName($data['name']);
        if (isset($data['customer_id'])) $item->setCustomerId($data['customer_id']);
        if (isset($data['start_date'])) $item->setStartDate($data['start_date']);
        if (isset($data['end_date'])) $item->setEndDate($data['end_date']);
        if (isset($data['budget'])) $item->setBudget((float)$data['budget']);
        if (isset($data['status'])) $item->setStatus((bool)$data['status']);
        if (isset($data['notes'])) $item->setNotes($data['notes']);

        $this->repo->save($item);
        echo json_encode($item->toArray());
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

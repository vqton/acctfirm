<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\Ccdc;
use Accounting\Domain\Repository\CcdcRepositoryInterface;

class CcdcController
{
    private CcdcRepositoryInterface $repo;

    public function __construct(CcdcRepositoryInterface $repo)
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
        if (!$data || !isset($data['code'], $data['name'])) {
            http_response_code(400); echo json_encode(['error' => 'code and name required']); return;
        }
        if ($this->repo->findByCode($data['code'])) {
            http_response_code(409); echo json_encode(['error' => 'Code already exists']); return;
        }
        $item = new Ccdc(
            $data['id'] ?? uniqid('ccdc_'), $data['code'], $data['name'],
            $data['unit'] ?? 'cai', (float)($data['quantity'] ?? 0),
            $data['allocation_type'] ?? 'direct', (float)($data['total_cost'] ?? 0),
            (float)($data['allocated'] ?? 0)
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
        if (isset($data['unit'])) $item->setUnit($data['unit']);
        if (isset($data['quantity'])) $item->setQuantity((float)$data['quantity']);
        if (isset($data['allocation_type'])) $item->setAllocationType($data['allocation_type']);
        if (isset($data['total_cost'])) $item->setTotalCost((float)$data['total_cost']);
        if (isset($data['allocated'])) $item->setAllocated((float)$data['allocated']);
        if (isset($data['status'])) $item->setStatus((bool)$data['status']);

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

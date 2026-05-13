<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\DepreciationPolicy;
use Accounting\Domain\Repository\DepreciationPolicyRepositoryInterface;

class DepreciationPolicyController
{
    private DepreciationPolicyRepositoryInterface $repo;

    public function __construct(DepreciationPolicyRepositoryInterface $repo)
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
        $item = new DepreciationPolicy(
            $data['id'] ?? uniqid('dp_'), $data['code'], $data['name'],
            $data['method'] ?? 'straight_line', (int)($data['default_life'] ?? 0),
            (float)($data['default_salvage_rate'] ?? 0)
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
        if (isset($data['method'])) $item->setMethod($data['method']);
        if (isset($data['default_life'])) $item->setDefaultLife((int)$data['default_life']);
        if (isset($data['default_salvage_rate'])) $item->setDefaultSalvageRate((float)$data['default_salvage_rate']);
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

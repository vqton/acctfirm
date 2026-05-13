<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\Contract;
use Accounting\Domain\Repository\ContractRepositoryInterface;

class ContractController
{
    private ContractRepositoryInterface $repo;

    public function __construct(ContractRepositoryInterface $repo)
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
        if (!$data || !isset($data['code'], $data['name'], $data['contract_type'], $data['party_id'], $data['party_name'], $data['contract_date'])) {
            http_response_code(400); echo json_encode(['error' => 'code, name, contract_type, party_id, party_name, contract_date required']); return;
        }
        if ($this->repo->findByCode($data['code'])) {
            http_response_code(409); echo json_encode(['error' => 'Code already exists']); return;
        }
        $item = new Contract(
            $data['id'] ?? uniqid('ct_'), $data['code'], $data['name'], $data['contract_type'],
            $data['party_id'], $data['party_name'], $data['contract_date'],
            (float)($data['total_amount'] ?? 0), $data['currency'] ?? 'VND', $data['notes'] ?? null
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
        if (isset($data['contract_type'])) $item->setContractType($data['contract_type']);
        if (isset($data['party_id'])) $item->setPartyId($data['party_id']);
        if (isset($data['party_name'])) $item->setPartyName($data['party_name']);
        if (isset($data['contract_date'])) $item->setContractDate($data['contract_date']);
        if (isset($data['total_amount'])) $item->setTotalAmount((float)$data['total_amount']);
        if (isset($data['currency'])) $item->setCurrency($data['currency']);
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

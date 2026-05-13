<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\FixedAsset;
use Accounting\Domain\Repository\FixedAssetRepositoryInterface;

class FixedAssetController
{
    private FixedAssetRepositoryInterface $repo;

    public function __construct(FixedAssetRepositoryInterface $repo)
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
        if (!$data || !isset($data['code'], $data['name'], $data['purchase_date'], $data['original_cost'])) {
            http_response_code(400); echo json_encode(['error' => 'code, name, purchase_date, original_cost required']); return;
        }
        if ($this->repo->findByCode($data['code'])) {
            http_response_code(409); echo json_encode(['error' => 'Code already exists']); return;
        }
        $item = new FixedAsset(
            $data['id'] ?? uniqid('fa_'), $data['code'], $data['name'], $data['purchase_date'],
            (float)$data['original_cost'], $data['depreciation_method'] ?? 'straight_line',
            (int)($data['useful_life'] ?? 0), (float)($data['salvage_value'] ?? 0),
            (float)($data['monthly_depreciation'] ?? 0), (float)($data['accumulated_depreciation'] ?? 0),
            (float)($data['net_book_value'] ?? 0), $data['department_id'] ?? null,
            $data['employee_id'] ?? null, $data['location'] ?? null, $data['notes'] ?? null
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
        if (isset($data['purchase_date'])) $item->setPurchaseDate($data['purchase_date']);
        if (isset($data['original_cost'])) $item->setOriginalCost((float)$data['original_cost']);
        if (isset($data['depreciation_method'])) $item->setDepreciationMethod($data['depreciation_method']);
        if (isset($data['useful_life'])) $item->setUsefulLife((int)$data['useful_life']);
        if (isset($data['salvage_value'])) $item->setSalvageValue((float)$data['salvage_value']);
        if (isset($data['monthly_depreciation'])) $item->setMonthlyDepreciation((float)$data['monthly_depreciation']);
        if (isset($data['accumulated_depreciation'])) $item->setAccumulatedDepreciation((float)$data['accumulated_depreciation']);
        if (isset($data['net_book_value'])) $item->setNetBookValue((float)$data['net_book_value']);
        if (isset($data['department_id'])) $item->setDepartmentId($data['department_id']);
        if (isset($data['employee_id'])) $item->setEmployeeId($data['employee_id']);
        if (isset($data['location'])) $item->setLocation($data['location']);
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

<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\Item;
use Accounting\Domain\Repository\ItemRepositoryInterface;

class ItemController
{
    private ItemRepositoryInterface $repo;

    public function __construct(ItemRepositoryInterface $repo)
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
        $item = new Item(
            $data['id'] ?? uniqid('itm_'), $data['code'], $data['name'],
            $data['item_type'] ?? 'material', $data['unit'] ?? 'cai',
            (float)($data['purchase_price'] ?? 0), (float)($data['sale_price'] ?? 0),
            (float)($data['stock_qty'] ?? 0), (float)($data['min_stock'] ?? 0),
            $data['description'] ?? null
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
        if (isset($data['item_type'])) $item->setItemType($data['item_type']);
        if (isset($data['unit'])) $item->setUnit($data['unit']);
        if (isset($data['purchase_price'])) $item->setPurchasePrice((float)$data['purchase_price']);
        if (isset($data['sale_price'])) $item->setSalePrice((float)$data['sale_price']);
        if (isset($data['stock_qty'])) $item->setStockQty((float)$data['stock_qty']);
        if (isset($data['min_stock'])) $item->setMinStock((float)$data['min_stock']);
        if (isset($data['description'])) $item->setDescription($data['description']);
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
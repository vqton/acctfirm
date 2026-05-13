<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Domain\Repository\WarehouseRepositoryInterface;

class TransferController
{
    private InventoryService $inventory;
    private ItemRepositoryInterface $itemRepo;
    private WarehouseRepositoryInterface $warehouseRepo;

    public function __construct(
        InventoryService $inventory,
        ItemRepositoryInterface $itemRepo,
        WarehouseRepositoryInterface $warehouseRepo
    ) {
        $this->inventory = $inventory;
        $this->itemRepo = $itemRepo;
        $this->warehouseRepo = $warehouseRepo;
    }

    public function list(): void
    {
        // Return list of transfers from transactions with "Transfer:" prefix
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT t.id, t.description, t.reference, t.status, t.created_at
            FROM transactions t WHERE t.description LIKE 'Transfer:%' ORDER BY t.created_at DESC");
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function transfer(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'], $data['to_warehouse_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id, qty, to_warehouse_id required']);
            return;
        }

        $fromWarehouseId = $data['from_warehouse_id'] ?? null;
        $toWarehouseId = $data['to_warehouse_id'];
        $qty = (float)$data['qty'];

        if ($qty <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'qty must be positive']);
            return;
        }

        try {
            $result = $this->inventory->transferGoods(
                $data['item_id'],
                $qty,
                $fromWarehouseId,
                $toWarehouseId,
                $data['reference'] ?? uniqid('trf_'),
                $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function items(): void
    {
        echo json_encode(array_map(fn($x) => $x->toArray(), $this->itemRepo->findAll()));
    }

    public function warehouses(): void
    {
        echo json_encode(array_map(fn($x) => $x->toArray(), $this->warehouseRepo->findAll()));
    }

    private function getPdo(): \PDO
    {
        $ref = new \ReflectionClass($this->itemRepo);
        $prop = $ref->getProperty('pdo');
        $prop->setAccessible(true);
        return $prop->getValue($this->itemRepo);
    }
}

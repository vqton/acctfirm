<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Domain\Repository\WarehouseRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class TransferController
{
    private InventoryService $inventory;
    private ItemRepositoryInterface $itemRepo;
    private WarehouseRepositoryInterface $warehouseRepo;
    private \PDO $pdo;

    public function __construct(
        InventoryService $inventory,
        ItemRepositoryInterface $itemRepo,
        WarehouseRepositoryInterface $warehouseRepo,
        \PDO $pdo
    ) {
        $this->inventory = $inventory;
        $this->itemRepo = $itemRepo;
        $this->warehouseRepo = $warehouseRepo;
        $this->pdo = $pdo;
    }

    public function list(): void
    {
        // Return list of transfers from transactions with "Transfer:" prefix
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT t.id, t.description, t.reference, t.status, t.created_at
            FROM transactions t WHERE t.description LIKE 'Transfer:%' ORDER BY t.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function transfer(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'], $data['to_warehouse_id'])) {
            JsonResponse::error('item_id, qty, to_warehouse_id required');
            return;
        }

        $fromWarehouseId = $data['from_warehouse_id'] ?? null;
        $toWarehouseId = $data['to_warehouse_id'];
        $qty = (float)$data['qty'];

        if ($qty <= 0) {
            JsonResponse::error('qty must be positive');
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
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    public function items(): void
    {
        JsonResponse::ok(array_map(fn($x) => $x->toArray(), $this->itemRepo->findAll()));
    }

    public function warehouses(): void
    {
        JsonResponse::ok(array_map(fn($x) => $x->toArray(), $this->warehouseRepo->findAll()));
    }

    private function getPdo(): \PDO
    {
        return $this->pdo;
    }
}

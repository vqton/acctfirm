<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class InventoryTransitController
{
    private InventoryService $inventory;
    private ItemRepositoryInterface $itemRepo;
    private \PDO $pdo;

    public function __construct(InventoryService $inventory, ItemRepositoryInterface $itemRepo, \PDO $pdo)
    {
        $this->inventory = $inventory;
        $this->itemRepo = $itemRepo;
        $this->pdo = $pdo;
    }

    public function list(): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT it.*, i.code as item_code, i.name as item_name
            FROM inventory_in_transit it JOIN items i ON i.id = it.item_id ORDER BY it.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function record(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'], $data['unit_price'])) {
            JsonResponse::error('item_id, qty, unit_price required');
            return;
        }
        try {
            $result = $this->inventory->recordInTransit(
                $data['item_id'], (float)$data['qty'], (float)$data['unit_price'],
                $data['addon_costs'] ?? [], $data['reference'] ?? uniqid('po_'), $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    public function receive(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['transit_id'], $data['qty'])) {
            JsonResponse::error('transit_id, qty required');
            return;
        }
        try {
            $result = $this->inventory->receiveFromTransit(
                $data['transit_id'], (float)$data['qty'],
                $data['reference'] ?? uniqid('recv_'), $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    private function getPdo(): \PDO
    {
        return $this->pdo;
    }
}

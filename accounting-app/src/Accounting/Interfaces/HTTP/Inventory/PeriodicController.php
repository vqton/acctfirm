<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Infrastructure\JsonResponse;

class PeriodicController
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
        $stmt = $pdo->query("SELECT p.*, i.code as item_code, i.name as item_name FROM periodic_inventory p JOIN items i ON i.id = p.item_id ORDER BY p.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function close(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['closing_qty'], $data['closing_unit_cost'])) {
            JsonResponse::error('item_id, closing_qty, closing_unit_cost required');
            return;
        }
        try {
            $result = $this->inventory->closePeriodicInventory(
                $data['item_id'], (float)$data['closing_qty'], (float)$data['closing_unit_cost'],
                $data['reference'] ?? uniqid('prd_'), $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    private function getPdo(): \PDO
    {
        return $this->pdo;
    }
}

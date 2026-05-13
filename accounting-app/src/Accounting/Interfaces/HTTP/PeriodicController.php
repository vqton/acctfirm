<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;

class PeriodicController
{
    private InventoryService $inventory;
    private ItemRepositoryInterface $itemRepo;

    public function __construct(InventoryService $inventory, ItemRepositoryInterface $itemRepo)
    {
        $this->inventory = $inventory;
        $this->itemRepo = $itemRepo;
    }

    public function list(): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT p.*, i.code as item_code, i.name as item_name FROM periodic_inventory p JOIN items i ON i.id = p.item_id ORDER BY p.created_at DESC");
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function close(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['closing_qty'], $data['closing_unit_cost'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id, closing_qty, closing_unit_cost required']);
            return;
        }
        try {
            $result = $this->inventory->closePeriodicInventory(
                $data['item_id'], (float)$data['closing_qty'], (float)$data['closing_unit_cost'],
                $data['reference'] ?? uniqid('prd_'), $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function getPdo(): \PDO
    {
        $ref = new \ReflectionClass($this->itemRepo);
        $prop = $ref->getProperty('pdo');
        $prop->setAccessible(true);
        return $prop->getValue($this->itemRepo);
    }
}

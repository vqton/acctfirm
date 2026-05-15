<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;

class ConsignmentController
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
        $stmt = $pdo->query("SELECT c.*, i.code as item_code, i.name as item_name
            FROM inventory_consignment c JOIN items i ON i.id = c.item_id ORDER BY c.created_at DESC");
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function consign(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'], $data['consignee'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id, qty, consignee required']);
            return;
        }
        try {
            $result = $this->inventory->consignGoods(
                $data['item_id'], (float)$data['qty'], $data['consignee'],
                $data['reference'] ?? uniqid('csn_'), $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function sell(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['consignment_id'], $data['qty'])) {
            http_response_code(400);
            echo json_encode(['error' => 'consignment_id, qty required']);
            return;
        }
        try {
            $result = $this->inventory->sellConsigned(
                $data['consignment_id'], (float)$data['qty'],
                $data['reference'] ?? uniqid('sale_'), $data['created_by'] ?? 'system'
            );
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function returnConsignment(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['consignment_id'], $data['qty'])) {
            http_response_code(400);
            echo json_encode(['error' => 'consignment_id, qty required']);
            return;
        }
        try {
            $result = $this->inventory->returnConsigned(
                $data['consignment_id'], (float)$data['qty'],
                $data['reference'] ?? uniqid('ret_'), $data['created_by'] ?? 'system'
            );
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function getPdo(): \PDO
    {
        return $this->pdo;
    }
}

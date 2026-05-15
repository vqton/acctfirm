<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\InventoryService;

class ImpairmentController
{
    private InventoryService $inventory;
    private \PDO $pdo;

    public function __construct(InventoryService $inventory, \PDO $pdo)
    {
        $this->inventory = $inventory;
        $this->pdo = $pdo;
    }

    public function list(): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT ip.*, i.code as item_code, i.name as item_name FROM inventory_impairment ip JOIN items i ON i.id = ip.item_id ORDER BY ip.created_at DESC");
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function record(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id, amount required']);
            return;
        }
        try {
            $result = $this->inventory->recordImpairment(
                $data['item_id'], (float)$data['amount'],
                $data['reference'] ?? uniqid('imp_'), $data['notes'] ?? '',
                $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function reverse(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['impairment_id'], $data['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'impairment_id, amount required']);
            return;
        }
        try {
            $result = $this->inventory->reverseImpairment(
                $data['impairment_id'], (float)$data['amount'],
                $data['reference'] ?? uniqid('rev_'), $data['created_by'] ?? 'system'
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

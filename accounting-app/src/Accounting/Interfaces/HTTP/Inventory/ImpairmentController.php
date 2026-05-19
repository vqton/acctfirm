<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Service\InventoryService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

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
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function record(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['amount'])) {
            JsonResponse::error('item_id, amount required');
            return;
        }
        try {
            $result = $this->inventory->recordImpairment(
                $data['item_id'], (float)$data['amount'],
                $data['reference'] ?? uniqid('imp_'), $data['notes'] ?? '',
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    public function reverse(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['impairment_id'], $data['amount'])) {
            JsonResponse::error('impairment_id, amount required');
            return;
        }
        try {
            $result = $this->inventory->reverseImpairment(
                $data['impairment_id'], (float)$data['amount'],
                $data['reference'] ?? uniqid('rev_'), $data['created_by'] ?? 'system'
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

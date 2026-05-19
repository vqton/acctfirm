<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class PhysicalCountController
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

    public function sessions(): void
    {
        $pdo = $this->getPdo();
        JsonResponse::ok($pdo->query("SELECT * FROM inventory_count_sessions ORDER BY created_at DESC")->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function lines(string $sessionId): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT cl.*, i.code as item_code, i.name as item_name FROM inventory_count_lines cl JOIN items i ON i.id = cl.item_id WHERE cl.session_id = ? ORDER BY cl.created_at");
        $stmt->execute([$sessionId]);
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function createSession(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['lines']) || count($data['lines']) === 0) {
            JsonResponse::error('lines required');
            return;
        }
        try {
            $result = $this->inventory->createCountSession(
                $data['lines'], $data['reference'] ?? uniqid('cnt_'),
                $data['notes'] ?? '', $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    public function adjust(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['actual_qty'])) {
            JsonResponse::error('item_id, actual_qty required');
            return;
        }
        try {
            $result = $this->inventory->adjustPhysicalCount(
                $data['item_id'], (float)$data['actual_qty'],
                $data['reference'] ?? uniqid('adj_'), $data['created_by'] ?? 'system'
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

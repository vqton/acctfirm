<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

class IssueController
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
        $pdo = $this->pdo;
        $stmt = $pdo->query("SELECT t.id, t.description, t.reference, t.status, t.created_at
            FROM transactions t WHERE t.description LIKE 'Goods issue:%' ORDER BY t.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function issue(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'])) {
            JsonResponse::error('item_id, qty required');
            return;
        }
        $qty = (float)$data['qty'];
        if ($qty <= 0) {
            JsonResponse::error('qty must be positive');
            return;
        }
        try {
            $result = $this->inventory->issueGoods(
                $data['item_id'], $qty,
                $data['issue_type'] ?? 'sale',
                $data['reference'] ?? uniqid('iss_'),
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
}

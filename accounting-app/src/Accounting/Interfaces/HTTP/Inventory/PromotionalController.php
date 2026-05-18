<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Infrastructure\JsonResponse;

class PromotionalController
{
    private InventoryService $inventory;
    private ItemRepositoryInterface $itemRepo;

    public function __construct(InventoryService $inventory, ItemRepositoryInterface $itemRepo)
    {
        $this->inventory = $inventory;
        $this->itemRepo = $itemRepo;
    }

    public function issue(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'])) {
            JsonResponse::error('item_id, qty required');
            return;
        }
        try {
            $result = $this->inventory->issuePromotional(
                $data['item_id'], (float)$data['qty'],
                $data['reference'] ?? uniqid('promo_'), $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }
}

<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;

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
            http_response_code(400);
            echo json_encode(['error' => 'item_id, qty required']);
            return;
        }
        try {
            $result = $this->inventory->issuePromotional(
                $data['item_id'], (float)$data['qty'],
                $data['reference'] ?? uniqid('promo_'), $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

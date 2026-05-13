<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;

class PhysicalCountController
{
    private InventoryService $inventory;
    private ItemRepositoryInterface $itemRepo;

    public function __construct(InventoryService $inventory, ItemRepositoryInterface $itemRepo)
    {
        $this->inventory = $inventory;
        $this->itemRepo = $itemRepo;
    }

    public function sessions(): void
    {
        $pdo = $this->getPdo();
        echo json_encode($pdo->query("SELECT * FROM inventory_count_sessions ORDER BY created_at DESC")->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function lines(string $sessionId): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT cl.*, i.code as item_code, i.name as item_name FROM inventory_count_lines cl JOIN items i ON i.id = cl.item_id WHERE cl.session_id = ? ORDER BY cl.created_at");
        $stmt->execute([$sessionId]);
        echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function createSession(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['lines']) || count($data['lines']) === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'lines required']);
            return;
        }
        try {
            $result = $this->inventory->createCountSession(
                $data['lines'], $data['reference'] ?? uniqid('cnt_'),
                $data['notes'] ?? '', $data['created_by'] ?? 'system'
            );
            http_response_code(201);
            echo json_encode($result);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function adjust(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['actual_qty'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id, actual_qty required']);
            return;
        }
        try {
            $result = $this->inventory->adjustPhysicalCount(
                $data['item_id'], (float)$data['actual_qty'],
                $data['reference'] ?? uniqid('adj_'), $data['created_by'] ?? 'system'
            );
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

<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Hàng đang đi đường (In Transit / Goods in Transit)
 *
 * Mục đích nghiệp vụ:
 *   - Ghi nhận hàng mua đang đi đường (chưa về kho)
 *   - Ghi nhận chi phí phát sinh thêm (addon costs: vận chuyển, bảo hiểm...)
 *   - Xử lý khi hàng về kho (receive) — kết chuyển từ 157 → 152/156
 *   - Theo dõi số lượng và giá trị hàng đang đi đường
 *
 * API endpoints:
 *   GET  /api/inventory-transit       — Danh sách hàng đang đi
 *   POST /api/inventory-transit       — Ghi nhận hàng mới đi đường
 *   POST /api/inventory-transit/receive — Nhận hàng về kho
 *
 * Rủi ro:
 *   - Hàng về kho nhưng không cập nhật → tồn kho thấp hơn thực tế
 *   - Chi phí vận chuyển không được phân bổ → sai giá vốn
 *   - R007: Multi-step (ghi nhận + kết chuyển) cần transaction
 *
 * Tích hợp:
 *   - InventoryService.recordInTransit ghi Nợ 157 / Có 331
 *   - Khi receive: Nợ 152,156 / Có 157
 *   - Addon costs được phân bổ vào giá trị hàng nhập kho
 */
class InventoryTransitController
{
    private InventoryServiceInterface $inventory;
    private ItemRepositoryInterface $itemRepo;
    private \PDO $pdo;

    public function __construct(InventoryServiceInterface $inventory, ItemRepositoryInterface $itemRepo, \PDO $pdo)
    {
        $this->inventory = $inventory;
        $this->itemRepo = $itemRepo;
        $this->pdo = $pdo;
    }

    public function list(): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT it.*, i.code as item_code, i.name as item_name
            FROM inventory_in_transit it JOIN items i ON i.id = it.item_id ORDER BY it.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Ghi nhận hàng mua đang đi đường (chưa về kho) — TK 157
    // Input: { item_id, qty, unit_price, addon_costs?, reference?, created_by? }
    // Output: { transit_id, item_id, qty, total_cost } — 201 Created
    // Service: InventoryService.recordInTransit() → JournalService.postEntry
    // Hạch toán: Nợ 157 (hàng đi đường) / Có 331 (công nợ NCC)
    // Rủi ro: Hàng về kho mà quên kết chuyển 157→152/156 → tồn kho thiếu
    // Quy trình: record() → khi hàng về → receive() kết chuyển 157→152/156
    public function record(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'], $data['unit_price'])) {
            JsonResponse::error('Vui lòng nhập mã vật tư, số lượng và đơn giá');
            return;
        }
        try {
            $result = $this->inventory->recordInTransit(
                $data['item_id'], (float)$data['qty'], (float)$data['unit_price'],
                $data['addon_costs'] ?? [], $data['reference'] ?? uniqid('po_'), $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // NGHIỆP VỤ: Nhận hàng từ đường đi về kho — kết chuyển 157 → 152/156
    // Input: { transit_id, qty, reference?, created_by? }
    // Output: { receipt_id, item_id, qty, previous_account: 157, new_account: 152/156 }
    // Service: InventoryService.receiveFromTransit() → JournalService.postEntry
    // Hạch toán: Nợ 152,156 (tồn kho tăng) / Có 157 (hàng đi đường giảm)
    // Rủi ro: receive qty > transit qty → lỗi. Nhận một phần → theo dõi số dư 157 còn lại
    // Tích hợp: ReceiptController xử lý nhập kho song song
    public function receive(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['transit_id'], $data['qty'])) {
            JsonResponse::error('Vui lòng nhập mã hàng đi đường và số lượng');
            return;
        }
        try {
            $result = $this->inventory->receiveFromTransit(
                $data['transit_id'], (float)$data['qty'],
                $data['reference'] ?? uniqid('recv_'), $data['created_by'] ?? 'system'
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

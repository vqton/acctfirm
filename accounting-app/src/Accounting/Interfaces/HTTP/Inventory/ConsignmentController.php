<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Hàng gửi bán (Consignment)
 *
 * Mục đích nghiệp vụ:
 *   - Ghi nhận hàng gửi bán đại lý (ký gửi) — hàng vẫn thuộc sở hữu DN
 *   - Ghi nhận hàng bán được từ đại lý
 *   - Theo dõi số lượng hàng đang gửi tại từng đại lý
 *   - Hạch toán: xuất kho gửi bán (157) → khi bán được ghi nhận doanh thu
 *
 * API endpoints:
 *   GET  /api/consignment        — Danh sách hàng gửi bán
 *   POST /api/consignment        — Gửi hàng cho đại lý
 *   POST /api/consignment/sell   — Ghi nhận hàng đã bán từ đại lý
 *   POST /api/consignment/return — Nhận lại hàng từ đại lý
 *
 * Rủi ro:
 *   - Hàng gửi bán chưa mất quyền sở hữu → không ghi nhận doanh thu khi gửi
 *   - Chỉ ghi nhận doanh thu (511) và giá vốn (632) khi đại lý báo bán
 *   - Sai sót trong theo dõi số lượng → mất hàng
 *
 * Tích hợp:
 *   - Chuyển kho từ 152/156 → 157 khi gửi hàng (qua InventoryService)
 *   - Khi bán: ghi nhận Nợ 131, 111 / Có 511, 3331 và Nợ 632 / Có 157
 *   - IssueController và ReceiptController xử lý riêng xuất/nhập kho
 */
class ConsignmentController
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
        $stmt = $pdo->query("SELECT c.*, i.code as item_code, i.name as item_name
            FROM inventory_consignment c JOIN items i ON i.id = c.item_id ORDER BY c.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Gửi hàng cho đại lý bán (ký gửi) — chuyển 152/156 → 157
    // Input: { item_id, qty, consignee, reference?, created_by? }
    // Output: { consignment_id, item_id, qty, account: 157 } — 201 Created
    // Service: InventoryService.consignGoods() — xuất kho gửi bán
    // Hạch toán: Nợ 157 (hàng gửi bán) / Có 152,156 (tồn kho)
    // Rủi ro: Hàng gửi bán vẫn là hàng của DN (chưa mất quyền sở hữu). KHÔNG ghi nhận doanh thu khi gửi
    // Chỉ ghi nhận doanh thu (511) và giá vốn (632) khi đại lý báo bán → gọi sell()
    // Audit trail: Theo dõi số lượng hàng đang gửi tại từng đại lý
    public function consign(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'], $data['consignee'])) {
            JsonResponse::error('Vui lòng nhập mã vật tư, số lượng và bên nhận ký gửi');
            return;
        }
        try {
            $result = $this->inventory->consignGoods(
                $data['item_id'], (float)$data['qty'], $data['consignee'],
                $data['reference'] ?? uniqid('csn_'), $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // NGHIỆP VỤ: Ghi nhận hàng gửi bán đã bán được (đại lý báo bán)
    // Input: { consignment_id, qty, reference?, created_by? }
    // Output: { sale_id, item_id, qty, cost_of_goods, revenue_entry }
    // Service: InventoryService.sellConsigned() → JournalService.postEntry
    // Hạch toán: (1) Nợ 632 / Có 157 (giá vốn — giảm hàng gửi bán)
    // (2) Nợ 131,111 / Có 511,3331 (doanh thu + thuế — qua ArController)
    // Rủi ro: Chỉ ghi nhận khi có báo bán từ đại lý. Sai giá vốn (từ 157) → sai BC02
    public function sell(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['consignment_id'], $data['qty'])) {
            JsonResponse::error('Vui lòng nhập mã ký gửi và số lượng');
            return;
        }
        try {
            $result = $this->inventory->sellConsigned(
                $data['consignment_id'], (float)$data['qty'],
                $data['reference'] ?? uniqid('sale_'), $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }
    
    // NGHIỆP VỤ: Nhận lại hàng từ đại lý (hàng gửi không bán được)
    // Input: { consignment_id, qty, reference?, created_by? }
    // Output: { return_id, item_id, qty, account: 152/156 }
    // Service: InventoryService.returnConsigned() — nhập kho lại
    // Hạch toán: Nợ 152,156 (tồn kho tăng) / Có 157 (hàng gửi bán giảm)
    // Rủi ro: Đơn giá nhập kho lại = đơn giá gốc khi gửi. Chi phí vận chuyển (nếu có) ghi nhận riêng
    public function returnConsignment(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['consignment_id'], $data['qty'])) {
            JsonResponse::error('Vui lòng nhập mã ký gửi và số lượng');
            return;
        }
        try {
            $result = $this->inventory->returnConsigned(
                $data['consignment_id'], (float)$data['qty'],
                $data['reference'] ?? uniqid('ret_'), $data['created_by'] ?? 'system'
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

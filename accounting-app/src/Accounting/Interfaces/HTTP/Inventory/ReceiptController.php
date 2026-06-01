<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

/**
 * MODULE: Nhập kho (Goods Receipt)
 *
 * Mục đích nghiệp vụ:
 *   - Ghi nhận nhập kho hàng hóa, nguyên vật liệu
 *   - Hạch toán tăng tồn kho (Nợ 152, 156 / Có 331)
 *   - Xử lý chi phí phát sinh thêm (addon costs) phân bổ vào giá vốn
 *   - Cập nhật đơn giá nhập cho tính giá xuất kho (FIFO/Bình quân)
 *
 * API endpoints:
 *   GET  /api/inventory/receipts       — Danh sách phiếu nhập
 *   POST /api/inventory/receipts       — Tạo phiếu nhập mới
 *   GET  /api/inventory/receipts/items — Danh sách item để chọn
 *
 * Rủi ro:
 *   - R007: Nhập kho không ghi nhận bút toán → sai tồn kho
 *   - Sai đơn giá nhập → sai giá vốn khi xuất
 *   - Addon costs không phân bổ đúng → sai giá trị hàng tồn kho
 *   - Nhập kho mà chưa có hóa đơn → cần theo dõi tạm thời
 *
 * Tích hợp:
 *   - InventoryService.receiveGoods ghi nhận và tạo bút toán
 *   - ApController ghi nhận hóa đơn mua hàng đồng thời
 *   - InventoryTransitController xử lý hàng đi đường trước khi nhập
 */
class ReceiptController
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
        $pdo = $this->pdo;
        $stmt = $pdo->query("SELECT t.id, t.description, t.reference, t.status, t.created_at
            FROM transactions t WHERE t.description LIKE 'Goods receipt:%' ORDER BY t.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Ghi nhận nhập kho (phiếu nhập kho — PNK)
    // Input: { item_id, qty, unit_price, addon_costs?, reference?, created_by? }
    // Output: { transaction_id, item_id, qty, unit_price, total_cost } — 201 Created
    // Service: InventoryService.receiveGoods() → JournalService.postEntry
    // Hạch toán: Nợ 152,156 (tồn kho) / Có 331 (công nợ NCC) — hoặc Nợ 152/156 / Có 111,112 nếu trả tiền ngay
    // Transaction: InventoryService tự wrap (ghi nhận tồn kho + bút toán)
    // Rủi ro: addon_costs (vận chuyển, bảo hiểm) phải phân bổ vào giá vốn. Sai đơn giá → sai giá xuất sau này
    // Quy trình: Nhập kho → cập nhật inventory_layers (FIFO) hoặc tính lại giá BQGQ
    // Tích hợp: ApController.recordInvoice ghi nhận hóa đơn mua đồng thời
    public function receive(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'], $data['unit_price'])) {
            JsonResponse::error('Vui lòng nhập mã vật tư, số lượng và đơn giá');
            return;
        }
        $qty = (float)$data['qty'];
        $unitPrice = (float)$data['unit_price'];
        if ($qty <= 0 || $unitPrice <= 0) {
            JsonResponse::error('Số lượng và đơn giá phải lớn hơn 0');
            return;
        }
        try {
            $result = $this->inventory->receiveGoods(
                $data['item_id'], $qty, $unitPrice,
                $data['addon_costs'] ?? [],
                $data['reference'] ?? uniqid('recv_'),
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

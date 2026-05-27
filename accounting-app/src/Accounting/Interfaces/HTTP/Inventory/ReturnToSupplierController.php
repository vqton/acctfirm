<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

/**
 * MODULE: Trả lại Hàng cho Nhà cung cấp (Return to Supplier)
 *
 * Mục đích nghiệp vụ:
 *   - Xử lý hàng mua bị trả lại cho nhà cung cấp
 *   - Giảm tồn kho tương ứng
 *   - Điều chỉnh giảm công nợ phải trả (331)
 *   - Điều chỉnh giảm thuế GTGT đầu vào (1331) nếu đã kê khai
 *
 * API endpoints:
 *   GET  /api/supplier-returns       — Danh sách hàng trả
 *   POST /api/supplier-returns       — Ghi nhận trả hàng
 *   GET  /api/supplier-returns/items — Danh sách item để chọn
 *
 * Rủi ro:
 *   - Trả hàng nhưng không điều chỉnh công nợ → sai số dư 331
 *   - Không điều chỉnh thuế GTGT → sai chỉ tiêu thuế
 *   - Sai giá vốn trả lại (nếu đã xuất kho)
 *
 * Tích hợp:
 *   - InventoryService.returnToSupplier ghi bút toán Nợ 331 / Có 152, 156
 *   - ApController xử lý điều chỉnh hóa đơn mua hàng
 *   - Cần đồng bộ với kê khai thuế GTGT
 */
class ReturnToSupplierController
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
        $stmt = $pdo->query("SELECT sr.*, i.code as item_code, i.name as item_name
            FROM supplier_returns sr JOIN items i ON i.id = sr.item_id ORDER BY sr.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Trả hàng cho nhà cung cấp — giảm tồn kho + giảm công nợ 331
    // Input: { item_id, qty, reference?, created_by? }
    // Output: { return_id, item_id, qty } — 201 Created
    // Service: InventoryService.returnToSupplier() → JournalService.postEntry
    // Hạch toán: Nợ 331 / Có 152,156 (giảm tồn kho) + Có 1331 (giảm thuế VAT đầu vào)
    // Rủi ro: Cần điều chỉnh thuế GTGT đầu vào (1331) nếu đã kê khai. Sai giá vốn trả lại
    // Tích hợp: ApController.returnGoods xử lý side công nợ
    public function return(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'])) {
            JsonResponse::error('Vui lòng nhập mã vật tư và số lượng');
            return;
        }
        $qty = (float)$data['qty'];
        if ($qty <= 0) {
            JsonResponse::error('Số lượng phải lớn hơn 0');
            return;
        }
        try {
            $result = $this->inventory->returnToSupplier(
                $data['item_id'], $qty,
                $data['reference'] ?? uniqid('sret_'),
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
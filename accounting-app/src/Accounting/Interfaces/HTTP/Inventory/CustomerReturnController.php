<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

/**
 * MODULE: Hàng bán trả lại (Customer Return)
 *
 * Mục đích nghiệp vụ:
 *   - Xử lý hàng hóa bị khách hàng trả lại
 *   - Nhập kho hàng trả lại (tăng tồn kho)
 *   - Điều chỉnh giảm doanh thu (511) và giảm giá vốn (632)
 *   - Điều chỉnh công nợ phải thu (131)
 *
 * API endpoints:
 *   GET  /api/customer-returns       — Danh sách hàng trả lại
 *   POST /api/customer-returns       — Ghi nhận hàng trả lại
 *
 * Rủi ro:
 *   - Trả lại không đúng lô hàng → sai giá vốn (FIFO/Weighted Average)
 *   - Không điều chỉnh thuế GTGT (3331) nếu đã xuất hóa đơn
 *   - Ảnh hưởng BC02: giảm doanh thu và giá vốn
 *
 * Tích hợp:
 *   - InventoryService.returnFromCustomer để nhập kho và ghi nhận bút toán
 *   - ArController xử lý điều chỉnh công nợ phải thu
 *   - ItemRepository kiểm tra item tồn tại trước khi xử lý
 */
class CustomerReturnController
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
            FROM transactions t WHERE t.description LIKE 'Customer return:%' ORDER BY t.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Ghi nhận hàng bán trả lại từ khách hàng — nhập kho + điều chỉnh doanh thu
    // Input: { item_id, qty, reference?, created_by? }
    // Output: { transaction_id, item_id, qty } — 201 Created
    // Service: InventoryService.returnFromCustomer() → JournalService.postEntry
    // Hạch toán: Nợ 152,156 (tăng tồn kho) / Có 632 (giảm giá vốn) + Nợ 511 (giảm doanh thu) / Có 131
    // Rủi ro: Giá nhập kho lại phải đúng giá vốn tại thời điểm xuất (FIFO). Ảnh hưởng BC02
    // Tích hợp: ArController.returnGoods xử lý điều chỉnh công nợ
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
            $result = $this->inventory->returnFromCustomer(
                $data['item_id'], $qty,
                $data['reference'] ?? uniqid('ret_'),
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

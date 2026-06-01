<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

/**
 * MODULE: Xuất kho (Goods Issue)
 *
 * Mục đích nghiệp vụ:
 *   - Ghi nhận xuất kho cho các mục đích: bán hàng, sản xuất, nội bộ
 *   - Hạch toán giá vốn (632) theo phương pháp tính giá (FIFO/Bình quân)
 *   - Giảm tồn kho tương ứng
 *   - Phân loại xuất theo issue_type (sale, production, internal)
 *
 * API endpoints:
 *   GET  /api/inventory/issues       — Danh sách phiếu xuất
 *   POST /api/inventory/issues       — Tạo phiếu xuất mới
 *
 * Rủi ro:
 *   - R007: Xuất kho nhưng không ghi nhận bút toán → sai tồn kho
 *   - Sai phương pháp tính giá → sai giá vốn (632) → sai BC02
 *   - Xuất vượt quá tồn kho hiện có → số âm
 *   - Không ghi nhận kịp thời → sai báo cáo tồn kho
 *
 * Tích hợp:
 *   - InventoryService.issueGoods ghi Nợ 632 / Có 152,156
 *   - ArController ghi nhận doanh thu đồng thời với xuất bán
 *   - Production module (tương lai) sẽ gọi cho xuất NVL
 */
class IssueController
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
            FROM transactions t WHERE t.description LIKE 'Goods issue:%' ORDER BY t.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Ghi nhận xuất kho (phiếu xuất kho — PXK)
    // Input: { item_id, qty, issue_type (sale|production|internal), reference?, created_by? }
    // Output: { transaction_id, item_id, qty, unit_cost (từ FIFO/BQGQ), total_cost } — 201 Created
    // Service: InventoryService.issueGoods() → JournalService.postEntry
    // Hạch toán: Nợ 632 (giá vốn) / Có 152,156 (tồn kho giảm)
    // Tính giá: Dùng phương pháp đã gán cho item (FIFO hoặc BQGQ) — xem ValuationMethodController
    // Rủi ro: Xuất vượt tồn kho → InvalidArgumentException. Sai đơn giá xuất → sai 632 → sai BC02
    // Tích hợp: ArController ghi nhận doanh thu đồng thời khi issue_type=sale
    public function issue(): void
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

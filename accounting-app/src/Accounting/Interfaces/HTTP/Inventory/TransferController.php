<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Domain\Repository\WarehouseRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Chuyển kho (Inventory Transfer)
 *
 * Mục đích nghiệp vụ:
 *   - Chuyển hàng hóa giữa các kho trong cùng doanh nghiệp
 *   - Xuất kho nguồn (giảm tồn) và nhập kho đích (tăng tồn)
 *   - Không ảnh hưởng bút toán kế toán (nội bộ, cùng đơn vị)
 *   - Hỗ trợ chuyển một phần hoặc toàn bộ lô hàng
 *
 * API endpoints:
 *   GET  /api/transfers       — Danh sách chuyển kho
 *   POST /api/transfers       — Tạo phiếu chuyển kho mới
 *
 * Rủi ro:
 *   - R007: Xuất kho nguồn nhưng không nhập kho đích → mất hàng
 *   - Chuyển kho giữa đơn vị hạch toán khác nhau cần bút toán điều chỉnh
 *   - Sai kho đích → sai số dư chi tiết theo kho
 *
 * Tích hợp:
 *   - InventoryService.transferGoods xử lý đồng thời xuất+nnhập
 *   - WarehouseRepository kiểm tra kho tồn tại
 *   - Nội bộ, không qua JournalService (trừ trường hợp khác đơn vị)
 */
class TransferController
{
    private InventoryServiceInterface $inventory;
    private ItemRepositoryInterface $itemRepo;
    private WarehouseRepositoryInterface $warehouseRepo;
    private \PDO $pdo;

    public function __construct(
        InventoryServiceInterface $inventory,
        ItemRepositoryInterface $itemRepo,
        WarehouseRepositoryInterface $warehouseRepo,
        \PDO $pdo
    ) {
        $this->inventory = $inventory;
        $this->itemRepo = $itemRepo;
        $this->warehouseRepo = $warehouseRepo;
        $this->pdo = $pdo;
    }

    public function list(): void
    {
        // Return list of transfers from transactions with "Transfer:" prefix
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT t.id, t.description, t.reference, t.status, t.created_at
            FROM transactions t WHERE t.description LIKE 'Transfer:%' ORDER BY t.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Chuyển kho nội bộ — xuất kho nguồn, nhập kho đích
    // Input: { item_id, qty, from_warehouse_id?, to_warehouse_id, reference?, created_by? }
    // Output: { transfer_id, item_id, qty, from_warehouse, to_warehouse } — 201 Created
    // Service: InventoryService.transferGoods() — xử lý đồng thời 2 side
    // Hạch toán: Nội bộ, không ghi bút toán kế toán (cùng đơn vị hạch toán)
    // Rủi ro: R007 — Xuất kho nguồn nhưng nhập kho đích thất bại → mất hàng (transaction rollback)
    // Tích hợp: Nếu khác đơn vị hạch toán → cần qua JournalService
    public function transfer(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'], $data['to_warehouse_id'])) {
            JsonResponse::error('Vui lòng nhập mã vật tư, số lượng và kho đích');
            return;
        }

        $fromWarehouseId = $data['from_warehouse_id'] ?? null;
        $toWarehouseId = $data['to_warehouse_id'];
        $qty = (float)$data['qty'];

        if ($qty <= 0) {
            JsonResponse::error('Số lượng phải lớn hơn 0');
            return;
        }

        try {
            $result = $this->inventory->transferGoods(
                $data['item_id'],
                $qty,
                $fromWarehouseId,
                $toWarehouseId,
                $data['reference'] ?? uniqid('trf_'),
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

    public function warehouses(): void
    {
        JsonResponse::ok(array_map(fn($x) => $x->toArray(), $this->warehouseRepo->findAll()));
    }

    private function getPdo(): \PDO
    {
        return $this->pdo;
    }
}

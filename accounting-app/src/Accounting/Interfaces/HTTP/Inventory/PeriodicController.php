<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Kiểm kê Định kỳ (Periodic Inventory)
 *
 * Mục đích nghiệp vụ:
 *   - Ghi nhận kết quả kiểm kê định kỳ cuối tháng/cuối quý
 *   - Điều chỉnh tồn kho thực tế so với sổ sách
 *   - Cập nhật đơn giá tồn kho cuối kỳ (closing unit cost)
 *   - Hỗ trợ phương pháp kiểm kê định kỳ (periodic inventory system)
 *
 * API endpoints:
 *   GET  /api/periodic-inventory       — Danh sách kiểm kê
 *   POST /api/periodic-inventory       — Ghi nhận kết quả kiểm kê
 *
 * Rủi ro:
 *   - Chênh lệch kiểm kê không được xử lý → sai tồn kho sổ sách
 *   - Điều chỉnh sai → ảnh hưởng giá vốn (632) và BC02
 *   - Cần phân biệt thừa/thiếu để xử lý khác nhau
 *
 * Tích hợp:
 *   - InventoryService.closePeriodicInventory xử lý chênh lệch
 *   - PhysicalCountController quản lý phiên kiểm kê thực tế
 *   - Kết quả kiểm kê là cơ sở để lập BC01 khoản mục hàng tồn kho
 */
class PeriodicController
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
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT p.*, i.code as item_code, i.name as item_name FROM periodic_inventory p JOIN items i ON i.id = p.item_id ORDER BY p.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Kiểm kê định kỳ cuối kỳ — ghi nhận số lượng và đơn giá tồn cuối kỳ
    // Input: { item_id, closing_qty, closing_unit_cost, reference?, created_by? }
    // Output: { period_id, item_id, old_qty, closing_qty, difference_qty, adjustment_entry } — 201 Created
    // Service: InventoryService.closePeriodicInventory() — so sánh sổ sách vs thực tế
    // Hạch toán điều chỉnh: Thừa: Nợ 152,156 / Có 711. Thiếu: Nợ 632 / Có 152,156
    // Rủi ro: Sai closing_unit_cost → sai định giá hàng tồn kho → sai BC01 và BC02
    // Tích hợp: PhysicalCountController có thể cung cấp số liệu thực tế
    public function close(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['closing_qty'], $data['closing_unit_cost'])) {
            JsonResponse::error('Vui lòng nhập mã vật tư, số lượng tồn cuối và đơn giá cuối kỳ');
            return;
        }
        try {
            $result = $this->inventory->closePeriodicInventory(
                $data['item_id'], (float)$data['closing_qty'], (float)$data['closing_unit_cost'],
                $data['reference'] ?? uniqid('prd_'), $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    private function getPdo(): \PDO
    {
        return $this->pdo;
    }
}

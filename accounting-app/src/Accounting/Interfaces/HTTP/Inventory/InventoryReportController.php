<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Báo cáo Tồn kho (Inventory Reports)
 *
 * Mục đích nghiệp vụ:
 *   - Báo cáo tồn kho chi tiết theo từng mặt hàng
 *   - Lịch sử nhập xuất tồn
 *   - Cảnh báo hàng tồn tối thiểu
 *
 * API endpoints:
 *   GET /api/inventory/reports/stock — Báo cáo tồn kho
 *   GET /api/inventory/reports/movement — Lịch sử nhập xuất
 *   GET /api/inventory/reports/low-stock — Hàng sắp hết
 *
 * Rủi ro:
 *   - Số liệu không chính xác nếu tính cả draft
 *
 * Tích hợp:
 *   - InventoryService đọc từ items + ledger_entries
 */
class InventoryReportController
{
    private InventoryServiceInterface $inventory;

    public function __construct(InventoryServiceInterface $inventory) { $this->inventory = $inventory; }

    /**
     * Báo cáo tồn kho hiện tại
     *
     * @return void
     */
    public function stock(): void
    {
        Auth::requirePermission('inventory', 'read');
        $warehouseId = $_GET['warehouse_id'] ?? null;
        JsonResponse::ok($this->inventory->getStockReport($warehouseId));
    }

    /**
     * Lịch sử nhập xuất tồn kho
     *
     * @return void
     */
    public function movement(): void
    {
        Auth::requirePermission('inventory', 'read');
        $itemId = $_GET['item_id'] ?? '';
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        if (!$itemId) { JsonResponse::error('Vui lòng nhập mã hàng', 400); return; }
        JsonResponse::ok($this->inventory->getMovementHistory($itemId, $from, $to));
    }

    /**
     * Danh sách hàng tồn dưới mức tối thiểu
     *
     * @return void
     */
    public function lowStock(): void
    {
        Auth::requirePermission('inventory', 'read');
        JsonResponse::ok($this->inventory->getLowStockItems());
    }
}

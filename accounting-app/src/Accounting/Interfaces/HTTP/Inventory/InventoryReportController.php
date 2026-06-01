<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

/**
 * MODULE: Báo cáo Hàng tồn kho
 *
 * Mục đích nghiệp vụ:
 *   - Báo cáo tuổi tồn kho (aging) — phân tích hàng tồn lâu ngày
 *   - Báo cáo vòng quay hàng tồn kho (turnover ratio)
 *   - Báo cáo định giá hàng tồn kho (valuation)
 *   - Hỗ trợ filter theo item, kho, kỳ
 *
 * API endpoints:
 *   GET /api/inventory-reports/aging      — Tuổi tồn kho
 *   GET /api/inventory-reports/turnover   — Vòng quay hàng tồn kho
 *   GET /api/inventory-reports/valuation  — Định giá hàng tồn kho
 *
 * Rủi ro:
 *   - Aging sai nếu không cập nhật nhập/xuất đầy đủ
 *   - Turnover bị ảnh hưởng nếu giá vốn (632) không chính xác
 *   - Valuation phụ thuộc phương pháp tính giá (FIFO/Bình quân)
 *
 * Tích hợp:
 *   - InventoryService cung cấp dữ liệu từ ItemRepository + inventory_layers
 *   - Số liệu valuation dùng cho BCTC (BC01 — hàng tồn kho)
 *   - Báo cáo aging dùng để trích lập dự phòng (ImpairmentController)
 */
class InventoryReportController
{
    private InventoryServiceInterface $inventory;

    public function __construct(InventoryServiceInterface $inventory)
    {
        $this->inventory = $inventory;
    }

    public function aging(): void
    {
        $itemId = $_GET['item_id'] ?? null;
        $warehouseId = $_GET['warehouse_id'] ?? null;
        JsonResponse::ok($this->inventory->getAgingReport($itemId, $warehouseId));
    }

    public function turnover(): void
    {
        $start = $_GET['period_start'] ?? date('Y-m-01');
        $end = $_GET['period_end'] ?? date('Y-m-t');
        $itemId = $_GET['item_id'] ?? null;
        JsonResponse::ok($this->inventory->getTurnoverRatio($start, $end, $itemId));
    }

    public function valuation(): void
    {
        $itemId = $_GET['item_id'] ?? null;
        $warehouseId = $_GET['warehouse_id'] ?? null;
        $start = $_GET['period_start'] ?? null;
        $end = $_GET['period_end'] ?? null;
        JsonResponse::ok($this->inventory->getValuationReport($itemId, $warehouseId, $start, $end));
    }
}
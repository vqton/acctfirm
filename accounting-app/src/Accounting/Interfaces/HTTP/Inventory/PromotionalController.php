<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Hàng khuyến mại (Promotional Goods)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý hàng khuyến mại, hàng tặng kèm
 *   - Xuất kho hàng khuyến mại
 *   - Hạch toán chi phí khuyến mại (TK 641)
 *
 * API endpoints:
 *   POST /api/inventory/promotional — Xuất hàng KM
 *   GET  /api/inventory/promotional — Danh sách
 *
 * Rủi ro:
 *   - Sai TK hạch toán (641 thay vì 632)
 *   - Vi phạm quy định thuế về hàng KM
 *
 * Tích hợp:
 *   - InventoryService gọi JournalService
 */
class PromotionalController
{
    private InventoryServiceInterface $inventory;

    public function __construct(InventoryServiceInterface $inventory) { $this->inventory = $inventory; }

    /**
     * Xuất hàng khuyến mại
     *
     * @return void
     */
    public function issue(): void
    {
        Auth::requirePermission('inventory', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'])) {
            JsonResponse::error('Vui lòng nhập mã hàng và số lượng', 400);
            return;
        }
        try {
            $result = $this->inventory->issuePromotional($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Danh sách hàng khuyến mại đã xuất
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('inventory', 'read');
        JsonResponse::ok($this->inventory->getPromotionalIssues());
    }
}

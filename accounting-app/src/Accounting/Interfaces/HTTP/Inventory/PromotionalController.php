<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Hàng Khuyến mại (Promotional Goods)
 *
 * Mục đích nghiệp vụ:
 *   - Xuất hàng khuyến mại, quà tặng cho khách hàng
 *   - Hạch toán chi phí khuyến mại (641 — Chi phí bán hàng)
 *   - Giảm tồn kho tương ứng
 *   - Tuân thủ quy định về thuế GTGT đối với hàng khuyến mại
 *
 * API endpoints:
 *   POST /api/promotional/issue — Xuất hàng khuyến mại
 *
 * Rủi ro:
 *   - Không xuất hóa đơn cho hàng KM → vi phạm luật thuế GTGT
 *   - Sai TK đối ứng (632 thay vì 641) → sai BC02
 *   - Hàng KM vượt định mức → không được khấu trừ thuế
 *
 * Tích hợp:
 *   - InventoryService.issuePromotional ghi Nợ 641 / Có 152,156
 *   - IssueController xử lý xuất kho thông thường (632)
 *   - Cần integrate với module thuế để xử lý GTGT hàng KM
 */
class PromotionalController
{
    private InventoryService $inventory;
    private ItemRepositoryInterface $itemRepo;

    public function __construct(InventoryService $inventory, ItemRepositoryInterface $itemRepo)
    {
        $this->inventory = $inventory;
        $this->itemRepo = $itemRepo;
    }

    // NGHIỆP VỤ: Xuất hàng khuyến mại, quà tặng — ghi nhận chi phí bán hàng
    // Input: { item_id, qty, reference?, created_by? }
    // Output: { transaction_id, item_id, qty, total_cost } — 201 Created
    // Service: InventoryService.issuePromotional() → JournalService.postEntry
    // Hạch toán: Nợ 641 (chi phí bán hàng) / Có 152,156 (giảm tồn kho)
    // Rủi ro: TK đối ứng 641 (chi phí bán hàng), KHÔNG phải 632 (giá vốn). Sai TK → sai BC02
    // Thuế: Hàng KM phải xuất hóa đơn và chịu thuế GTGT theo quy định
    public function issue(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'])) {
            JsonResponse::error('item_id, qty required');
            return;
        }
        try {
            $result = $this->inventory->issuePromotional(
                $data['item_id'], (float)$data['qty'],
                $data['reference'] ?? uniqid('promo_'), $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }
}

<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryTransitServiceInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Hàng đi đường (Inventory in Transit)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý hàng mua đang đi đường (TK 151)
 *   - Ghi nhận khi mua hàng chưa về nhập kho
 *   - Kết chuyển 151 -> 152,156 khi hàng về
 *
 * API endpoints:
 *   GET  /api/inventory/transit — Danh sách
 *   POST /api/inventory/transit — Ghi nhận mới
 *   POST /api/inventory/transit/{id}/receive — Xác nhận hàng về
 *
 * Rủi ro:
 *   - Hàng đi đường không theo dõi -> mất hàng
 *   - Sai hạch toán 151/152/156
 *
 * Tích hợp:
 *   - InventoryTransitService gọi JournalService
 *   - ReceiptController khi hàng về nhập kho
 */
class InventoryTransitController
{
    private InventoryTransitServiceInterface $transit;

    public function __construct(InventoryTransitServiceInterface $transit) { $this->transit = $transit; }

    /**
     * Danh sách hàng đang đi đường
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('inventory', 'read');
        JsonResponse::ok($this->transit->getTransitList());
    }

    /**
     * Ghi nhận hàng mua đang đi đường (TK 151)
     *
     * @return void
     */
    public function record(): void
    {
        Auth::requirePermission('inventory', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'])) {
            JsonResponse::error('Vui lòng nhập mã hàng và số lượng', 400);
            return;
        }
        try {
            $result = $this->transit->recordTransit($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Xác nhận hàng đã về nhập kho
     *
     * @param string $id Mã lô hàng đi đường
     * @return void
     */
    public function receive(string $id): void
    {
        Auth::requirePermission('inventory', 'create');
        Auth::checkCsrf();
        try {
            $result = $this->transit->receiveTransit($id, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }
}

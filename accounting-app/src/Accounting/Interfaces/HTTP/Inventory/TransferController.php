<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Chuyển kho (Inventory Transfer)
 *
 * Mục đích nghiệp vụ:
 *   - Chuyển hàng giữa các kho
 *   - Ghi nhận xuất kho nguồn + nhập kho đích
 *
 * API endpoints:
 *   POST /api/inventory/transfers — Tạo mới
 *   POST /api/inventory/transfers/{id}/confirm — Xác nhận
 *   GET  /api/inventory/transfers — Danh sách
 *
 * Rủi ro:
 *   - Mất hàng trong quá trình vận chuyển
 *   - Không ghi nhận đồng thời xuất/nhập
 *
 * Tích hợp:
 *   - InventoryService xử lý xuất + nhập
 *   - ReceiptController và IssueController
 */
class TransferController
{
    private InventoryServiceInterface $inventory;

    public function __construct(InventoryServiceInterface $inventory) { $this->inventory = $inventory; }

    /**
     * Tạo phiếu chuyển kho
     *
     * @return void
     */
    public function create(): void
    {
        Auth::requirePermission('inventory', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'], $data['from_warehouse'], $data['to_warehouse'])) {
            JsonResponse::error('Vui lòng nhập hàng, số lượng, kho nguồn và kho đích', 400);
            return;
        }
        try {
            $result = $this->inventory->transferStock($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Xác nhận chuyển kho hoàn tất
     *
     * @param string $id Mã phiếu chuyển
     * @return void
     */
    public function confirm(string $id): void
    {
        Auth::requirePermission('inventory', 'create');
        Auth::checkCsrf();
        try {
            $result = $this->inventory->confirmTransfer($id, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Danh sách phiếu chuyển kho
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('inventory', 'read');
        $status = $_GET['status'] ?? null;
        JsonResponse::ok($this->inventory->getTransferList($status));
    }
}

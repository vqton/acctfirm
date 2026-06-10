<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Xoá sổ hàng tồn kho (Write-off)
 *
 * Mục đích nghiệp vụ:
 *   - Xoá sổ hàng tồn kho hư hỏng, mất mát
 *   - Hạch toán vào chi phí (TK 632 hoặc 642)
 *
 * API endpoints:
 *   POST /api/inventory/write-off — Ghi nhận xoá sổ
 *   GET  /api/inventory/write-off — Danh sách
 *
 * Rủi ro:
 *   - Xoá sổ sai -> mất hàng nhưng không ghi nhận chi phí
 *
 * Tích hợp:
 *   - InventoryService gọi JournalService
 */
class WriteOffController
{
    private InventoryServiceInterface $inventory;

    public function __construct(InventoryServiceInterface $inventory) { $this->inventory = $inventory; }

    /**
     * Ghi nhận xoá sổ hàng tồn kho
     *
     * @return void
     */
    public function writeOff(): void
    {
        Auth::requirePermission('inventory', 'delete');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'])) {
            JsonResponse::error('Vui lòng nhập mã hàng và số lượng', 400);
            return;
        }
        try {
            $result = $this->inventory->writeOffStock($data, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Danh sách hàng đã xoá sổ
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('inventory', 'read');
        JsonResponse::ok($this->inventory->getWriteOffList());
    }
}

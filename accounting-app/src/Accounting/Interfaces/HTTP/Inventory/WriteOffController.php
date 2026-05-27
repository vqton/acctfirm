<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Service\InventoryService;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

/**
 * MODULE: Xóa sổ Hàng tồn kho (Write-Off)
 *
 * Mục đích nghiệp vụ:
 *   - Xóa sổ hàng tồn kho bị hư hỏng, mất mát, hết hạn sử dụng
 *   - Hạch toán chi phí xóa sổ (632 — Giá vốn hàng bán hoặc tài khoản chi phí khác)
 *   - Giảm tồn kho tương ứng
 *   - Phân loại lý do xóa sổ (hư hỏng, mất, hết hạn)
 *
 * API endpoints:
 *   GET  /api/write-offs       — Danh sách xóa sổ
 *   POST /api/write-offs       — Ghi nhận xóa sổ mới
 *
 * Rủi ro:
 *   - Xóa sổ không đúng lý do → sai chi phí (632 thay vì 642)
 *   - Mất audit trail → không trace được hàng đã xóa
 *   - Xóa sổ hàng có giá trị lớn cần phê duyệt (approval workflow)
 *   - Ảnh hưởng BC02: tăng giá vốn hoặc chi phí QLDN
 *
 * Tích hợp:
 *   - InventoryService.writeOffGoods ghi Nợ 632,642 / Có 152,156
 *   - Cần ApprovalController phê duyệt cho xóa sổ giá trị lớn
 *   - PhysicalCountController phát hiện hàng cần xóa qua kiểm kê
 */
class WriteOffController
{
    private InventoryService $inventory;
    private \PDO $pdo;

    public function __construct(InventoryService $inventory, \PDO $pdo)
    {
        $this->inventory = $inventory;
        $this->pdo = $pdo;
    }

    public function list(): void
    {
        $pdo = $this->pdo;
        $stmt = $pdo->query("SELECT wo.*, i.code as item_code, i.name as item_name
            FROM inventory_write_offs wo JOIN items i ON i.id = wo.item_id ORDER BY wo.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Xóa sổ hàng tồn kho hư hỏng/mất/hết hạn
    // Input: { item_id, qty, reason, expense_account? (default: 632), reference?, created_by?, notes? }
    // Output: { write_off_id, item_id, qty, total_value, journal_entry } — 201 Created
    // Service: InventoryService.writeOffGoods() → JournalService.postEntry
    // Hạch toán: Nợ 632,642 (expense_account) / Có 152,156 (giảm tồn kho)
    // Rủi ro: expense_account phải đúng bản chất: 632=giá vốn, 642=chi phí QLDN
    // Xóa giá trị lớn cần phê duyệt qua ApprovalController. Ảnh hưởng BC02
    // Audit trail: Lưu reason, notes, chứng từ phê duyệt
    public function writeOff(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'], $data['reason'])) {
            JsonResponse::error('Vui lòng nhập mã vật tư, số lượng và lý do xuất hủy');
            return;
        }
        $qty = (float)$data['qty'];
        if ($qty <= 0) {
            JsonResponse::error('Số lượng phải lớn hơn 0');
            return;
        }
        try {
            $result = $this->inventory->writeOffGoods(
                $data['item_id'], $qty, $data['reason'],
                $data['expense_account'] ?? '632',
                $data['reference'] ?? uniqid('wo_'),
                $data['created_by'] ?? 'system',
                $data['notes'] ?? ''
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }
}
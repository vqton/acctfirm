<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Dự phòng Giảm giá Hàng tồn kho (Impairment / Provision)
 *
 * Mục đích nghiệp vụ:
 *   - Ghi nhận dự phòng giảm giá hàng tồn kho (TK 2294)
 *   - Xử lý hàng tồn kho bị giảm giá trị (hàng chậm luân chuyển, lỗi mốt, hư hỏng)
 *   - Hoàn nhập dự phòng khi giá trị phục hồi
 *   - Tuân thủ chuẩn mực kế toán VAS 02 — Hàng tồn kho
 *
 * API endpoints:
 *   GET  /api/impairment         — Danh sách dự phòng
 *   POST /api/impairment         — Ghi nhận dự phòng mới
 *   POST /api/impairment/reverse — Hoàn nhập dự phòng
 *
 * Rủi ro:
 *   - Dự phòng sai → lợi nhuận BC02 sai → thuế TNDN sai
 *   - Hoàn nhập không đúng thời điểm → sai số dư 2294
 *   - Ảnh hưởng đến chỉ tiêu hàng tồn kho trên BC01
 *
 * Tích hợp:
 *   - InventoryService gọi JournalService ghi bút toán Nợ 632 / Có 2294
 *   - PhysicalCountController phát hiện hàng giảm giá qua kiểm kê
 *   - Ảnh hưởng BC02 chỉ tiêu giá vốn (632) và BC01 (2294)
 */
class ImpairmentController
{
    private InventoryServiceInterface $inventory;
    private \PDO $pdo;

    public function __construct(InventoryServiceInterface $inventory, \PDO $pdo)
    {
        $this->inventory = $inventory;
        $this->pdo = $pdo;
    }

    public function list(): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT ip.*, i.code as item_code, i.name as item_name FROM inventory_impairment ip JOIN items i ON i.id = ip.item_id ORDER BY ip.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Ghi nhận dự phòng giảm giá hàng tồn kho (VAS 02 — Hàng tồn kho)
    // Input: { item_id, amount, reference?, notes?, created_by? }
    // Output: { impairment_id, item_id, amount, journal_entry } — 201 Created
    // Service: InventoryService.recordImpairment() → JournalService.postEntry
    // Hạch toán: Nợ 632 (giá vốn hàng bán) / Có 2294 (dự phòng giảm giá HTK)
    // Rủi ro: Dự phòng sai → lợi nhuận BC02 sai → thuế TNDN sai
    // Hoàn nhập: Khi giá trị phục hồi → gọi reverse(). Hạch toán ngược lại
    // Ảnh hưởng BC01: Hàng tồn kho trình bày = nguyên giá - dự phòng (2294)
    public function record(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['amount'])) {
            JsonResponse::error('Vui lòng nhập mã vật tư và số tiền dự phòng');
            return;
        }
        try {
            $result = $this->inventory->recordImpairment(
                $data['item_id'], (float)$data['amount'],
                $data['reference'] ?? uniqid('imp_'), $data['notes'] ?? '',
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // NGHIỆP VỤ: Hoàn nhập dự phòng giảm giá hàng tồn kho — ghi giảm chi phí
    // Input: { impairment_id, amount, reference?, created_by? }
    // Output: { reversal_id, reduced_amount, journal_entry }
    // Service: InventoryService.reverseImpairment() → JournalService.postEntry
    // Hạch toán: Nợ 2294 (giảm dự phòng) / Có 632 (giảm giá vốn)
    // Rủi ro: Hoàn nhập vượt quá số dự phòng đã trích → không hợp lệ
    // Ảnh hưởng BC02: Giảm giá vốn (632) → tăng lợi nhuận gộp
    public function reverse(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['impairment_id'], $data['amount'])) {
            JsonResponse::error('Vui lòng nhập mã dự phòng và số tiền');
            return;
        }
        try {
            $result = $this->inventory->reverseImpairment(
                $data['impairment_id'], (float)$data['amount'],
                $data['reference'] ?? uniqid('rev_'), $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    private function getPdo(): \PDO
    {
        return $this->pdo;
    }
}

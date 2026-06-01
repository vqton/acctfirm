<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Kiểm kê Thực tế (Physical Count)
 *
 * Mục đích nghiệp vụ:
 *   - Tạo phiên kiểm kê (count session) để đếm hàng thực tế
 *   - Ghi nhận kết quả kiểm từng item trong phiên
 *   - So sánh số lượng thực tế với sổ sách
 *   - Tạo bút toán điều chỉnh chênh lệch (thừa/thiếu)
 *
 * API endpoints:
 *   GET    /api/physical-count/sessions       — Danh sách phiên kiểm kê
 *   GET    /api/physical-count/sessions/{id}  — Chi tiết phiên (các dòng)
 *   POST   /api/physical-count/sessions       — Tạo phiên mới
 *   POST   /api/physical-count/adjust         — Điều chỉnh sau kiểm kê
 *
 * Rủi ro:
 *   - Chênh lệch không được điều chỉnh → sai tồn kho BC01
 *   - Kiểm kê không đầy đủ → không phát hiện mất mát
 *   - Điều chỉnh sai (thừa thành thiếu) → ảnh hưởng giá vốn
 *
 * Tích hợp:
 *   - InventoryService.createCountSession khởi tạo phiên
 *   - Adjust gọi JournalService ghi bút toán điều chỉnh
 *   - PeriodicController xử lý kiểm kê định kỳ cuối kỳ
 */
class PhysicalCountController
{
    private InventoryServiceInterface $inventory;
    private ItemRepositoryInterface $itemRepo;
    private \PDO $pdo;

    public function __construct(InventoryServiceInterface $inventory, ItemRepositoryInterface $itemRepo, \PDO $pdo)
    {
        $this->inventory = $inventory;
        $this->itemRepo = $itemRepo;
        $this->pdo = $pdo;
    }

    public function sessions(): void
    {
        $pdo = $this->getPdo();
        JsonResponse::ok($pdo->query("SELECT * FROM inventory_count_sessions ORDER BY created_at DESC")->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function lines(string $sessionId): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT cl.*, i.code as item_code, i.name as item_name FROM inventory_count_lines cl JOIN items i ON i.id = cl.item_id WHERE cl.session_id = ? ORDER BY cl.created_at");
        $stmt->execute([$sessionId]);
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Tạo phiên kiểm kê thực tế — ghi nhận số lượng đếm thực tế từng item
    // Input: { lines: [{item_id, actual_qty, notes?}...], reference?, notes?, created_by? }
    // Output: { session_id, lines_count, status: 'open' } — 201 Created
    // Service: InventoryService.createCountSession()
    // Quy trình: Tạo phiên → nhập kết quả kiểm (từng dòng trong lines) → so sánh với sổ sách → adjust nếu lệch
    // Rủi ro: Kiểm kê không đầy đủ → không phát hiện mất mát. Sai số liệu nhập → adjust sai
    // Tích hợp: PhysicalCountController.adjust tạo bút toán điều chỉnh chênh lệch
    public function createSession(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['lines']) || count($data['lines']) === 0) {
            JsonResponse::error('Vui lòng nhập danh sách kiểm kê');
            return;
        }
        try {
            $result = $this->inventory->createCountSession(
                $data['lines'], $data['reference'] ?? uniqid('cnt_'),
                $data['notes'] ?? '', $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // NGHIỆP VỤ: Điều chỉnh tồn kho sau kiểm kê — ghi nhận chênh lệch thừa/thiếu
    // Input: { item_id, actual_qty, reference?, created_by? }
    // Output: { adjustment_id, difference_qty, journal_entry }
    // Service: InventoryService.adjustPhysicalCount() → JournalService.postEntry
    // Hạch toán: Thừa: Nợ 152,156 / Có 711 (thu nhập khác). Thiếu: Nợ 632 / Có 152,156
    // Rủi ro: R007 — Điều chỉnh sai chiều (thừa→632, thiếu→711) → sai BC02
    // Ảnh hưởng BC01: Thay đổi giá trị hàng tồn kho
    public function adjust(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['actual_qty'])) {
            JsonResponse::error('Vui lòng nhập mã vật tư và số lượng thực tế');
            return;
        }
        try {
            $result = $this->inventory->adjustPhysicalCount(
                $data['item_id'], (float)$data['actual_qty'],
                $data['reference'] ?? uniqid('adj_'), $data['created_by'] ?? 'system'
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

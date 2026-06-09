<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Domain\Service\GoodsIssueService;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

/**
 * MODULE: Xuất kho (Goods Issue)
 *
 * Mục đích nghiệp vụ:
 *   - Ghi nhận xuất kho cho các mục đích: bán hàng, sản xuất, nội bộ
 *   - Hạch toán giá vốn (632) theo phương pháp tính giá (FIFO/Bình quân)
 *   - Giảm tồn kho tương ứng
 *   - Phân loại xuất theo issue_type (sale, production, internal)
 *
 * API endpoints:
 *   GET  /api/inventory/issues       — Danh sách phiếu xuất
 *   POST /api/inventory/issues       — Tạo phiếu xuất mới
 *
 * Rủi ro:
 *   - R007: Xuất kho nhưng không ghi nhận bút toán → sai tồn kho
 *   - Sai phương pháp tính giá → sai giá vốn (632) → sai BC02
 *   - Xuất vượt quá tồn kho hiện có → số âm
 *   - Không ghi nhận kịp thời → sai báo cáo tồn kho
 *
 * Tích hợp:
 *   - InventoryService.issueGoods ghi Nợ 632 / Có 152,156
 *   - ArController ghi nhận doanh thu đồng thời với xuất bán
 *   - Production module (tương lai) sẽ gọi cho xuất NVL
 */
class IssueController
{
    private InventoryServiceInterface $inventory;
    private ItemRepositoryInterface $itemRepo;
    private \PDO $pdo;
    private ?GoodsIssueService $goodsIssueService;

    public function __construct(
        InventoryServiceInterface $inventory,
        ItemRepositoryInterface $itemRepo,
        \PDO $pdo,
        ?GoodsIssueService $goodsIssueService = null
    ) {
        $this->inventory = $inventory;
        $this->itemRepo = $itemRepo;
        $this->pdo = $pdo;
        $this->goodsIssueService = $goodsIssueService;
    }

    public function list(): void
    {
        $pdo = $this->pdo;
        $stmt = $pdo->query("SELECT t.id, t.description, t.reference, t.status, t.created_at
            FROM transactions t WHERE t.description LIKE 'Goods issue:%' ORDER BY t.created_at DESC");
        JsonResponse::ok($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // NGHIỆP VỤ: Ghi nhận xuất kho (phiếu xuất kho — PXK)
    // Input: { item_id, qty, issue_type (sale|production|internal), reference?, created_by? }
    // Output: { transaction_id, item_id, qty, unit_cost (từ FIFO/BQGQ), total_cost } — 201 Created
    // Service: InventoryService.issueGoods() → JournalService.postEntry
    // Hạch toán: Nợ 632 (giá vốn) / Có 152,156 (tồn kho giảm)
    // Tính giá: Dùng phương pháp đã gán cho item (FIFO hoặc BQGQ) — xem ValuationMethodController
    // Rủi ro: Xuất vượt tồn kho → InvalidArgumentException. Sai đơn giá xuất → sai 632 → sai BC02
    // Tích hợp: ArController ghi nhận doanh thu đồng thời khi issue_type=sale
    public function issue(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['item_id'], $data['qty'])) {
            JsonResponse::error('Vui lòng nhập mã vật tư và số lượng');
            return;
        }
        $qty = (float)$data['qty'];
        if ($qty <= 0) {
            JsonResponse::error('Số lượng phải lớn hơn 0');
            return;
        }
        try {
            $result = $this->inventory->issueGoods(
                $data['item_id'], $qty,
                $data['issue_type'] ?? 'sale',
                $data['reference'] ?? uniqid('iss_'),
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    public function items(): void
    {
        JsonResponse::ok(array_map(fn($x) => $x->toArray(), $this->itemRepo->findAll()));
    }

    // NGHIỆP VỤ: Kiểm tra tồn kho realtime cho 1 mặt hàng
    // Output: { item_id, name, stock_qty, unit, allow_negative }
    public function stockCheck(string $itemId): void
    {
        Auth::requirePermission('inventory', 'read');
        $stmt = $this->pdo->prepare(
            "SELECT id, name, stock_qty, unit, allow_negative_stock FROM items WHERE id = ?"
        );
        $stmt->execute([$itemId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            JsonResponse::error('Không tìm thấy mặt hàng');
            return;
        }
        JsonResponse::ok([
            'item_id' => $row['id'],
            'name' => $row['name'],
            'stock_qty' => (float)$row['stock_qty'],
            'unit' => $row['unit'],
            'allow_negative' => (bool)$row['allow_negative_stock'],
        ]);
    }

    // NGHIỆP VỤ: Tạo PXK dạng nháp (multi-line) — Mẫu 02-VT
    // Input: { issue_date, warehouse_id, receiver_name, receiver_department, issue_reason,
    //          issue_type, lines: [{ item_id, requested_qty, actual_qty }], notes }
    // Output: GoodsIssue với status=draft
    public function createDraft(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        if (!$this->goodsIssueService) {
            JsonResponse::error('GoodsIssueService not available');
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['lines'])) {
            JsonResponse::error('Vui lòng nhập danh sách vật tư cần xuất');
            return;
        }
        $data['created_by'] = $_SESSION['user_id'] ?? 'system';
        try {
            $result = $this->goodsIssueService->createDraft($data);
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // NGHIỆP VỤ: Ghi sổ PXK (từ draft → posted, tạo bút toán + giảm tồn kho)
    public function postDraft(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        if (!$this->goodsIssueService) {
            JsonResponse::error('GoodsIssueService not available');
            return;
        }
        $createdBy = $_SESSION['user_id'] ?? 'system';
        try {
            $result = $this->goodsIssueService->postIssue($id, $createdBy);
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // NGHIỆP VỤ: Hủy PXK (chỉ khi draft)
    public function cancelDraft(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        if (!$this->goodsIssueService) {
            JsonResponse::error('GoodsIssueService not available');
            return;
        }
        $cancelledBy = $_SESSION['user_id'] ?? 'system';
        try {
            $result = $this->goodsIssueService->cancelIssue($id, $cancelledBy);
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // NGHIỆP VỤ: Lấy chi tiết PXK (kèm line items)
    public function getDetail(string $id): void
    {
        Auth::requirePermission('inventory', 'read');
        if (!$this->goodsIssueService) {
            JsonResponse::error('GoodsIssueService not available');
            return;
        }
        try {
            $result = $this->goodsIssueService->getIssue($id);
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    // NGHIỆP VỤ: Danh sách PXK từ GoodsIssueService (Mẫu 02-VT)
    public function listIssues(): void
    {
        Auth::requirePermission('inventory', 'read');
        if (!$this->goodsIssueService) {
            JsonResponse::error('GoodsIssueService not available');
            return;
        }
        $status = $_GET['status'] ?? null;
        $limit = (int)($_GET['limit'] ?? 50);
        JsonResponse::ok($this->goodsIssueService->listIssues($status, $limit));
    }
}

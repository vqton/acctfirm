<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\IntercompanyService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Giao dịch Nội bộ / Liên công ty (Intercompany)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý giao dịch giữa các đơn vị/ công ty trong cùng tập đoàn
 *   - Đối chiếu số dư nội bộ (intercompany matching)
 *   - Loại trừ giao dịch nội bộ khi lập BCTC hợp nhất
 *   - Báo cáo tổng hợp sau loại trừ
 *
 * API endpoints:
 *   GET  /api/intercompany/entities         — Danh sách entity
 *   GET  /api/intercompany/match/{entityId} — Đối chiếu IC
 *   POST /api/intercompany/eliminate/{entityId} — Loại trừ IC
 *   GET  /api/intercompany/consolidated     — Báo cáo hợp nhất
 *   GET  /api/intercompany/view             — View HTML
 *
 * Rủi ro:
 *   - R007: Loại trừ IC không đúng → BCTC hợp nhất sai
 *   - Số dư nội bộ không khớp giữa các đơn vị → không loại trừ được
 *   - Giao dịch IC chưa được ghi nhận ở cả 2 bên → chênh lệch
 *   - Loại trừ không hoàn toàn → lãi/lỗ nội bộ còn sót
 *
 * Tích hợp:
 *   - IntercompanyService gọi JournalService cho bút toán loại trừ
 *   - Cần số dư từ tất cả module (AP, AR, Cash, Inventory)
 *   - Kết quả ảnh hưởng BC01 hợp nhất (loại trừ 131/331 nội bộ)
 */
class IntercompanyController
{
    private IntercompanyService $ic;

    public function __construct(IntercompanyService $ic) { $this->ic = $ic; }

    // Danh sách entity
    public function entities(): void
    {
        JsonResponse::ok($this->ic->getEntities());
    }

    // NGHIỆP VỤ: Đối chiếu giao dịch nội bộ cho một entity/thành viên tập đoàn
    // Input: entityId, GET ?period=2025-01
    // Output: { matched: [...], unmatched: [...], differences: [...], total_ic_receivable, total_ic_payable }
    // Service: IntercompanyService.matchBalances() — so sánh số dư 131/331 nội bộ
    // Rủi ro: Số dư nội bộ không khớp giữa 2 entity → không loại trừ được trên BCTC hợp nhất
    // Rang buộc: Cần ghi nhận đầy đủ giao dịch ở cả 2 bên trước khi đối chiếu
    // Đối chiếu IC cho một entity
    public function match(int $entityId): void
    {
        $periodCode = $_GET['period'] ?? null;
        try {
            JsonResponse::ok($this->ic->matchBalances($entityId, $periodCode));
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    // NGHIỆP VỤ: Loại trừ giao dịch nội bộ cho entity — lập BCTC hợp nhất
    // Input: entityId, GET ?period=2025-01
    // Output: { journal_entries: [...], eliminated_amount, net_effect }
    // Service: IntercompanyService.eliminate() → JournalService.postEntry cho bút toán loại trừ
    // Permission: system, edit
    // Hạch toán: Nợ 411 (vốn) / Có 131 (nội bộ) và Nợ 331 (nội bộ) / Có 411
    // Rủi ro: R007 — Loại trừ không đúng → BCTC hợp nhất sai. Chỉ chạy sau khi đã đối chiếu (match)
    // Ràng buộc: Tất cả giao dịch nội bộ phải được ghi nhận ở cả 2 bên trước khi loại trừ
    // Loại trừ IC cho một entity
    public function eliminate(int $entityId): void
    {
        Auth::requirePermission('system', 'edit');
        $periodCode = $_GET['period'] ?? null;
        try {
            JsonResponse::ok($this->ic->eliminate($entityId, $_SESSION['user']['username'] ?? 'system', $periodCode));
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    // Báo cáo tổng hợp
    public function consolidated(): void
    {
        JsonResponse::ok($this->ic->consolidatedReport());
    }

    public function view(): void
    {
        require __DIR__ . '/../../../public/views/intercompany.php';
    }
}

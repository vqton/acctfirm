<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\MenuService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;
use PDO;

/**
 * MODULE: Menu & Điều hướng
 *
 * Mục đích nghiệp vụ:
 *   - Cung cấp menu sidebar theo quyền người dùng
 *   - Tìm kiếm chức năng
 *   - Quản lý menu yêu thích
 *
 * API endpoints:
 *   GET  /api/menu/sidebar — Menu tree
 *   GET  /api/menu/search?q= — Tìm kiếm
 *   GET  /api/menu/section/{section} — Menu theo section
 *   POST /api/menu/favorites — Thêm yêu thích
 *   DELETE /api/menu/favorites/{id} — Xóa yêu thích
 *   GET  /api/menu/favorites — Danh sách yêu thích
 *
 * Tích hợp:
 *   - MenuService xây dựng menu động
 *   - Auth kiểm tra quyền truy cập
 */
class MenuController
{
    private MenuService $menuService;
    private PDO $pdo;

    public function __construct(MenuService $menuService, PDO $pdo)
    {
        $this->menuService = $menuService;
        $this->pdo = $pdo;
    }

    /**
     * Menu sidebar
     *
     * @return void
     */
    public function getSidebar(): void
    {
        $menu = $this->menuService->getSidebarMenu();
        $period = $this->menuService->getCurrentPeriod();
        $pendingApprovals = $this->menuService->getPendingApprovalCount();
        $overdue = $this->menuService->getOverdueCounts();

        JsonResponse::ok([
            'menu' => $menu,
            'period' => $period,
            'badges' => [
                'pending_approvals' => $pendingApprovals,
                'overdue_ap' => $overdue['ap'],
                'overdue_ar' => $overdue['ar'],
            ],
        ]);
    }

    /**
     * Tìm kiếm chức năng
     *
     * @return void
     */
    public function search(): void
    {
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 2) {
            JsonResponse::ok(['results' => []]);
            return;
        }

        $results = $this->menuService->search($q);
        JsonResponse::ok([
            'results' => array_map(fn($item) => $item->toArray(), $results),
        ]);
    }

    /**
     * Menu theo section
     *
     * @param string $section Tên section
     * @return void
     */
    public function getSection(string $section): void
    {
        $items = $this->menuService->getSectionMenu($section);
        JsonResponse::ok(['items' => $items]);
    }

    /**
     * Thêm menu vào yêu thích
     *
     * @return void
     */
    public function addFavorite(): void
    {
        Auth::requirePermission('admin', 'update');
        Auth::checkCsrf();

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $menuId = (int)($body['menu_id'] ?? 0);
        $userId = Auth::getCurrentUserId();

        if ($menuId <= 0) {
            JsonResponse::error('ID menu không hợp lệ', 422);
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO menu_favorites (user_id, menu_item_id) VALUES (?, ?)"
        );
        $stmt->execute([$userId, $menuId]);

        JsonResponse::ok(['message' => 'Đã thêm vào yêu thích'], 201);
    }

    /**
     * Xóa menu yêu thích
     *
     * @param int $id ID menu
     * @return void
     */
    public function removeFavorite(int $id): void
    {
        Auth::requirePermission('admin', 'update');
        Auth::checkCsrf();

        $userId = Auth::getCurrentUserId();
        $stmt = $this->pdo->prepare("DELETE FROM menu_favorites WHERE user_id = ? AND menu_item_id = ?");
        $stmt->execute([$userId, $id]);

        JsonResponse::ok(['message' => 'Đã xóa khỏi yêu thích']);
    }

    /**
     * Danh sách yêu thích
     *
     * @return void
     */
    public function getFavorites(): void
    {
        $userId = Auth::getCurrentUserId();
        $stmt = $this->pdo->prepare(
            "SELECT mi.* FROM menu_favorites mf
             JOIN menu_items mi ON mi.id = mf.menu_item_id
             WHERE mf.user_id = ? AND mi.is_active = 1
             ORDER BY mi.section, mi.sort_order"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        JsonResponse::ok(['favorites' => $rows]);
    }
}

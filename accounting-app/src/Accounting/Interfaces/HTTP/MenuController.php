<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\MenuService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;
use PDO;

class MenuController
{
    private MenuService $menuService;
    private PDO $pdo;

    public function __construct(MenuService $menuService, PDO $pdo)
    {
        $this->menuService = $menuService;
        $this->pdo = $pdo;
    }

    // GET /api/menu/sidebar — trả về menu tree cho sidebar
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

    // GET /api/menu/search?q=keyword
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

    // GET /api/menu/section/:section
    public function getSection(string $section): void
    {
        $items = $this->menuService->getSectionMenu($section);
        JsonResponse::ok(['items' => $items]);
    }

    // POST /api/menu/favorites — thêm menu vào yêu thích
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

    // DELETE /api/menu/favorites/:id
    public function removeFavorite(int $id): void
    {
        Auth::requirePermission('admin', 'update');
        Auth::checkCsrf();

        $userId = Auth::getCurrentUserId();
        $stmt = $this->pdo->prepare("DELETE FROM menu_favorites WHERE user_id = ? AND menu_item_id = ?");
        $stmt->execute([$userId, $id]);

        JsonResponse::ok(['message' => 'Đã xóa khỏi yêu thích']);
    }

    // GET /api/menu/favorites — danh sách yêu thích
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

<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\MenuRepositoryInterface;
use Accounting\Domain\Model\MenuItem;
use Accounting\Infrastructure\Auth;
use PDO;

class MenuService
{
    private MenuRepositoryInterface $repo;
    private PDO $pdo;

    public function __construct(MenuRepositoryInterface $repo, PDO $pdo)
    {
        $this->repo = $repo;
        $this->pdo = $pdo;
    }

    // Lấy menu tree cho sidebar — đã filter theo permission của user hiện tại
    public function getSidebarMenu(): array
    {
        $allItems = $this->repo->findAllActive();
        $filtered = array_filter($allItems, fn(MenuItem $item) => $this->userCanSee($item));
        return $this->buildTree($filtered);
    }

    // Lấy menu tree cho 1 section cụ thể
    public function getSectionMenu(string $section): array
    {
        $items = $this->repo->findBySection($section);
        $filtered = array_filter($items, fn(MenuItem $item) => $this->userCanSee($item));
        return $this->buildTree($filtered);
    }

    // Tìm kiếm menu items
    public function search(string $keyword): array
    {
        $results = $this->repo->search($keyword);
        return array_values(array_filter($results, fn(MenuItem $item) => $this->userCanSee($item)));
    }

    // Kiểm tra user có quyền xem menu item này không
    // Nếu permission_module = null → public (ai cũng thấy)
    // Nếu permission_action = null → chỉ cần có quyền module ở bất kỳ action nào
    public function userCanSee(MenuItem $item): bool
    {
        $mod = $item->getPermissionModule();
        $act = $item->getPermissionAction();

        // Heading/section title luôn hiện (nếu có ít nhất 1 child visible)
        if ($mod === null && $act === null) return true;

        // Kiểm tra trong role_permissions
        // Nếu không có action cụ thể → chỉ cần module tồn tại trong permissions
        if ($act === null) {
            return Auth::hasPermission($mod, 'read')
                || Auth::hasPermission($mod, 'view')
                || Auth::hasPermission($mod, 'create');
        }

        return Auth::hasPermission($mod, $act);
    }

    // Xây dựng cây menu phân cấp
    private function buildTree(array $items): array
    {
        $tree = [];
        $grouped = [];

        foreach ($items as $item) {
            $section = $item->getSection();
            if (!isset($grouped[$section])) {
                $grouped[$section] = ['heading' => null, 'items' => []];
            }
            if ($item->isHeading()) {
                $grouped[$section]['heading'] = $item;
            } else {
                $grouped[$section]['items'][] = $item;
            }
        }

        // Sắp xếp section theo sort_order của heading (hoặc item đầu tiên)
        uasort($grouped, function ($a, $b) {
            $orderA = $a['heading'] ? $a['heading']->getSortOrder() : 9999;
            $orderB = $b['heading'] ? $b['heading']->getSortOrder() : 9999;
            if (empty($a['heading']) && !empty($a['items'])) {
                $orderA = $a['items'][0]->getSortOrder();
            }
            if (empty($b['heading']) && !empty($b['items'])) {
                $orderB = $b['items'][0]->getSortOrder();
            }
            return $orderA <=> $orderB;
        });

        foreach ($grouped as $section => $data) {
            $heading = $data['heading'];
            $children = $data['items'];

            // Nếu là heading nhưng không có children nào → bỏ qua (section rỗng)
            if ($heading && empty($children)) continue;
            // Nếu không có heading nhưng có items → tạo heading ẩn
            if (!$heading && !empty($children)) {
                $node = [
                    'heading' => null,
                    'section' => $section,
                    'label' => $this->getSectionLabel($section),
                    'icon' => null,
                    'children' => [],
                ];
                foreach ($children as $child) {
                    $node['children'][] = $child->toArray();
                }
                $tree[] = $node;
            } elseif ($heading) {
                $node = $heading->toArray();
                $node['children'] = [];
                foreach ($children as $child) {
                    $node['children'][] = $child->toArray();
                }
                $tree[] = $node;
            }
        }

        return $tree;
    }

    // Lấy label cho section từ DB (fallback nếu không có heading)
    private function getSectionLabel(string $section): string
    {
        $labels = [
            'dashboard' => 'Tổng quan',
            'cash_bank' => 'Tiền mặt & Ngân hàng',
            'purchase_ap' => 'Mua hàng & Công nợ phải trả',
            'sales_ar' => 'Bán hàng & Công nợ phải thu',
            'inventory_ccdc' => 'Kho & CCDC',
            'fixed_asset' => 'TSCĐ',
            'manufacturing' => 'Sản xuất & Giá thành',
            'projects_contracts' => 'Dự án & Hợp đồng',
            'payroll' => 'Tiền lương & Nhân sự',
            'tax' => 'Thuế & Hóa đơn',
            'gl_report' => 'Kế toán tổng hợp',
            'system' => 'Hệ thống',
        ];
        return $labels[$section] ?? $section;
    }

    // Lấy thông báo pending approvals
    public function getPendingApprovalCount(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM transactions WHERE status = 'submitted'"
        );
        return (int)$stmt->fetchColumn();
    }

    // Lấy số công nợ quá hạn
    public function getOverdueCounts(): array
    {
        $ap = $this->pdo->query(
            "SELECT COUNT(*) FROM ap_invoices WHERE due_date < CURDATE() AND status = 'unpaid'"
        )->fetchColumn();

        $ar = $this->pdo->query(
            "SELECT COUNT(*) FROM ar_invoices WHERE due_date < CURDATE() AND status = 'unpaid'"
        )->fetchColumn();

        return ['ap' => (int)$ap, 'ar' => (int)$ar];
    }

    // Lấy kỳ kế toán hiện tại
    public function getCurrentPeriod(): ?array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM accounting_periods WHERE status = 'open' ORDER BY start_date DESC LIMIT 1"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

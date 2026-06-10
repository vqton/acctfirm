<?php
namespace Accounting\Domain\Model;

/**
 * Mục menu — Điều hướng trong giao diện người dùng.
 *
 * Cấu trúc cây: mỗi mục có thể có parent (menu cha) và children (menu con).
 * Mỗi mục gắn với một permission để kiểm soát quyền truy cập.
 *
 * NGHIỆP VỤ:
 * - $section: nhóm menu (VD: 'dashboard', 'cash', 'inventory', 'report')
 * - $route: đường dẫn URL (có thể null nếu là heading)
 * - $isHeading: true nếu là heading nhóm, không phải link
 * - $badge: hiển thị badge (VD: "New", số lượng chờ duyệt)
 */
class MenuItem
{
    private int $id;
    private ?int $parentId;
    private string $section;
    private string $label;
    private ?string $icon;
    private ?string $route;
    private ?string $permissionModule;
    private ?string $permissionAction;
    private int $sortOrder;
    private bool $isActive;
    private bool $isHeading;
    private ?string $badge;
    private ?array $children;

    /**
     * Khởi tạo mục menu.
     *
     * @param int $id Định danh
     * @param int|null $parentId ID menu cha
     * @param string $section Nhóm menu
     * @param string $label Nhãn hiển thị
     * @param string|null $icon Icon CSS class
     * @param string|null $route Đường dẫn URL
     * @param string|null $permissionModule Module quyền
     * @param string|null $permissionAction Hành động quyền
     * @param int $sortOrder Thứ tự sắp xếp
     * @param bool $isActive Kích hoạt
     * @param bool $isHeading Là heading nhóm
     * @param string|null $badge Nội dung badge
     */
    public function __construct(
        int $id,
        ?int $parentId,
        string $section,
        string $label,
        ?string $icon = null,
        ?string $route = null,
        ?string $permissionModule = null,
        ?string $permissionAction = null,
        int $sortOrder = 0,
        bool $isActive = true,
        bool $isHeading = false,
        ?string $badge = null
    ) {
        $this->id = $id;
        $this->parentId = $parentId;
        $this->section = $section;
        $this->label = $label;
        $this->icon = $icon;
        $this->route = $route;
        $this->permissionModule = $permissionModule;
        $this->permissionAction = $permissionAction;
        $this->sortOrder = $sortOrder;
        $this->isActive = $isActive;
        $this->isHeading = $isHeading;
        $this->badge = $badge;
        $this->children = [];
    }

    /** @return int Định danh */
    public function getId(): int { return $this->id; }

    /** @return int|null ID menu cha */
    public function getParentId(): ?int { return $this->parentId; }

    /** @return string Nhóm menu */
    public function getSection(): string { return $this->section; }

    /** @return string Nhãn hiển thị */
    public function getLabel(): string { return $this->label; }

    /** @return string|null Icon CSS class */
    public function getIcon(): ?string { return $this->icon; }

    /** @return string|null Đường dẫn URL */
    public function getRoute(): ?string { return $this->route; }

    /** @return string|null Module quyền */
    public function getPermissionModule(): ?string { return $this->permissionModule; }

    /** @return string|null Hành động quyền */
    public function getPermissionAction(): ?string { return $this->permissionAction; }

    /** @return int Thứ tự sắp xếp */
    public function getSortOrder(): int { return $this->sortOrder; }

    /** @return bool Kích hoạt */
    public function isActive(): bool { return $this->isActive; }

    /** @return bool Là heading nhóm */
    public function isHeading(): bool { return $this->isHeading; }

    /** @return string|null Badge */
    public function getBadge(): ?string { return $this->badge; }

    /** @return array Danh sách menu con */
    public function getChildren(): array { return $this->children; }

    /** @param array $children Danh sách menu con mới */
    public function setChildren(array $children): void { $this->children = $children; }

    /** @param MenuItem $child Thêm một menu con */
    public function addChild(MenuItem $child): void { $this->children[] = $child; }

    /** @return bool Có menu con hay không */
    public function hasChildren(): bool { return !empty($this->children); }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu menu dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parentId,
            'section' => $this->section,
            'label' => $this->label,
            'icon' => $this->icon,
            'route' => $this->route,
            'permission_module' => $this->permissionModule,
            'permission_action' => $this->permissionAction,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'is_heading' => $this->isHeading,
            'badge' => $this->badge,
            'children' => array_map(fn(MenuItem $c) => $c->toArray(), $this->children),
        ];
    }
}

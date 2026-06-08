<?php
namespace Accounting\Domain\Model;

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

    public function getId(): int { return $this->id; }
    public function getParentId(): ?int { return $this->parentId; }
    public function getSection(): string { return $this->section; }
    public function getLabel(): string { return $this->label; }
    public function getIcon(): ?string { return $this->icon; }
    public function getRoute(): ?string { return $this->route; }
    public function getPermissionModule(): ?string { return $this->permissionModule; }
    public function getPermissionAction(): ?string { return $this->permissionAction; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function isActive(): bool { return $this->isActive; }
    public function isHeading(): bool { return $this->isHeading; }
    public function getBadge(): ?string { return $this->badge; }
    public function getChildren(): array { return $this->children; }

    public function setChildren(array $children): void { $this->children = $children; }
    public function addChild(MenuItem $child): void { $this->children[] = $child; }

    public function hasChildren(): bool { return !empty($this->children); }

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

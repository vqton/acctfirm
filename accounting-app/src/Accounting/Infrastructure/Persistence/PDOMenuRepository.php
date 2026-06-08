<?php
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\MenuItem;
use Accounting\Domain\Repository\MenuRepositoryInterface;
use PDO;

class PDOMenuRepository implements MenuRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findAllActive(): array
    {
        $rows = $this->pdo->query(
            "SELECT * FROM menu_items WHERE is_active = 1 ORDER BY section, sort_order"
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'hydrate'], $rows);
    }

    public function findBySection(string $section): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM menu_items WHERE is_active = 1 AND section = ? ORDER BY sort_order"
        );
        $stmt->execute([$section]);
        return array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?MenuItem
    {
        $stmt = $this->pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function search(string $keyword): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM menu_items WHERE is_active = 1 AND label LIKE ? ORDER BY section, sort_order LIMIT 20"
        );
        $stmt->execute(['%' . $keyword . '%']);
        return array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function save(MenuItem $item): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO menu_items (id, parent_id, section, label, icon, route, permission_module, permission_action, sort_order, is_active, is_heading, badge)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE label=VALUES(label), icon=VALUES(icon), route=VALUES(route),
               permission_module=VALUES(permission_module), permission_action=VALUES(permission_action),
               sort_order=VALUES(sort_order), is_active=VALUES(is_active), badge=VALUES(badge)"
        );
        $stmt->execute([
            $item->getId(),
            $item->getParentId(),
            $item->getSection(),
            $item->getLabel(),
            $item->getIcon(),
            $item->getRoute(),
            $item->getPermissionModule(),
            $item->getPermissionAction(),
            $item->getSortOrder(),
            $item->isActive() ? 1 : 0,
            $item->isHeading() ? 1 : 0,
            $item->getBadge(),
        ]);
    }

    public function update(MenuItem $item): void
    {
        $this->save($item);
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE menu_items SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): MenuItem
    {
        return new MenuItem(
            (int)$row['id'],
            $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
            $row['section'],
            $row['label'],
            $row['icon'],
            $row['route'],
            $row['permission_module'],
            $row['permission_action'],
            (int)$row['sort_order'],
            (bool)$row['is_active'],
            (bool)$row['is_heading'],
            $row['badge']
        );
    }
}

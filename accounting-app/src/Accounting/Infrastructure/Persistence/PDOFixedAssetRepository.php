<?php
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\FixedAsset;
use Accounting\Domain\Repository\FixedAssetRepositoryInterface;
use PDO;

class PDOFixedAssetRepository implements FixedAssetRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?FixedAsset
    {
        $stmt = $this->pdo->prepare('SELECT * FROM fixed_assets WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?FixedAsset
    {
        $stmt = $this->pdo->prepare('SELECT * FROM fixed_assets WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM fixed_assets ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function findActive(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM fixed_assets WHERE status = 'in_use' ORDER BY code");
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(FixedAsset $asset): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO fixed_assets (id, code, name, purchase_date, original_cost, purchase_cost, depreciation_method, useful_life, salvage_value, total_estimated_units, monthly_depreciation, accumulated_depreciation, net_book_value, fa_category, fa_type, department_id, employee_id, location, status, last_depreciation_date, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name),
             purchase_date=VALUES(purchase_date), original_cost=VALUES(original_cost),
             purchase_cost=VALUES(purchase_cost),
             depreciation_method=VALUES(depreciation_method), useful_life=VALUES(useful_life),
             salvage_value=VALUES(salvage_value),
             total_estimated_units=VALUES(total_estimated_units),
             monthly_depreciation=VALUES(monthly_depreciation),
             accumulated_depreciation=VALUES(accumulated_depreciation),
             net_book_value=VALUES(net_book_value), fa_category=VALUES(fa_category),
             fa_type=VALUES(fa_type), department_id=VALUES(department_id),
             employee_id=VALUES(employee_id), location=VALUES(location),
             status=VALUES(status), last_depreciation_date=VALUES(last_depreciation_date),
             notes=VALUES(notes)'
        );
        $stmt->execute([
            $asset->getId(), $asset->getCode(), $asset->getName(), $asset->getPurchaseDate(),
            $asset->getOriginalCost(), $asset->getPurchaseCost(),
            $asset->getDepreciationMethod(), $asset->getUsefulLife(),
            $asset->getSalvageValue(), $asset->getTotalEstimatedUnits(),
            $asset->getMonthlyDepreciation(),
            $asset->getAccumulatedDepreciation(), $asset->getNetBookValue(),
            $asset->getFaCategory(), $asset->getFaType(),
            $asset->getDepartmentId(), $asset->getEmployeeId(), $asset->getLocation(),
            $asset->getStatus(), $asset->getLastDepreciationDate(), $asset->getNotes(),
            $asset->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM fixed_assets WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): FixedAsset
    {
        return new FixedAsset(
            $row['id'], $row['code'], $row['name'], $row['purchase_date'],
            (float)$row['original_cost'],
            $row['depreciation_method'], (int)$row['useful_life'],
            (float)$row['salvage_value'],
            (float)($row['monthly_depreciation'] ?? 0),
            (float)($row['accumulated_depreciation'] ?? 0),
            (float)($row['net_book_value'] ?? 0),
            $row['fa_category'] ?? 'tangible',
            $row['fa_type'] ?? null,
            isset($row['total_estimated_units']) ? (float)$row['total_estimated_units'] : null,
            (float)($row['purchase_cost'] ?? 0),
            $row['department_id'] ?? null,
            $row['employee_id'] ?? null,
            $row['location'] ?? null,
            $row['status'] ?? 'in_use',
            $row['last_depreciation_date'] ?? null,
            $row['notes'] ?? null
        );
    }
}

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

    public function save(FixedAsset $asset): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO fixed_assets (id, code, name, purchase_date, original_cost, depreciation_method, useful_life, salvage_value, monthly_depreciation, accumulated_depreciation, net_book_value, department_id, employee_id, location, status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name),
             purchase_date=VALUES(purchase_date), original_cost=VALUES(original_cost),
             depreciation_method=VALUES(depreciation_method), useful_life=VALUES(useful_life),
             salvage_value=VALUES(salvage_value), monthly_depreciation=VALUES(monthly_depreciation),
             accumulated_depreciation=VALUES(accumulated_depreciation),
             net_book_value=VALUES(net_book_value), department_id=VALUES(department_id),
             employee_id=VALUES(employee_id), location=VALUES(location), status=VALUES(status),
             notes=VALUES(notes)'
        );
        $stmt->execute([
            $asset->getId(), $asset->getCode(), $asset->getName(), $asset->getPurchaseDate(),
            $asset->getOriginalCost(), $asset->getDepreciationMethod(), $asset->getUsefulLife(),
            $asset->getSalvageValue(), $asset->getMonthlyDepreciation(),
            $asset->getAccumulatedDepreciation(), $asset->getNetBookValue(),
            $asset->getDepartmentId(), $asset->getEmployeeId(), $asset->getLocation(),
            $asset->isStatus() ? 1 : 0, $asset->getNotes(),
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
        $asset = new FixedAsset(
            $row['id'], $row['code'], $row['name'], $row['purchase_date'],
            (float)$row['original_cost'], $row['depreciation_method'], (int)$row['useful_life'],
            (float)$row['salvage_value'], (float)$row['monthly_depreciation'],
            (float)$row['accumulated_depreciation'], (float)$row['net_book_value'],
            $row['department_id'], $row['employee_id'], $row['location'], $row['notes']
        );
        $asset->setStatus((bool)$row['status']);
        return $asset;
    }
}

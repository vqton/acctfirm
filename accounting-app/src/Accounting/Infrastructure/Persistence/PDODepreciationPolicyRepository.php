<?php
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\DepreciationPolicy;
use Accounting\Domain\Repository\DepreciationPolicyRepositoryInterface;
use PDO;

class PDODepreciationPolicyRepository implements DepreciationPolicyRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?DepreciationPolicy
    {
        $stmt = $this->pdo->prepare('SELECT * FROM depreciation_policies WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?DepreciationPolicy
    {
        $stmt = $this->pdo->prepare('SELECT * FROM depreciation_policies WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM depreciation_policies ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(DepreciationPolicy $policy): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO depreciation_policies (id, code, name, method, default_life, default_salvage_rate, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name), method=VALUES(method),
             default_life=VALUES(default_life), default_salvage_rate=VALUES(default_salvage_rate),
             status=VALUES(status)'
        );
        $stmt->execute([
            $policy->getId(), $policy->getCode(), $policy->getName(), $policy->getMethod(),
            $policy->getDefaultLife(), $policy->getDefaultSalvageRate(),
            $policy->isStatus() ? 1 : 0, $policy->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM depreciation_policies WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): DepreciationPolicy
    {
        $policy = new DepreciationPolicy(
            $row['id'], $row['code'], $row['name'], $row['method'],
            (int)$row['default_life'], (float)$row['default_salvage_rate']
        );
        $policy->setStatus((bool)$row['status']);
        return $policy;
    }
}

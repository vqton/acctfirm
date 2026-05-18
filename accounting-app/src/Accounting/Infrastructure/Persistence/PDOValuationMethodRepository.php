<?php
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\ValuationMethod;
use Accounting\Domain\Repository\ValuationMethodRepositoryInterface;
use PDO;

class PDOValuationMethodRepository implements ValuationMethodRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?ValuationMethod
    {
        $stmt = $this->pdo->prepare('SELECT * FROM valuation_methods WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?ValuationMethod
    {
        $stmt = $this->pdo->prepare('SELECT * FROM valuation_methods WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM valuation_methods ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(ValuationMethod $method): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO valuation_methods (id, code, name, description, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name),
             description=VALUES(description), status=VALUES(status)'
        );
        $stmt->execute([
            $method->getId(), $method->getCode(), $method->getName(), $method->getDescription(),
            $method->isStatus() ? 1 : 0, $method->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM valuation_methods WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): ValuationMethod
    {
        $method = new ValuationMethod($row['id'], $row['code'], $row['name'], $row['description']);
        $method->setStatus((bool)$row['status']);
        return $method;
    }
}

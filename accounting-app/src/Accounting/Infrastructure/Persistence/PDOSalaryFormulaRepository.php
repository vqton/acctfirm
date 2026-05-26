<?php
// Quan ly du lieu: cong thuc tinh luong (salary_formulas)
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\SalaryFormula;
use Accounting\Domain\Repository\SalaryFormulaRepositoryInterface;
use PDO;

class PDOSalaryFormulaRepository implements SalaryFormulaRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?SalaryFormula
    {
        $stmt = $this->pdo->prepare('SELECT * FROM salary_formulas WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?SalaryFormula
    {
        $stmt = $this->pdo->prepare('SELECT * FROM salary_formulas WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM salary_formulas ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function findByType(string $type): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM salary_formulas WHERE type = ? ORDER BY code');
        $stmt->execute([$type]);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function save(SalaryFormula $f): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO salary_formulas (id, code, name, type, description, formula_expression, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                code=VALUES(code), name=VALUES(name), type=VALUES(type),
                description=VALUES(description), formula_expression=VALUES(formula_expression),
                status=VALUES(status)'
        );
        $stmt->execute([
            $f->getId(), $f->getCode(), $f->getName(), $f->getType(),
            $f->getDescription(), $f->getFormulaExpression(),
            $f->isStatus() ? 1 : 0, $f->toArray()['created_at'],
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM salary_formulas WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): SalaryFormula
    {
        $f = new SalaryFormula(
            $row['id'], $row['code'], $row['name'], $row['type'],
            $row['description'] ?? null, $row['formula_expression'] ?? '',
            (bool)$row['status']
        );
        return $f;
    }
}

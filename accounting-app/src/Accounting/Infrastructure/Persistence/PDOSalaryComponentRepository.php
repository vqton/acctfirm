<?php
// Quan ly du lieu: danh muc khoan luong (salary_components)
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\SalaryComponent;
use Accounting\Domain\Repository\SalaryComponentRepositoryInterface;
use PDO;

class PDOSalaryComponentRepository implements SalaryComponentRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?SalaryComponent
    {
        $stmt = $this->pdo->prepare('SELECT * FROM salary_components WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?SalaryComponent
    {
        $stmt = $this->pdo->prepare('SELECT * FROM salary_components WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM salary_components ORDER BY priority, code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function findByType(string $type): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM salary_components WHERE type = ? ORDER BY priority');
        $stmt->execute([$type]);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function save(SalaryComponent $c): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO salary_components (id, code, name, type, calculation_type, value,
                account_code_debit, account_code_credit, priority, is_mandatory, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                code=VALUES(code), name=VALUES(name), type=VALUES(type),
                calculation_type=VALUES(calculation_type), value=VALUES(value),
                account_code_debit=VALUES(account_code_debit), account_code_credit=VALUES(account_code_credit),
                priority=VALUES(priority), is_mandatory=VALUES(is_mandatory), status=VALUES(status)'
        );
        $stmt->execute([
            $c->getId(), $c->getCode(), $c->getName(), $c->getType(),
            $c->getCalculationType(), $c->getValue(),
            $c->getAccountCodeDebit(), $c->getAccountCodeCredit(),
            $c->getPriority(), $c->isMandatory() ? 1 : 0, $c->isStatus() ? 1 : 0,
            $c->toArray()['created_at'],
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM salary_components WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): SalaryComponent
    {
        $c = new SalaryComponent(
            $row['id'], $row['code'], $row['name'], $row['type'],
            $row['calculation_type'], (float)$row['value'],
            $row['account_code_debit'], $row['account_code_credit'],
            (int)$row['priority'], (bool)$row['is_mandatory']
        );
        $c->setStatus((bool)$row['status']);
        return $c;
    }
}

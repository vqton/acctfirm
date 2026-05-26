<?php
// Quan ly du lieu: danh muc nhan vien
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\Employee;
use Accounting\Domain\Repository\EmployeeRepositoryInterface;
use PDO;

class PDOEmployeeRepository implements EmployeeRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?Employee
    {
        $stmt = $this->pdo->prepare('SELECT * FROM employees WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?Employee
    {
        $stmt = $this->pdo->prepare('SELECT * FROM employees WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM employees ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function save(Employee $e): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO employees (id, code, name, department_id, position, phone, email,
                insurance_salary, bank_account, bank_name, tax_code,
                dependent_count, region, contract_type, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                code=VALUES(code), name=VALUES(name), department_id=VALUES(department_id),
                position=VALUES(position), phone=VALUES(phone), email=VALUES(email),
                insurance_salary=VALUES(insurance_salary), bank_account=VALUES(bank_account),
                bank_name=VALUES(bank_name), tax_code=VALUES(tax_code),
                dependent_count=VALUES(dependent_count), region=VALUES(region),
                contract_type=VALUES(contract_type), status=VALUES(status)'
        );
        $stmt->execute([
            $e->getId(), $e->getCode(), $e->getName(), $e->getDepartmentId(),
            $e->getPosition(), $e->getPhone(), $e->getEmail(),
            $e->getInsuranceSalary(), $e->getBankAccount(), $e->getBankName(),
            $e->getTaxCode(), $e->getDependentCount(), $e->getRegion(),
            $e->getContractType(), $e->isStatus() ? 1 : 0,
            $e->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM employees WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): Employee
    {
        $e = new Employee(
            $row['id'], $row['code'], $row['name'], $row['department_id'],
            $row['position'], $row['phone'], $row['email'],
            isset($row['insurance_salary']) ? (float)$row['insurance_salary'] : null,
            $row['bank_account'] ?? null, $row['bank_name'] ?? null,
            $row['tax_code'] ?? null,
            (int)($row['dependent_count'] ?? 0),
            $row['region'] ?? null,
            $row['contract_type'] ?? 'indefinite'
        );
        $e->setStatus((bool)$row['status']);
        return $e;
    }
}

<?php
// Quan ly du lieu: ky luong (payroll_periods)
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\PayrollPeriod;
use Accounting\Domain\Repository\PayrollPeriodRepositoryInterface;
use PDO;

class PDOPayrollPeriodRepository implements PayrollPeriodRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?PayrollPeriod
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payroll_periods WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?PayrollPeriod
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payroll_periods WHERE period_code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM payroll_periods ORDER BY start_date DESC');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function findOpen(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payroll_periods WHERE status = ? ORDER BY start_date DESC');
        $stmt->execute(['open']);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function save(PayrollPeriod $p): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO payroll_periods (id, period_code, name, start_date, end_date, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                name=VALUES(name), start_date=VALUES(start_date), end_date=VALUES(end_date),
                status=VALUES(status), created_by=VALUES(created_by)'
        );
        $stmt->execute([
            $p->getId(), $p->getPeriodCode(), $p->getName(),
            $p->getStartDate()->format('Y-m-d'), $p->getEndDate()->format('Y-m-d'),
            $p->getStatus(), $p->getCreatedBy(),
            $p->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM payroll_periods WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): PayrollPeriod
    {
        return new PayrollPeriod(
            $row['id'], $row['period_code'], $row['name'],
            new \DateTimeImmutable($row['start_date']),
            new \DateTimeImmutable($row['end_date']),
            $row['status'], $row['created_by']
        );
    }
}

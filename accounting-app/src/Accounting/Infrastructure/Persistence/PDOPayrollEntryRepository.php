<?php
// Quan ly du lieu: bang luong (payroll_entries + payroll_details)
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\PayrollEntry;
use Accounting\Domain\Model\PayrollDetail;
use Accounting\Domain\Repository\PayrollEntryRepositoryInterface;
use PDO;

class PDOPayrollEntryRepository implements PayrollEntryRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?PayrollEntry
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payroll_entries WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByPeriod(string $periodId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payroll_entries WHERE period_id = ? ORDER BY created_at DESC');
        $stmt->execute([$periodId]);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT pe.*, pp.name AS period_name
            FROM payroll_entries pe
            JOIN payroll_periods pp ON pp.id = pe.period_id
            ORDER BY pp.start_date DESC, pe.created_at DESC');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function save(PayrollEntry $e): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO payroll_entries (id, period_id, status, total_employees,
                total_gross, total_allowances, total_deductions,
                total_insurance_ee, total_insurance_er, total_tax,
                total_net, total_cost, posted_at, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                status=VALUES(status), total_employees=VALUES(total_employees),
                total_gross=VALUES(total_gross), total_allowances=VALUES(total_allowances),
                total_deductions=VALUES(total_deductions),
                total_insurance_ee=VALUES(total_insurance_ee),
                total_insurance_er=VALUES(total_insurance_er),
                total_tax=VALUES(total_tax), total_net=VALUES(total_net),
                total_cost=VALUES(total_cost), posted_at=VALUES(posted_at)'
        );
        $stmt->execute([
            $e->getId(), $e->getPeriodId(), $e->getStatus(),
            $e->getTotalEmployees(),
            $e->getTotalGross(), $e->getTotalAllowances(), $e->getTotalDeductions(),
            $e->getTotalInsuranceEe(), $e->getTotalInsuranceEr(), $e->getTotalTax(),
            $e->getTotalNet(), $e->getTotalCost(),
            $e->getPostedAt()?->format('Y-m-d H:i:s'),
            $e->getCreatedBy(), $e->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM payroll_entries WHERE id = ?');
        $stmt->execute([$id]);
    }

    // --- Chi tiet bang luong (payroll_details) ---

    public function findDetailsByEntry(string $entryId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pd.*, e.code AS employee_code, e.name AS employee_name, d.name AS department_name
             FROM payroll_details pd
             JOIN employees e ON e.id = pd.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE pd.payroll_entry_id = ?
             ORDER BY e.code'
        );
        $stmt->execute([$entryId]);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $row; }
        return $items;
    }

    public function findDetailById(string $id): ?PayrollDetail
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payroll_details WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrateDetail($row) : null;
    }

    public function saveDetail(PayrollDetail $d): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO payroll_details (id, payroll_entry_id, employee_id,
                gross_salary, total_allowances, total_deductions,
                insurance_ee, insurance_er, tax_amount, net_pay,
                overtime_amount, total_cost, working_days, status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                gross_salary=VALUES(gross_salary), total_allowances=VALUES(total_allowances),
                total_deductions=VALUES(total_deductions),
                insurance_ee=VALUES(insurance_ee), insurance_er=VALUES(insurance_er),
                tax_amount=VALUES(tax_amount), net_pay=VALUES(net_pay),
                overtime_amount=VALUES(overtime_amount), total_cost=VALUES(total_cost),
                working_days=VALUES(working_days), status=VALUES(status), notes=VALUES(notes)'
        );
        $stmt->execute([
            $d->getId(), $d->getPayrollEntryId(), $d->getEmployeeId(),
            $d->getGrossSalary(), $d->getTotalAllowances(), $d->getTotalDeductions(),
            $d->getInsuranceEe(), $d->getInsuranceEr(), $d->getTaxAmount(), $d->getNetPay(),
            $d->getOvertimeAmount(), $d->getTotalCost(), $d->getWorkingDays(),
            $d->getStatus(), $d->getNotes(),
            $d->toArray()['created_at'],
        ]);
    }

    public function deleteDetail(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM payroll_details WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function deleteDetailsByEntry(string $entryId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM payroll_details WHERE payroll_entry_id = ?');
        $stmt->execute([$entryId]);
    }

    private function hydrate(array $row): PayrollEntry
    {
        $row['period_name'] ??= '';
        return new PayrollEntry(
            $row['id'], $row['period_id'], $row['status'] ?? 'draft',
            (int)$row['total_employees'], (float)$row['total_gross'],
            (float)$row['total_allowances'], (float)$row['total_deductions'],
            (float)$row['total_insurance_ee'], (float)$row['total_insurance_er'],
            (float)$row['total_tax'], (float)$row['total_net'], (float)$row['total_cost'],
            $row['posted_at'] ? new \DateTimeImmutable($row['posted_at']) : null,
            $row['created_by']
        );
    }

    private function hydrateDetail(array $row): PayrollDetail
    {
        return new PayrollDetail(
            $row['id'], $row['payroll_entry_id'], $row['employee_id'],
            (float)$row['gross_salary'], (float)$row['total_allowances'],
            (float)$row['total_deductions'], (float)$row['insurance_ee'],
            (float)$row['insurance_er'], (float)$row['tax_amount'],
            (float)$row['net_pay'], (float)$row['overtime_amount'],
            (float)$row['total_cost'], (float)$row['working_days'],
            $row['status'] ?? 'active', $row['notes'] ?? null
        );
    }
}

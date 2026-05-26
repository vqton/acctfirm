<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\Employee;
use Accounting\Domain\Repository\EmployeeRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Danh muc Nhan vien (Employee Master)
 *
 * API endpoints:
 *   (Su dung CrudControllerTrait — CRUD chuan)
 *
 * Tich hop:
 *   - Payroll module tinh luong dua tren employee
 */
class EmployeeController
{
    use CrudControllerTrait;

    private EmployeeRepositoryInterface $repo;
    public function __construct(EmployeeRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'emp_'; }

    protected function createEntity(array $data): object
    {
        return new Employee(
            $data['id'], $data['code'], $data['name'],
            $data['department_id'] ?? null, $data['position'] ?? null,
            $data['phone'] ?? null, $data['email'] ?? null,
            isset($data['insurance_salary']) ? (float)$data['insurance_salary'] : null,
            $data['bank_account'] ?? null, $data['bank_name'] ?? null,
            $data['tax_code'] ?? null, (int)($data['dependent_count'] ?? 0),
            $data['region'] ?? null, $data['contract_type'] ?? 'indefinite'
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['department_id'])) $entity->setDepartmentId($data['department_id']);
        if (isset($data['position'])) $entity->setPosition($data['position']);
        if (isset($data['phone'])) $entity->setPhone($data['phone']);
        if (isset($data['email'])) $entity->setEmail($data['email']);
        if (isset($data['insurance_salary'])) $entity->setInsuranceSalary((float)$data['insurance_salary']);
        if (isset($data['bank_account'])) $entity->setBankAccount($data['bank_account']);
        if (isset($data['bank_name'])) $entity->setBankName($data['bank_name']);
        if (isset($data['tax_code'])) $entity->setTaxCode($data['tax_code']);
        if (isset($data['dependent_count'])) $entity->setDependentCount((int)$data['dependent_count']);
        if (isset($data['region'])) $entity->setRegion($data['region']);
        if (isset($data['contract_type'])) $entity->setContractType($data['contract_type']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}

<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\Employee;
use Accounting\Domain\Repository\EmployeeRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Nhân viên
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách nhân viên
 *   - Liên kết với phòng ban, chức vụ, lương
 *
 * API endpoints:
 *   GET    /api/employees — Danh sách
 *   POST   /api/employees — Tạo mới
 *   GET    /api/employees/{id} — Chi tiết
 *   PUT    /api/employees/{id} — Cập nhật
 *   DELETE /api/employees/{id} — Xoá
 *
 * Tích hợp:
 *   - PayrollController tính lương
 *   - DepartmentController quản lý phòng ban
 */
class EmployeeController
{
    use CrudControllerTrait;

    /**
     * @param EmployeeRepositoryInterface $repository
     */
    public function __construct(EmployeeRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'employees';
    }

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'emp_';
    }

    protected function createEntity(array $data): object
    {
        return new Employee(
            id: $data['id'] ?? uniqid('emp_'),
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            departmentId: $data['department_id'] ?? null,
            position: $data['position'] ?? null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            insuranceSalary: isset($data['insurance_salary']) ? (float)$data['insurance_salary'] : null,
            bankAccount: $data['bank_account'] ?? null,
            bankName: $data['bank_name'] ?? null,
            taxCode: $data['tax_code'] ?? null,
            dependentCount: (int)($data['dependent_count'] ?? 0),
            region: $data['region'] ?? null,
            contractType: $data['contract_type'] ?? 'indefinite'
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

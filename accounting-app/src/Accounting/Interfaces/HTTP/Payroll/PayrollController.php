<?php
namespace Accounting\Interfaces\HTTP\Payroll;

use Accounting\Domain\Service\PayrollService;
use Accounting\Domain\Repository\EmployeeRepositoryInterface;
use Accounting\Domain\Repository\PayrollPeriodRepositoryInterface;
use Accounting\Domain\Repository\PayrollEntryRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Tien luong — API quan ly luong, BHXH, thue TNCN
 *
 * API endpoints:
 *   Ky luong:      GET/POST /api/payroll/periods, GET /periods/open, POST /periods/:id/close
 *   Bang luong:    GET /api/payroll/entries, GET /entries/:id, GET /entries/:id/details
 *   Tinh luong:    POST /api/payroll/process, GET /calculate/employee
 *   Duyet/Post:    POST /entries/:id/approve, POST /entries/:id/post
 *   Dieu chinh:    POST /entries/:id/adjust
 *   Tinh toan:     GET /calculate/insurance, GET /calculate/tax
 */
class PayrollController
{
    private PayrollService $payrollService;
    private EmployeeRepositoryInterface $employeeRepo;
    private PayrollPeriodRepositoryInterface $payrollPeriodRepo;
    private PayrollEntryRepositoryInterface $payrollEntryRepo;

    public function __construct(
        PayrollService $payrollService,
        EmployeeRepositoryInterface $employeeRepo,
        PayrollPeriodRepositoryInterface $payrollPeriodRepo,
        PayrollEntryRepositoryInterface $payrollEntryRepo
    ) {
        $this->payrollService = $payrollService;
        $this->employeeRepo = $employeeRepo;
        $this->payrollPeriodRepo = $payrollPeriodRepo;
        $this->payrollEntryRepo = $payrollEntryRepo;
    }

    // --- KY LUONG ---

    public function listPeriods(): void
    {
        Auth::requirePermission('payroll', 'read');
        $periods = $this->payrollService->findAllPeriods();
        JsonResponse::ok(array_map(fn($p) => $p->toArray(), $periods));
    }

    public function getPeriod(string $id): void
    {
        Auth::requirePermission('payroll', 'read');
        $period = $this->payrollService->findPeriodById($id);
        if (!$period) { JsonResponse::error('Payroll period not found', 404); return; }
        JsonResponse::ok($period->toArray());
    }

    public function createPeriod(): void
    {
        Auth::requirePermission('payroll', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        $yearMonth = $data['period_code'] ?? $data['year_month'] ?? date('Ym');
        $period = $this->payrollService->createPayrollPeriod($yearMonth, $_SESSION['user_id'] ?? 'system');
        JsonResponse::ok($period->toArray(), 201);
    }

    public function closePeriod(string $id): void
    {
        Auth::requirePermission('payroll', 'post');
        $period = $this->payrollService->closePayroll($id, $_SESSION['user_id'] ?? 'system');
        JsonResponse::ok($period->toArray());
    }

    public function listOpenPeriods(): void
    {
        Auth::requirePermission('payroll', 'read');
        $periods = $this->payrollService->findOpenPeriods();
        JsonResponse::ok(array_map(fn($p) => $p->toArray(), $periods));
    }

    // --- BANG LUONG ---

    public function listEntries(): void
    {
        Auth::requirePermission('payroll', 'read');
        $entries = $this->payrollService->findAllEntries();
        JsonResponse::ok(array_map(fn($e) => $e->toArray(), $entries));
    }

    public function getEntry(string $id): void
    {
        Auth::requirePermission('payroll', 'read');
        $entry = $this->payrollService->findEntryById($id);
        if (!$entry) { JsonResponse::error('Payroll entry not found', 404); return; }
        JsonResponse::ok($entry->toArray());
    }

    public function getEntryDetails(string $id): void
    {
        Auth::requirePermission('payroll', 'read');
        $entry = $this->payrollService->findEntryById($id);
        if (!$entry) { JsonResponse::error('Payroll entry not found', 404); return; }
        $details = $this->payrollService->findDetailsByEntry($id);
        JsonResponse::ok(['entry' => $entry->toArray(), 'details' => $details]);
    }

    // --- TINH LUONG ---

    public function processPayroll(): void
    {
        Auth::requirePermission('payroll', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        $periodId = $data['period_id'] ?? '';
        if (!$periodId) { JsonResponse::error('period_id required', 400); return; }
        $overrides = $data['employee_overrides'] ?? [];
        $entry = $this->payrollService->processPayroll($periodId, $_SESSION['user_id'] ?? 'system', $overrides);
        JsonResponse::ok($entry->toArray(), 201);
    }

    // --- DUYET & POST ---

    public function approveEntry(string $id): void
    {
        Auth::requirePermission('payroll', 'post');
        $entry = $this->payrollService->approvePayroll($id, $_SESSION['user_id'] ?? 'system');
        JsonResponse::ok($entry->toArray());
    }

    public function postEntry(string $id): void
    {
        Auth::requirePermission('payroll', 'post');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $result = $this->payrollService->postPayroll($id, $_SESSION['user_id'] ?? 'system', $data['account_overrides'] ?? []);
        JsonResponse::ok($result);
    }

    // --- DIEU CHINH ---

    public function adjustEntry(string $id): void
    {
        Auth::requirePermission('payroll', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['adjustments']) || !is_array($data['adjustments'])) {
            JsonResponse::error('adjustments array required', 400); return;
        }
        $entry = $this->payrollService->adjustPayroll($id, $_SESSION['user_id'] ?? 'system', $data['adjustments']);
        JsonResponse::ok($entry->toArray(), 201);
    }

    // --- TINH TOAN ---

    public function calculateInsurance(): void
    {
        Auth::requirePermission('payroll', 'read');
        $gross = (float)($_GET['gross'] ?? 0);
        $insuranceSalary = isset($_GET['insurance_salary']) ? (float)$_GET['insurance_salary'] : $gross;
        $region = $_GET['region'] ?? 'IV';
        $result = $this->payrollService->calculateInsurance($gross, $insuranceSalary, $region);
        JsonResponse::ok($result);
    }

    public function calculateTax(): void
    {
        Auth::requirePermission('payroll', 'read');
        $gross = (float)($_GET['gross'] ?? 0);
        $insuranceEe = (float)($_GET['insurance_ee'] ?? 0);
        $dependentCount = (int)($_GET['dependent_count'] ?? 0);
        $tax = $this->payrollService->calculateTax($gross, $insuranceEe, $dependentCount);
        JsonResponse::ok(['tax_amount' => $tax]);
    }

    public function calculateEmployeePay(): void
    {
        Auth::requirePermission('payroll', 'read');
        $employeeId = $_GET['employee_id'] ?? '';
        $employee = $this->employeeRepo->findById($employeeId);
        if (!$employee) { JsonResponse::error('Employee not found', 404); return; }
        $override = [];
        if (isset($_GET['gross'])) $override['gross_salary'] = (float)$_GET['gross'];
        $result = $this->payrollService->calculateEmployeePay($employee, $override);
        JsonResponse::ok($result);
    }

    // --- DANH SACH NHAN VIEN CHO BANG LUONG ---

    public function listPayrollEmployees(): void
    {
        Auth::requirePermission('payroll', 'read');
        $employees = $this->employeeRepo->findAll();
        $result = array_map(fn($e) => $e->toArray(), $employees);
        JsonResponse::ok($result);
    }

    // --- BANG LUONG CHO DUYET ---

    public function listPendingEntries(): void
    {
        Auth::requirePermission('payroll', 'read');
        $entries = $this->payrollService->findAllEntries();
        $pending = array_filter($entries, fn($e) => $e->getStatus() === 'draft' || $e->getStatus() === 'approved');
        JsonResponse::ok(array_map(fn($e) => $e->toArray(), array_values($pending)));
    }
}

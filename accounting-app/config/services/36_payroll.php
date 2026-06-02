<?php
// TỰ ĐỘNG SINH TỪ services.php — không sửa trực tiếp. Sửa services.php và chạy split script.

use Accounting\Domain\Service\PayrollService;

$payrollService = new PayrollService($payrollEntryRepository, $payrollPeriodRepository, $salaryComponentRepository, $employeeRepository, $journalService, $pdo, $auditLogger);

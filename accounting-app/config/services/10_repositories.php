<?php
// TỰ ĐỘNG SINH TỪ services.php — không sửa trực tiếp. Sửa services.php và chạy split script.

use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Infrastructure\Persistence\PDOItemRepository;
use Accounting\Infrastructure\Persistence\PDOCustomerRepository;
use Accounting\Infrastructure\Persistence\PDOSupplierRepository;
use Accounting\Infrastructure\Persistence\PDOWarehouseRepository;
use Accounting\Infrastructure\Persistence\PDODepartmentRepository;
use Accounting\Infrastructure\Persistence\PDOEmployeeRepository;
use Accounting\Infrastructure\Persistence\PDOUomRepository;
use Accounting\Infrastructure\Persistence\PDOCcdcRepository;
use Accounting\Infrastructure\Persistence\PDOBankAccountRepository;
use Accounting\Infrastructure\Persistence\PDOExchangeRateRepository;
use Accounting\Infrastructure\Persistence\PDOTaxRateRepository;
use Accounting\Infrastructure\Persistence\PDOFixedAssetRepository;
use Accounting\Infrastructure\Persistence\PDOValuationMethodRepository;
use Accounting\Infrastructure\Persistence\PDOContractRepository;
use Accounting\Infrastructure\Persistence\PDOProjectRepository;
use Accounting\Infrastructure\Persistence\PDODepreciationPolicyRepository;
use Accounting\Infrastructure\Persistence\PDOPayrollEntryRepository;
use Accounting\Infrastructure\Persistence\PDOPayrollPeriodRepository;
use Accounting\Infrastructure\Persistence\PDOSalaryComponentRepository;
use Accounting\Infrastructure\Persistence\PDOSalaryFormulaRepository;
use Accounting\Infrastructure\Persistence\PDOPurchaseRequisitionRepository;
use Accounting\Infrastructure\Persistence\PDOPurchaseOrderRepository;
use Accounting\Infrastructure\Persistence\PDOGoodsReceiptRepository;
use Accounting\Infrastructure\Persistence\PDOPurchaseInvoiceMatchRepository;
use Accounting\Infrastructure\Persistence\PDOPurchaseApprovalRepository;
use Accounting\Infrastructure\Persistence\PDOSupplierPerformanceRepository;
use Accounting\Infrastructure\Persistence\PDOPurchaseBudgetRepository;

// === LỚP REPOSITORY: Truy cập dữ liệu qua PDO ===
// Mỗi entity có Repository Interface + PDO Implementation
// Repository pattern: controller/service không biết DB implementation
$accountRepository = new PDOAccountRepository($pdo);
$transactionRepository = new PDOTransactionRepository($pdo);
$itemRepository = new PDOItemRepository($pdo);
$customerRepository = new PDOCustomerRepository($pdo);
$supplierRepository = new PDOSupplierRepository($pdo);
$warehouseRepository = new PDOWarehouseRepository($pdo);
$departmentRepository = new PDODepartmentRepository($pdo);
$employeeRepository = new PDOEmployeeRepository($pdo);
$uomRepository = new PDOUomRepository($pdo);
$ccdcRepository = new PDOCcdcRepository($pdo);
$bankAccountRepository = new PDOBankAccountRepository($pdo);
$exchangeRateRepository = new PDOExchangeRateRepository($pdo);
$taxRateRepository = new PDOTaxRateRepository($pdo);
$fixedAssetRepository = new PDOFixedAssetRepository($pdo);
$valuationMethodRepository = new PDOValuationMethodRepository($pdo);
$contractRepository = new PDOContractRepository($pdo);
$projectRepository = new PDOProjectRepository($pdo);
$depreciationPolicyRepository = new PDODepreciationPolicyRepository($pdo);
$payrollEntryRepository = new PDOPayrollEntryRepository($pdo);
$payrollPeriodRepository = new PDOPayrollPeriodRepository($pdo);
$salaryComponentRepository = new PDOSalaryComponentRepository($pdo);
$salaryFormulaRepository = new PDOSalaryFormulaRepository($pdo);
$purchaseRequisitionRepo = new PDOPurchaseRequisitionRepository($pdo);
$purchaseOrderRepo = new PDOPurchaseOrderRepository($pdo);
$goodsReceiptRepo = new PDOGoodsReceiptRepository($pdo);
$purchaseInvoiceMatchRepo = new PDOPurchaseInvoiceMatchRepository($pdo);
$purchaseApprovalRepo = new PDOPurchaseApprovalRepository($pdo);
$supplierPerformanceRepo = new PDOSupplierPerformanceRepository($pdo);
$purchaseBudgetRepo = new PDOPurchaseBudgetRepository($pdo);

<?php
// TỰ ĐỘNG SINH TỪ services.php — không sửa trực tiếp. Sửa services.php và chạy split script.

use Accounting\Interfaces\HTTP\MasterData\AccountController;
use Accounting\Interfaces\HTTP\ApprovalController;
use Accounting\Interfaces\HTTP\Financial\ApController;
use Accounting\Interfaces\HTTP\Financial\ArController;
use Accounting\Interfaces\HTTP\Auth\AuditLogController;
use Accounting\Interfaces\HTTP\Auth\AuthController;
use Accounting\Interfaces\HTTP\MasterData\BankAccountController;
use Accounting\Interfaces\HTTP\Cash\BankReconciliationController;
use Accounting\Interfaces\HTTP\Cash\CashController;
use Accounting\Interfaces\HTTP\Cash\CashReportController;
use Accounting\Interfaces\HTTP\Cash\PettyCashController;
use Accounting\Interfaces\HTTP\MasterData\CcdcController;
use Accounting\Interfaces\HTTP\MasterData\CcdcAllocationController;
use Accounting\Interfaces\HTTP\Financial\VatController;
use Accounting\Interfaces\HTTP\Financial\CitController;
use Accounting\Interfaces\HTTP\Financial\FctController;
use Accounting\Interfaces\HTTP\Financial\OpeningBalanceController;
use Accounting\Interfaces\HTTP\ReportExportController;
use Accounting\Interfaces\HTTP\ExportController;
use Accounting\Interfaces\HTTP\Inventory\ConsignmentController;
use Accounting\Interfaces\HTTP\MasterData\ContractController;
use Accounting\Interfaces\HTTP\MasterData\CustomerController;
use Accounting\Interfaces\HTTP\MasterData\DepartmentController;
use Accounting\Interfaces\HTTP\MasterData\DepreciationPolicyController;
use Accounting\Interfaces\HTTP\MasterData\EmployeeController;
use Accounting\Interfaces\HTTP\MasterData\ExchangeRateController;
use Accounting\Interfaces\HTTP\MasterData\FixedAssetController;
use Accounting\Interfaces\HTTP\FixedAsset\LifecycleController as FixedAssetLifecycleController;
use Accounting\Interfaces\HTTP\Financial\FsController;
use Accounting\Interfaces\HTTP\Financial\ImportController;
use Accounting\Interfaces\HTTP\Financial\GlController;
use Accounting\Interfaces\HTTP\Financial\JournalBookController;
use Accounting\Interfaces\HTTP\ReconciliationController;
use Accounting\Interfaces\HTTP\FxController;
use Accounting\Interfaces\HTTP\IntercompanyController;
use Accounting\Interfaces\HTTP\Inventory\ImpairmentController;
use Accounting\Interfaces\HTTP\Inventory\InventoryTransitController;
use Accounting\Interfaces\HTTP\Inventory\ItemController;
use Accounting\Interfaces\HTTP\Financial\CorrectionController;
use Accounting\Interfaces\HTTP\Financial\JournalController;
use Accounting\Interfaces\HTTP\Financial\PeriodController;
use Accounting\Interfaces\HTTP\Inventory\PeriodicController;
use Accounting\Interfaces\HTTP\Inventory\PhysicalCountController;
use Accounting\Interfaces\HTTP\MasterData\ProjectController;
use Accounting\Interfaces\HTTP\Inventory\InventoryReportController;
use Accounting\Interfaces\HTTP\Inventory\PromotionalController;
use Accounting\Interfaces\HTTP\Inventory\ReturnToSupplierController;
use Accounting\Interfaces\HTTP\Inventory\WriteOffController;
use Accounting\Interfaces\HTTP\Auth\RoleController;
use Accounting\Interfaces\HTTP\MasterData\SupplierController;
use Accounting\Interfaces\HTTP\MasterData\TaxRateController;
use Accounting\Interfaces\HTTP\Inventory\ReceiptController;
use Accounting\Interfaces\HTTP\Inventory\IssueController;
use Accounting\Interfaces\HTTP\Inventory\CustomerReturnController;
use Accounting\Interfaces\HTTP\Inventory\TransferController;
use Accounting\Interfaces\HTTP\MasterData\UomController;
use Accounting\Interfaces\HTTP\Auth\UserController;
use Accounting\Interfaces\HTTP\MasterData\ValuationMethodController;
use Accounting\Interfaces\HTTP\MasterData\WarehouseController;
use Accounting\Interfaces\HTTP\Payroll\PayrollController;
use Accounting\Interfaces\HTTP\Purchase\ProcurementController;

// === LỚP CONTROLLER: Tiếp nhận request từ Router, gọi Service ===
// Controller KHÔNG chứa business logic — chỉ validate input + format response
// Mỗi controller nhận dependency từ constructor — không dùng static/global trong controller
$accountController = new AccountController($accountService);
$approvalController = new ApprovalController($journalService, $pdo, $approvalRoutingService);
$apController = new ApController($apService);
$arController = new ArController($arService);
$auditLogController = new AuditLogController($pdo);
$authController = new AuthController($pdo);
$bankAccountController = new BankAccountController($bankAccountRepository);
$bankReconciliationController = new BankReconciliationController($bankReconciliationService, $accountRepository);
$cashController = new CashController($cashService, $accountRepository, $pdo);
$cashReportController = new CashReportController($cashReportService);
$pettyCashController = new PettyCashController($pettyCashService);
$ccdcController = new CcdcController($ccdcRepository);
$ccdcAllocationController = new CcdcAllocationController($ccdcAllocationService);
$vatController = new VatController($vatService);
$citController = new CitController($citService);
$fctController = new FctController($fctService);
$openingBalanceController = new OpeningBalanceController($openingBalanceService);
$reportExportController = new ReportExportController($reportExportService, $glService, $fsService);
$consignmentController = new ConsignmentController($inventoryService, $itemRepository, $pdo);
$contractController = new ContractController($contractRepository);
$customerController = new CustomerController($customerRepository);
$departmentController = new DepartmentController($departmentRepository);
$depreciationPolicyController = new DepreciationPolicyController($depreciationPolicyRepository);
$employeeController = new EmployeeController($employeeRepository);
$exchangeRateController = new ExchangeRateController($exchangeRateRepository);
$fixedAssetController = new FixedAssetController($fixedAssetRepository);
$fixedAssetLifecycleController = new FixedAssetLifecycleController($fixedAssetService, $accountRepository, $pdo);
$fsController = new FsController($fsService, $xbrlGenerator);
$importController = new ImportController($importService, $pdo);
$glController = new GlController($glService);
$journalBookController = new JournalBookController($journalBookService);
$reconciliationController = new ReconciliationController($reconciliationService);
$fxController = new FxController($fxRevaluationService);
$intercompanyController = new IntercompanyController($intercompanyService);
$impairmentController = new ImpairmentController($inventoryService, $pdo);
$inventoryTransitController = new InventoryTransitController($inventoryService, $itemRepository, $pdo);
$itemController = new ItemController($itemRepository);
$correctionController = new CorrectionController($journalService);
$journalController = new JournalController($journalService, $accountRepository, $transactionRepository);
$periodController = new PeriodController($periodService);
$periodicController = new PeriodicController($inventoryService, $itemRepository, $pdo);
$physicalCountController = new PhysicalCountController($inventoryService, $itemRepository, $pdo);
$projectController = new ProjectController($projectRepository);
$inventoryReportController = new InventoryReportController($inventoryService);
$promotionalController = new PromotionalController($inventoryService, $itemRepository);
$returnToSupplierController = new ReturnToSupplierController($inventoryService, $itemRepository, $pdo);
$writeOffController = new WriteOffController($inventoryService, $pdo);
$roleController = new RoleController($pdo);
$supplierController = new SupplierController($supplierRepository);
$taxRateController = new TaxRateController($taxRateRepository);
$receiptController = new ReceiptController($inventoryService, $itemRepository, $pdo);
$issueController = new IssueController($inventoryService, $itemRepository, $pdo);
$customerReturnController = new CustomerReturnController($inventoryService, $itemRepository, $pdo);
$transferController = new TransferController($inventoryService, $itemRepository, $warehouseRepository, $pdo);
$uomController = new UomController($uomRepository);
$userController = new UserController($pdo);
$valuationMethodController = new ValuationMethodController($valuationMethodRepository);
$warehouseController = new WarehouseController($warehouseRepository);
$payrollController = new PayrollController($payrollService, $employeeRepository, $payrollPeriodRepository, $payrollEntryRepository);
$procurementController = new ProcurementController($procurementService, $threeWayMatchService, $budgetControlService);

use Accounting\Interfaces\HTTP\Sales\SalesOrderController;
$salesOrderController = new SalesOrderController($salesOrderService, $salesOrderRepository);

// === SUB-LEDGER CONTROLLER ===
use Accounting\Interfaces\HTTP\SubLedgerController;
$subLedgerController = new SubLedgerController($subLedgerService, $reportExportService);

// === BC09 CONTROLLER ===
use Accounting\Interfaces\HTTP\FsNotesController;
$fsNotesController = new FsNotesController($fsNotesService);

// === EXPORT CONTROLLER (Gap 10) ===
// Endpoint duy nhất POST /api/export — nhận format, title, headers, rows, options
// Xuất file với Content-Type và Content-Disposition phù hợp
$exportController = new ExportController($exportService);

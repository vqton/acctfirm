<?php

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
use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Infrastructure\Database\AuditLogger;
use Accounting\Infrastructure\Logging\LoggingPDO;
use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Service\PostingRuleService;
use Accounting\Domain\Service\VoucherService;
use Accounting\Domain\Service\ApprovalRoutingService;
use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Service\CashService;
use Accounting\Domain\Service\CashReportService;
use Accounting\Domain\Service\PettyCashService;
use Accounting\Domain\Service\BankReconciliationService;
use Accounting\Domain\Service\ReconciliationService;
use Accounting\Domain\Service\FxRevaluationService;
use Accounting\Domain\Service\IntercompanyService;
use Accounting\Domain\Service\PeriodService;
use Accounting\Domain\Service\FsService;
use Accounting\Domain\Service\ApService;
use Accounting\Domain\Service\ArService;
use Accounting\Domain\Service\GlService;
use Accounting\Domain\Service\DebtCollectionService;
use Accounting\Domain\Repository\DebtCollectionRepositoryInterface;
use Accounting\Infrastructure\Repository\PDODebtCollectionRepository;
use Accounting\Interfaces\HTTP\Financial\DebtCollectionController;
use Accounting\Domain\Service\JournalBookService;
use Accounting\Domain\Service\FixedAssetService;
use Accounting\Domain\Service\AccountService;
use Accounting\Domain\Service\CcdcAllocationService;
use Accounting\Domain\Service\VatService;
use Accounting\Domain\Service\FctService;
use Accounting\Domain\Service\ReportExportService;
use Accounting\Interfaces\HTTP\MasterData\CcdcAllocationController;
use Accounting\Interfaces\HTTP\Financial\VatController;
use Accounting\Interfaces\HTTP\Financial\FctController;
use Accounting\Interfaces\HTTP\ReportExportController;
use Accounting\Interfaces\HTTP\Cash\BankReconciliationController;
use Accounting\Interfaces\HTTP\Cash\CashController;
use Accounting\Interfaces\HTTP\Cash\CashReportController;
use Accounting\Interfaces\HTTP\Cash\PettyCashController;
use Accounting\Interfaces\HTTP\ApprovalController;
use Accounting\Interfaces\HTTP\Inventory\ConsignmentController;
use Accounting\Interfaces\HTTP\Inventory\ImpairmentController;
use Accounting\Interfaces\HTTP\Inventory\InventoryTransitController;
use Accounting\Interfaces\HTTP\Inventory\ItemController;
use Accounting\Interfaces\HTTP\Inventory\PeriodicController;
use Accounting\Interfaces\HTTP\Inventory\PhysicalCountController;
use Accounting\Interfaces\HTTP\Inventory\PromotionalController;
use Accounting\Interfaces\HTTP\Inventory\ReturnToSupplierController;
use Accounting\Interfaces\HTTP\Inventory\WriteOffController;
use Accounting\Interfaces\HTTP\Inventory\ReceiptController;
use Accounting\Interfaces\HTTP\Inventory\IssueController;
use Accounting\Interfaces\HTTP\Inventory\InventoryReportController;
use Accounting\Interfaces\HTTP\Inventory\CustomerReturnController;
use Accounting\Interfaces\HTTP\Inventory\TransferController;
use Accounting\Interfaces\HTTP\Financial\ApController;
use Accounting\Interfaces\HTTP\Financial\ArController;
use Accounting\Interfaces\HTTP\Financial\FsController;
use Accounting\Interfaces\HTTP\Financial\CorrectionController;
use Accounting\Interfaces\HTTP\Financial\GlController;
use Accounting\Interfaces\HTTP\Financial\JournalBookController;
use Accounting\Interfaces\HTTP\Financial\JournalController;
use Accounting\Interfaces\HTTP\Financial\PeriodController;
use Accounting\Interfaces\HTTP\MasterData\AccountController;
use Accounting\Interfaces\HTTP\MasterData\BankAccountController;
use Accounting\Interfaces\HTTP\MasterData\CcdcController;
use Accounting\Interfaces\HTTP\MasterData\ContractController;
use Accounting\Interfaces\HTTP\MasterData\CustomerController;
use Accounting\Interfaces\HTTP\MasterData\DepartmentController;
use Accounting\Interfaces\HTTP\MasterData\DepreciationPolicyController;
use Accounting\Interfaces\HTTP\MasterData\EmployeeController;
use Accounting\Interfaces\HTTP\MasterData\ExchangeRateController;
use Accounting\Interfaces\HTTP\MasterData\FixedAssetController;
use Accounting\Interfaces\HTTP\FixedAsset\LifecycleController as FixedAssetLifecycleController;
use Accounting\Interfaces\HTTP\MasterData\ProjectController;
use Accounting\Interfaces\HTTP\MasterData\SupplierController;
use Accounting\Interfaces\HTTP\MasterData\TaxRateController;
use Accounting\Interfaces\HTTP\MasterData\UomController;
use Accounting\Interfaces\HTTP\MasterData\ValuationMethodController;
use Accounting\Interfaces\HTTP\MasterData\WarehouseController;
use Accounting\Interfaces\HTTP\Auth\AuditLogController;
use Accounting\Interfaces\HTTP\Auth\AuthController;
use Accounting\Interfaces\HTTP\Auth\RoleController;
use Accounting\Interfaces\HTTP\Auth\UserController;
use Accounting\Interfaces\HTTP\ReconciliationController;
use Accounting\Interfaces\HTTP\FxController;
use Accounting\Interfaces\HTTP\IntercompanyController;
use Accounting\Interfaces\HTTP\Payroll\PayrollController;
use Accounting\Domain\Service\PayrollService;
use Accounting\Infrastructure\Persistence\PDOPayrollEntryRepository;
use Accounting\Infrastructure\Persistence\PDOPayrollPeriodRepository;
use Accounting\Infrastructure\Persistence\PDOSalaryComponentRepository;
use Accounting\Infrastructure\Persistence\PDOSalaryFormulaRepository;
use Accounting\Domain\Service\ProcurementService;
use Accounting\Domain\Service\ThreeWayMatchService;
use Accounting\Domain\Service\BudgetControlService;
use Accounting\Infrastructure\Persistence\PDOPurchaseRequisitionRepository;
use Accounting\Infrastructure\Persistence\PDOPurchaseOrderRepository;
use Accounting\Infrastructure\Persistence\PDOGoodsReceiptRepository;
use Accounting\Infrastructure\Persistence\PDOPurchaseInvoiceMatchRepository;
use Accounting\Infrastructure\Persistence\PDOPurchaseApprovalRepository;
use Accounting\Infrastructure\Persistence\PDOSupplierPerformanceRepository;
use Accounting\Infrastructure\Persistence\PDOPurchaseBudgetRepository;
use Accounting\Interfaces\HTTP\Purchase\ProcurementController;
use Accounting\Infrastructure\EInvoice\XmlInvoiceBuilder;
use Accounting\Infrastructure\EInvoice\DigitalSignatureService;
use Accounting\Infrastructure\EInvoice\VnptEInvoiceGateway;
use Accounting\Domain\Contract\EInvoiceGatewayInterface;
use Accounting\Domain\Service\InvoiceService;
use Accounting\Domain\Service\VatDeclarationEngine;
use Accounting\Domain\Service\CitDeclarationEngine;
use Accounting\Domain\Service\PitDeclarationService;
use Accounting\Interfaces\HTTP\EInvoiceController;
use Accounting\Domain\Service\ExportService;
use Accounting\Domain\Contract\ExportDriverInterface;
use Accounting\Infrastructure\Export\CsvDriver;
use Accounting\Infrastructure\Export\HtmlExcelDriver;
use Accounting\Infrastructure\Export\PurePhpPdfDriver;
use Accounting\Interfaces\HTTP\ExportController;
use Accounting\Domain\Service\ContractService;
use Accounting\Domain\Service\ProjectAccountingService;
use Accounting\Domain\Service\BudgetService;
use Accounting\Domain\Service\ReportBuilderService;
use Accounting\Domain\Service\MenuService;
use Accounting\Domain\Repository\MenuRepositoryInterface;
use Accounting\Infrastructure\Persistence\PDOMenuRepository;
use Accounting\Interfaces\HTTP\MenuController;
use Accounting\Interfaces\HTTP\BudgetController;
use Accounting\Interfaces\HTTP\ReportBuilderController;
use Accounting\Interfaces\HTTP\ContractManagementController;
use Accounting\Interfaces\HTTP\ProjectAccountingController;
use Accounting\Interfaces\HTTP\ManufacturingController;
use Accounting\Domain\Repository\BomRepositoryInterface;
use Accounting\Infrastructure\Persistence\PDOBomRepository;
use Accounting\Domain\Repository\ProductionOrderRepositoryInterface;
use Accounting\Infrastructure\Persistence\PDOProductionOrderRepository;
use Accounting\Domain\Service\GoodsIssueService;
use Accounting\Domain\Service\GoodsReceiptService;
use Accounting\Infrastructure\Persistence\PDOGoodsReceiptLineRepository;
use Accounting\Interfaces\HTTP\Inventory\GoodsReceiptController;
use Accounting\Domain\Service\AdvancePaymentRequestService;
use Accounting\Interfaces\HTTP\Cash\AdvancePaymentRequestController;
use Accounting\Domain\Service\DashboardService;
use Accounting\Interfaces\HTTP\DashboardController;

// Tạo DI container — khởi tạo tất cả service, repository, controller
// Dependency graph:
// PDO ← LoggingPDO ← Repository ← Service ← Controller
// Tất cả module giao tiếp qua JournalService — không module nào ghi trực tiếp vào DB
// Controller chỉ gọi Service — không chứa business logic
function createContainer(): array
{
    require __DIR__ . '/services/00_core.php';
    require __DIR__ . '/services/10_repositories.php';
    require __DIR__ . '/services/20_infrastructure.php';
    require __DIR__ . '/services/30_journal.php';
    require __DIR__ . '/services/31_cash.php';
    require __DIR__ . '/services/32_inventory.php';
    // GoodsIssueService — Mẫu 02-VT (Phiếu xuất kho) theo TT99
    $goodsIssueService = new GoodsIssueService($pdo, $inventoryService, $voucherService, $itemRepository, $auditLogger);
    require __DIR__ . '/services/33_financial.php';
    require __DIR__ . '/services/34_account.php';
    require __DIR__ . '/services/35_debt_collection.php';
    require __DIR__ . '/services/36_payroll.php';
    require __DIR__ . '/services/37_procurement.php';
    require __DIR__ . '/services/25_einvoice.php';
    // === EXPORT CONTROLLER ===

    $contractService = new ContractService($contractRepository, $pdo, $reportExportService);
    $contractManagementController = new ContractManagementController($contractService, $contractRepository);
    $projectAccountingService = new ProjectAccountingService($projectRepository, $pdo, $reportExportService);
    $projectAccountingController = new ProjectAccountingController($projectAccountingService, $projectRepository);

    $bomRepository = new PDOBomRepository($pdo);
    $productionOrderRepository = new PDOProductionOrderRepository($pdo);
    $manufacturingService = new \Accounting\Domain\Service\ManufacturingService($bomRepository, $productionOrderRepository, $pdo, $reportExportService, $journalService);
    $manufacturingController = new \Accounting\Interfaces\HTTP\ManufacturingController($manufacturingService, $bomRepository, $productionOrderRepository);

    $budgetService = new BudgetService($pdo, $reportExportService);
    $budgetController = new BudgetController($budgetService);

    $reportBuilderService = new ReportBuilderService($pdo, $reportExportService);
    $reportBuilderController = new ReportBuilderController($reportBuilderService);

    // === MENU SERVICE ===
    // Quản lý menu điều hướng động — role-based filtering + favorites
    $menuRepository = new PDOMenuRepository($pdo);
    $menuService = new MenuService($menuRepository, $pdo);
    $menuController = new MenuController($menuService, $pdo);

    // === ADVANCE PAYMENT REQUEST SERVICE (Mẫu 03-TT) ===
    $advancePaymentRequestService = new AdvancePaymentRequestService($pdo, $voucherService, $auditLogger, $journalService);
    $advancePaymentRequestController = new AdvancePaymentRequestController($advancePaymentRequestService);

    // TÍCH HỢP: Gắn AdvancePaymentRequestService vào PettyCashService cho disburseFromRequest()
    $pettyCashService->setAdvancePaymentService($advancePaymentRequestService);

    // === GOODS RECEIPT SERVICE (Mẫu 01-VT) ===
    // Phiếu nhập kho — full lifecycle: draft → posted → cancelled
    // Hạch toán: Nợ 15x / Có 331
    $grLineRepo = new PDOGoodsReceiptLineRepository($pdo);
    $goodsReceiptService = new GoodsReceiptService(
        $pdo, $voucherService, $journalService,
        $goodsReceiptRepo, $grLineRepo,
        $itemRepository, $warehouseRepository,
        $auditLogger, $inventoryService
    );
    $goodsReceiptController = new GoodsReceiptController($goodsReceiptService);

    // === E-INVOICE IMPORT: Gắn GoodsReceiptService để auto tạo phiếu nhập kho ===
    if (isset($einvoiceImportService)) {
        $einvoiceImportService->setGoodsReceiptService($goodsReceiptService);
    }

    // === DASHBOARD SERVICE ===
    // Tổng hợp KPI từ nhiều nguồn — cash balance, revenue/expense YTD, pending approvals, trends
    $dashboardService = new DashboardService($accountRepository, $transactionRepository, $pdo);
    $dashboardController = new DashboardController($dashboardService);

    require __DIR__ . '/services/40_controllers.php';

    // === CONTAINER: Map tên → instance ===
    // Container là array $GLOBALS['container'], controller/service lấy nhau qua key
    // Pattern: $c['AccountController'] thay vì new AccountController(...) — lazy loading tương đối
    // LƯU Ý: Nếu cần thêm service mới, phải khởi tạo ở đây và thêm vào return array
    return [
        'pdo' => $pdo, 'accountRepository' => $accountRepository,
        'transactionRepository' => $transactionRepository,
        'itemRepository' => $itemRepository, 'customerRepository' => $customerRepository,
        'supplierRepository' => $supplierRepository, 'warehouseRepository' => $warehouseRepository,
        'departmentRepository' => $departmentRepository, 'employeeRepository' => $employeeRepository,
        'uomRepository' => $uomRepository, 'ccdcRepository' => $ccdcRepository,
        'bankAccountRepository' => $bankAccountRepository,
        'exchangeRateRepository' => $exchangeRateRepository,
        'taxRateRepository' => $taxRateRepository, 'fixedAssetRepository' => $fixedAssetRepository,
        'valuationMethodRepository' => $valuationMethodRepository,
        'contractRepository' => $contractRepository, 'projectRepository' => $projectRepository,
        'depreciationPolicyRepository' => $depreciationPolicyRepository,
        'auditLogger' => $auditLogger,
        'journalService' => $journalService,
        'inventoryService' => $inventoryService,
        'goodsIssueService' => $goodsIssueService,
        'cashService' => $cashService,
        'pettyCashService' => $pettyCashService,
        'bankReconciliationService' => $bankReconciliationService,
        'cashReportService' => $cashReportService,
        'periodService' => $periodService,
        'fsService' => $fsService,
        'apService' => $apService,
        'arService' => $arService,
        'glService' => $glService,
        'journalBookService' => $journalBookService,
        'fixedAssetService' => $fixedAssetService,
        'depreciationBatchService' => $depreciationBatchService,
        'DepreciationReportController' => $depreciationReportController,
        'citService' => $citService,
        'openingBalanceService' => $openingBalanceService,

        'AccountController' => $accountController,
        'ApprovalController' => $approvalController,
        'ApController' => $apController,
        'ArController' => $arController,
        'AuditLogController' => $auditLogController,
        'AuthController' => $authController,
        'BankAccountController' => $bankAccountController,
        'BankReconciliationController' => $bankReconciliationController,
        'CashController' => $cashController,
        'CashReportController' => $cashReportController,
        'PettyCashController' => $pettyCashController,
        'AdvancePaymentRequestController' => $advancePaymentRequestController,
        'advancePaymentRequestService' => $advancePaymentRequestService,
        'CcdcController' => $ccdcController,
        'CcdcAllocationController' => $ccdcAllocationController,
        'CitController' => $citController,
        'FctController' => $fctController,
        'OpeningBalanceController' => $openingBalanceController,
        'VatController' => $vatController,
        'ReportExportController' => $reportExportController,
        'ConsignmentController' => $consignmentController,
        'ContractController' => $contractController,
        'ContractManagementController' => $contractManagementController,
        'contractService' => $contractService,
        'ProjectAccountingController' => $projectAccountingController,
        'projectAccountingService' => $projectAccountingService,
        'ManufacturingController' => $manufacturingController,
        'manufacturingService' => $manufacturingService,
        'bomRepository' => $bomRepository,
        'productionOrderRepository' => $productionOrderRepository,
        'BudgetController' => $budgetController,
        'budgetService' => $budgetService,
        'CustomerController' => $customerController,
        'DepartmentController' => $departmentController,
        'DepreciationPolicyController' => $depreciationPolicyController,
        'EmployeeController' => $employeeController,
        'ExchangeRateController' => $exchangeRateController,
        'FixedAssetController' => $fixedAssetController,
        'FixedAssetLifecycleController' => $fixedAssetLifecycleController,
        'FsController' => $fsController,
        'FsNotesController' => $fsNotesController,
        'ExportController' => $exportController,
        'ImportController' => $importController,
        'CurrencyController' => $currencyController,
        'NotificationController' => $notificationController,
        'PrintTemplateController' => $printTemplateController,
        'GlController' => $glController,
        'JournalBookController' => $journalBookController,
        'ReconciliationController' => $reconciliationController,
        'FxController' => $fxController,
        'IntercompanyController' => $intercompanyController,
        'ImpairmentController' => $impairmentController,
        'InventoryTransitController' => $inventoryTransitController,
        'ItemController' => $itemController,
        'CorrectionController' => $correctionController,
        'JournalController' => $journalController,
        'PeriodController' => $periodController,
        'PeriodicController' => $periodicController,
        'PhysicalCountController' => $physicalCountController,
        'ProjectController' => $projectController,
        'PromotionalController' => $promotionalController,
        'ReturnToSupplierController' => $returnToSupplierController,
        'WriteOffController' => $writeOffController,
        'ReceiptController' => $receiptController,
        'IssueController' => $issueController,
        'InventoryReportController' => $inventoryReportController,
        'CustomerReturnController' => $customerReturnController,
        'RoleController' => $roleController,
        'SupplierController' => $supplierController,
        'TaxRateController' => $taxRateController,
        'TransferController' => $transferController,
        'UomController' => $uomController,
        'UserController' => $userController,
        'ValuationMethodController' => $valuationMethodController,
        'WarehouseController' => $warehouseController,
        'PayrollController' => $payrollController,
        'payrollService' => $payrollService,
        'procurementService' => $procurementService,
        'threeWayMatchService' => $threeWayMatchService,
        'budgetControlService' => $budgetControlService,
        'debtCollectionService' => $debtCollectionService,
        'debtCollectionRepo' => $debtCollectionRepo,
        'DebtCollectionController' => $debtCollectionController,
        'payrollEntryRepository' => $payrollEntryRepository,
        'payrollPeriodRepository' => $payrollPeriodRepository,
        'salaryComponentRepository' => $salaryComponentRepository,
        'salaryFormulaRepository' => $salaryFormulaRepository,
        'purchaseRequisitionRepo' => $purchaseRequisitionRepo,
        'purchaseOrderRepo' => $purchaseOrderRepo,
        'goodsReceiptRepo' => $goodsReceiptRepo,
        'purchaseInvoiceMatchRepo' => $purchaseInvoiceMatchRepo,
        'purchaseApprovalRepo' => $purchaseApprovalRepo,
        'supplierPerformanceRepo' => $supplierPerformanceRepo,
        'purchaseBudgetRepo' => $purchaseBudgetRepo,
        'ProcurementController' => $procurementController,
        'salesOrderRepository' => $salesOrderRepository,
        'salesOrderService' => $salesOrderService,
        'SalesOrderController' => $salesOrderController,
        'xmlBuilder' => $xmlBuilder,
        'digitalSignatureService' => $digitalSignatureService,
        'einvoiceGateway' => $einvoiceGateway,
        'invoiceService' => $invoiceService,
        'vatDeclarationEngine' => $vatDeclarationEngine,
        'citDeclarationEngine' => $citDeclarationEngine,
        'pitDeclarationService' => $pitDeclarationService,
        'EInvoiceController' => $einvoiceController,
        'einvoiceImportService' => $einvoiceImportService,
        'EInvoiceImportController' => $einvoiceImportController,
        'subLedgerService' => $subLedgerService,
        'SubLedgerController' => $subLedgerController,
        'ReportBuilderController' => $reportBuilderController,
        'reportBuilderService' => $reportBuilderService,
        'menuRepository' => $menuRepository,
        'menuService' => $menuService,
        'MenuController' => $menuController,
        'dashboardService' => $dashboardService,
        'DashboardController' => $dashboardController,
    ];
}

$container = createContainer();
$GLOBALS['container'] = $container;

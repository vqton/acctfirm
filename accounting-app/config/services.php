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
use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Service\CashService;
use Accounting\Domain\Service\CashReportService;
use Accounting\Domain\Service\PettyCashService;
use Accounting\Domain\Service\BankReconciliationService;
use Accounting\Domain\Service\PeriodService;
use Accounting\Domain\Service\FsService;
use Accounting\Domain\Service\ApService;
use Accounting\Domain\Service\ArService;
use Accounting\Domain\Service\GlService;
use Accounting\Domain\Service\JournalBookService;
use Accounting\Domain\Service\FixedAssetService;
use Accounting\Interfaces\HTTP\Cash\BankReconciliationController;
use Accounting\Interfaces\HTTP\Cash\CashController;
use Accounting\Interfaces\HTTP\Cash\CashReportController;
use Accounting\Interfaces\HTTP\Cash\PettyCashController;
use Accounting\Interfaces\HTTP\Inventory\ConsignmentController;
use Accounting\Interfaces\HTTP\Inventory\ImpairmentController;
use Accounting\Interfaces\HTTP\Inventory\InventoryTransitController;
use Accounting\Interfaces\HTTP\Inventory\ItemController;
use Accounting\Interfaces\HTTP\Inventory\PeriodicController;
use Accounting\Interfaces\HTTP\Inventory\PhysicalCountController;
use Accounting\Interfaces\HTTP\Inventory\PromotionalController;
use Accounting\Interfaces\HTTP\Inventory\ReceiptController;
use Accounting\Interfaces\HTTP\Inventory\IssueController;
use Accounting\Interfaces\HTTP\Inventory\CustomerReturnController;
use Accounting\Interfaces\HTTP\Inventory\TransferController;
use Accounting\Interfaces\HTTP\Financial\ApController;
use Accounting\Interfaces\HTTP\Financial\ArController;
use Accounting\Interfaces\HTTP\Financial\FsController;
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

function createContainer(): array
{
    $dbConfig = require __DIR__ . '/database.php';
    $innerPdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'], $dbConfig['password'], $dbConfig['options']
    );
    $pdo = new LoggingPDO($innerPdo);

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

    $auditLogger = new AuditLogger($pdo);

    $journalService = new JournalService($accountRepository, $transactionRepository, $pdo, $auditLogger);
    $inventoryService = new InventoryService($accountRepository, $transactionRepository, $itemRepository, $warehouseRepository, $journalService, $pdo);
    $cashService = new CashService($accountRepository, $transactionRepository, $journalService, $pdo);
    $pettyCashService = new PettyCashService($accountRepository, $transactionRepository, $journalService, $pdo);
    $bankReconciliationService = new BankReconciliationService($accountRepository, $transactionRepository, $journalService, $pdo, $auditLogger);
    $cashReportService = new CashReportService($pdo, $accountRepository);
    $periodService = new PeriodService($pdo, $accountRepository, $transactionRepository, $journalService, $auditLogger);
    $fsService = new FsService($pdo, $accountRepository, $auditLogger);
    $apService = new ApService($pdo, $supplierRepository, $accountRepository, $journalService, $auditLogger);
    $arService = new ArService($pdo, $accountRepository, $journalService, $auditLogger);
    $glService = new GlService($pdo, $accountRepository);
    $journalBookService = new JournalBookService($pdo);
    $fixedAssetService = new FixedAssetService($fixedAssetRepository, $accountRepository, $transactionRepository, $journalService, $pdo, $auditLogger);

    $accountController = new AccountController($accountRepository, $auditLogger);
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
    $consignmentController = new ConsignmentController($inventoryService, $itemRepository, $pdo);
    $contractController = new ContractController($contractRepository);
    $customerController = new CustomerController($customerRepository);
    $departmentController = new DepartmentController($departmentRepository);
    $depreciationPolicyController = new DepreciationPolicyController($depreciationPolicyRepository);
    $employeeController = new EmployeeController($employeeRepository);
    $exchangeRateController = new ExchangeRateController($exchangeRateRepository);
    $fixedAssetController = new FixedAssetController($fixedAssetRepository);
    $fsController = new FsController($fsService);
    $glController = new GlController($glService);
    $journalBookController = new JournalBookController($journalBookService);
    $impairmentController = new ImpairmentController($inventoryService, $pdo);
    $inventoryTransitController = new InventoryTransitController($inventoryService, $itemRepository, $pdo);
    $itemController = new ItemController($itemRepository);
    $journalController = new JournalController($journalService, $accountRepository, $transactionRepository);
    $periodController = new PeriodController($periodService);
    $periodicController = new PeriodicController($inventoryService, $itemRepository, $pdo);
    $physicalCountController = new PhysicalCountController($inventoryService, $itemRepository, $pdo);
    $projectController = new ProjectController($projectRepository);
    $promotionalController = new PromotionalController($inventoryService, $itemRepository);
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

        'AccountController' => $accountController,
        'ApController' => $apController,
        'ArController' => $arController,
        'AuditLogController' => $auditLogController,
        'AuthController' => $authController,
        'BankAccountController' => $bankAccountController,
        'BankReconciliationController' => $bankReconciliationController,
        'CashController' => $cashController,
        'CashReportController' => $cashReportController,
        'PettyCashController' => $pettyCashController,
        'CcdcController' => $ccdcController,
        'ConsignmentController' => $consignmentController,
        'ContractController' => $contractController,
        'CustomerController' => $customerController,
        'DepartmentController' => $departmentController,
        'DepreciationPolicyController' => $depreciationPolicyController,
        'EmployeeController' => $employeeController,
        'ExchangeRateController' => $exchangeRateController,
        'FixedAssetController' => $fixedAssetController,
        'FsController' => $fsController,
        'GlController' => $glController,
        'JournalBookController' => $journalBookController,
        'ImpairmentController' => $impairmentController,
        'InventoryTransitController' => $inventoryTransitController,
        'ItemController' => $itemController,
        'JournalController' => $journalController,
        'PeriodController' => $periodController,
        'PeriodicController' => $periodicController,
        'PhysicalCountController' => $physicalCountController,
        'ProjectController' => $projectController,
        'PromotionalController' => $promotionalController,
        'ReceiptController' => $receiptController,
        'IssueController' => $issueController,
        'CustomerReturnController' => $customerReturnController,
        'RoleController' => $roleController,
        'SupplierController' => $supplierController,
        'TaxRateController' => $taxRateController,
        'TransferController' => $transferController,
        'UomController' => $uomController,
        'UserController' => $userController,
        'ValuationMethodController' => $valuationMethodController,
        'WarehouseController' => $warehouseController,
    ];
}

$container = createContainer();
$GLOBALS['container'] = $container;
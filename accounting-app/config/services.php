<?php

use Accounting\Infrastructure\Repository\PDOAccountRepository;
use Accounting\Infrastructure\Repository\PDOTransactionRepository;
use Accounting\Infrastructure\Repository\PDOItemRepository;
use Accounting\Infrastructure\Repository\PDOCustomerRepository;
use Accounting\Infrastructure\Repository\PDOSupplierRepository;
use Accounting\Infrastructure\Repository\PDOWarehouseRepository;
use Accounting\Infrastructure\Repository\PDODepartmentRepository;
use Accounting\Infrastructure\Repository\PDOEmployeeRepository;
use Accounting\Infrastructure\Repository\PDOUomRepository;
use Accounting\Infrastructure\Repository\PDOCcdcRepository;
use Accounting\Infrastructure\Repository\PDOBankAccountRepository;
use Accounting\Infrastructure\Repository\PDOExchangeRateRepository;
use Accounting\Infrastructure\Repository\PDOTaxRateRepository;
use Accounting\Infrastructure\Repository\PDOFixedAssetRepository;
use Accounting\Infrastructure\Repository\PDOValuationMethodRepository;
use Accounting\Infrastructure\Repository\PDOContractRepository;
use Accounting\Infrastructure\Repository\PDOProjectRepository;
use Accounting\Infrastructure\Repository\PDODepreciationPolicyRepository;
use Accounting\Domain\Service\AccountingService;
use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Service\CashService;
use Accounting\Domain\Service\CashReportService;
use Accounting\Domain\Service\BankReconciliationService;
use Accounting\Domain\Service\PeriodService;
use Accounting\Domain\Service\FsService;
use Accounting\Domain\Service\ApService;
use Accounting\Domain\Service\ArService;

function createContainer(): array
{
    $dbConfig = require __DIR__ . '/database.php';
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'], $dbConfig['password'], $dbConfig['options']
    );

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

    $accountingService = new AccountingService($accountRepository, $transactionRepository);
    $journalService = new JournalService($accountRepository, $transactionRepository);
    $inventoryService = new InventoryService($accountRepository, $transactionRepository, $itemRepository, $warehouseRepository);
    $cashService = new CashService($accountRepository, $transactionRepository, $pdo);
    $bankReconciliationService = new BankReconciliationService($accountRepository, $transactionRepository, $pdo);
    $cashReportService = new CashReportService($pdo, $accountRepository);
    $periodService = new PeriodService($pdo, $accountRepository, $transactionRepository);
    $fsService = new FsService($pdo, $accountRepository);
    $apService = new ApService($pdo, $supplierRepository, $accountRepository);
    $arService = new ArService($pdo, $accountRepository);

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
        'accountingService' => $accountingService, 'journalService' => $journalService,
        'inventoryService' => $inventoryService,
        'cashService' => $cashService,
        'bankReconciliationService' => $bankReconciliationService,
        'cashReportService' => $cashReportService,
        'periodService' => $periodService,
        'fsService' => $fsService,
        'apService' => $apService,
        'arService' => $arService
    ];
}

$container = createContainer();
$GLOBALS['container'] = $container;
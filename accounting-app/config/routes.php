<?php

use Accounting\Interfaces\HTTP\Router;

function defineRoutes(Router $router): void
{
    // Auth
    $router->get('/dang-nhap', function() { require __DIR__ . '/../public/views/login.php'; });
    $router->post('/api/auth/login', function() { (new \Accounting\Interfaces\HTTP\AuthController($GLOBALS['container']['pdo']))->login(); });
    $router->post('/api/auth/logout', function() { (new \Accounting\Interfaces\HTTP\AuthController($GLOBALS['container']['pdo']))->logout(); });
    $router->get('/api/auth/me', function() { (new \Accounting\Interfaces\HTTP\AuthController($GLOBALS['container']['pdo']))->me(); });

    // User management
    $router->get('/api/users', function() { (new \Accounting\Interfaces\HTTP\UserController($GLOBALS['container']['pdo']))->list(); });
    $router->post('/api/users', function() { (new \Accounting\Interfaces\HTTP\UserController($GLOBALS['container']['pdo']))->create(); });
    $router->put('/api/users/{id}', function($id) { (new \Accounting\Interfaces\HTTP\UserController($GLOBALS['container']['pdo']))->update($id); });
    $router->delete('/api/users/{id}', function($id) { (new \Accounting\Interfaces\HTTP\UserController($GLOBALS['container']['pdo']))->delete($id); });

    // Role management
    $router->get('/api/roles', function() { (new \Accounting\Interfaces\HTTP\RoleController($GLOBALS['container']['pdo']))->list(); });
    $router->post('/api/roles', function() { (new \Accounting\Interfaces\HTTP\RoleController($GLOBALS['container']['pdo']))->create(); });
    $router->put('/api/roles/{id}', function($id) { (new \Accounting\Interfaces\HTTP\RoleController($GLOBALS['container']['pdo']))->update($id); });
    $router->delete('/api/roles/{id}', function($id) { (new \Accounting\Interfaces\HTTP\RoleController($GLOBALS['container']['pdo']))->delete($id); });
    $router->get('/api/roles/{id}/permissions', function($id) { (new \Accounting\Interfaces\HTTP\RoleController($GLOBALS['container']['pdo']))->getPermissions($id); });
    $router->put('/api/roles/{id}/permissions', function($id) { (new \Accounting\Interfaces\HTTP\RoleController($GLOBALS['container']['pdo']))->updatePermissions($id); });
    $router->get('/api/user-management/users', function() { (new \Accounting\Interfaces\HTTP\UserController($GLOBALS['container']['pdo']))->listWithRoles(); });

    // Frontend pages
    $router->get('/', function() { require __DIR__ . '/../public/views/dashboard.php'; });
    $router->get('/danh-muc/vat-tu', function() { require __DIR__ . '/../public/views/items.php'; });
    $router->get('/danh-muc/khach-hang', function() { require __DIR__ . '/../public/views/customers.php'; });
    $router->get('/danh-muc/nha-cung-cap', function() { require __DIR__ . '/../public/views/suppliers.php'; });

    // Item API
    $router->get('/api/items', function() { (new \Accounting\Interfaces\HTTP\ItemController($GLOBALS['container']['itemRepository']))->list(); });
    $router->get('/api/items/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ItemController($GLOBALS['container']['itemRepository']))->get($id); });
    $router->post('/api/items', function() { (new \Accounting\Interfaces\HTTP\ItemController($GLOBALS['container']['itemRepository']))->create(); });
    $router->put('/api/items/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ItemController($GLOBALS['container']['itemRepository']))->update($id); });
    $router->delete('/api/items/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ItemController($GLOBALS['container']['itemRepository']))->delete($id); });

    // Customer API
    $router->get('/api/customers', function() { (new \Accounting\Interfaces\HTTP\CustomerController($GLOBALS['container']['customerRepository']))->list(); });
    $router->get('/api/customers/{id}', function($id) { (new \Accounting\Interfaces\HTTP\CustomerController($GLOBALS['container']['customerRepository']))->get($id); });
    $router->post('/api/customers', function() { (new \Accounting\Interfaces\HTTP\CustomerController($GLOBALS['container']['customerRepository']))->create(); });
    $router->put('/api/customers/{id}', function($id) { (new \Accounting\Interfaces\HTTP\CustomerController($GLOBALS['container']['customerRepository']))->update($id); });
    $router->delete('/api/customers/{id}', function($id) { (new \Accounting\Interfaces\HTTP\CustomerController($GLOBALS['container']['customerRepository']))->delete($id); });

    // Supplier API
    $router->get('/api/suppliers', function() { (new \Accounting\Interfaces\HTTP\SupplierController($GLOBALS['container']['supplierRepository']))->list(); });
    $router->get('/api/suppliers/{id}', function($id) { (new \Accounting\Interfaces\HTTP\SupplierController($GLOBALS['container']['supplierRepository']))->get($id); });
    $router->post('/api/suppliers', function() { (new \Accounting\Interfaces\HTTP\SupplierController($GLOBALS['container']['supplierRepository']))->create(); });
    $router->put('/api/suppliers/{id}', function($id) { (new \Accounting\Interfaces\HTTP\SupplierController($GLOBALS['container']['supplierRepository']))->update($id); });
    $router->delete('/api/suppliers/{id}', function($id) { (new \Accounting\Interfaces\HTTP\SupplierController($GLOBALS['container']['supplierRepository']))->delete($id); });

    // Frontend pages
    $router->get('/danh-muc/kho', function() { require __DIR__ . '/../public/views/warehouses.php'; });
    $router->get('/danh-muc/phong-ban', function() { require __DIR__ . '/../public/views/departments.php'; });
    $router->get('/danh-muc/nhan-vien', function() { require __DIR__ . '/../public/views/employees.php'; });

    // Warehouse API
    $router->get('/api/warehouses', function() { (new \Accounting\Interfaces\HTTP\WarehouseController($GLOBALS['container']['warehouseRepository']))->list(); });
    $router->get('/api/warehouses/{id}', function($id) { (new \Accounting\Interfaces\HTTP\WarehouseController($GLOBALS['container']['warehouseRepository']))->get($id); });
    $router->post('/api/warehouses', function() { (new \Accounting\Interfaces\HTTP\WarehouseController($GLOBALS['container']['warehouseRepository']))->create(); });
    $router->put('/api/warehouses/{id}', function($id) { (new \Accounting\Interfaces\HTTP\WarehouseController($GLOBALS['container']['warehouseRepository']))->update($id); });
    $router->delete('/api/warehouses/{id}', function($id) { (new \Accounting\Interfaces\HTTP\WarehouseController($GLOBALS['container']['warehouseRepository']))->delete($id); });

    // Department API
    $router->get('/api/departments', function() { (new \Accounting\Interfaces\HTTP\DepartmentController($GLOBALS['container']['departmentRepository']))->list(); });
    $router->get('/api/departments/{id}', function($id) { (new \Accounting\Interfaces\HTTP\DepartmentController($GLOBALS['container']['departmentRepository']))->get($id); });
    $router->post('/api/departments', function() { (new \Accounting\Interfaces\HTTP\DepartmentController($GLOBALS['container']['departmentRepository']))->create(); });
    $router->put('/api/departments/{id}', function($id) { (new \Accounting\Interfaces\HTTP\DepartmentController($GLOBALS['container']['departmentRepository']))->update($id); });
    $router->delete('/api/departments/{id}', function($id) { (new \Accounting\Interfaces\HTTP\DepartmentController($GLOBALS['container']['departmentRepository']))->delete($id); });

    // Employee API
    $router->get('/api/employees', function() { (new \Accounting\Interfaces\HTTP\EmployeeController($GLOBALS['container']['employeeRepository']))->list(); });
    $router->get('/api/employees/{id}', function($id) { (new \Accounting\Interfaces\HTTP\EmployeeController($GLOBALS['container']['employeeRepository']))->get($id); });
    $router->post('/api/employees', function() { (new \Accounting\Interfaces\HTTP\EmployeeController($GLOBALS['container']['employeeRepository']))->create(); });
    $router->put('/api/employees/{id}', function($id) { (new \Accounting\Interfaces\HTTP\EmployeeController($GLOBALS['container']['employeeRepository']))->update($id); });
    $router->delete('/api/employees/{id}', function($id) { (new \Accounting\Interfaces\HTTP\EmployeeController($GLOBALS['container']['employeeRepository']))->delete($id); });

    // UOM
    $router->get('/danh-muc/don-vi-tinh', function() { require __DIR__ . '/../public/views/uoms.php'; });
    $router->get('/api/uoms', function() { (new \Accounting\Interfaces\HTTP\UomController($GLOBALS['container']['uomRepository']))->list(); });
    $router->get('/api/uoms/{id}', function($id) { (new \Accounting\Interfaces\HTTP\UomController($GLOBALS['container']['uomRepository']))->get($id); });
    $router->post('/api/uoms', function() { (new \Accounting\Interfaces\HTTP\UomController($GLOBALS['container']['uomRepository']))->create(); });
    $router->put('/api/uoms/{id}', function($id) { (new \Accounting\Interfaces\HTTP\UomController($GLOBALS['container']['uomRepository']))->update($id); });
    $router->delete('/api/uoms/{id}', function($id) { (new \Accounting\Interfaces\HTTP\UomController($GLOBALS['container']['uomRepository']))->delete($id); });

    // CCDC
    $router->get('/danh-muc/cong-cu-dung-cu', function() { require __DIR__ . '/../public/views/ccdc.php'; });
    $router->get('/api/ccdc', function() { (new \Accounting\Interfaces\HTTP\CcdcController($GLOBALS['container']['ccdcRepository']))->list(); });
    $router->get('/api/ccdc/{id}', function($id) { (new \Accounting\Interfaces\HTTP\CcdcController($GLOBALS['container']['ccdcRepository']))->get($id); });
    $router->post('/api/ccdc', function() { (new \Accounting\Interfaces\HTTP\CcdcController($GLOBALS['container']['ccdcRepository']))->create(); });
    $router->put('/api/ccdc/{id}', function($id) { (new \Accounting\Interfaces\HTTP\CcdcController($GLOBALS['container']['ccdcRepository']))->update($id); });
    $router->delete('/api/ccdc/{id}', function($id) { (new \Accounting\Interfaces\HTTP\CcdcController($GLOBALS['container']['ccdcRepository']))->delete($id); });

    // Bank Accounts
    $router->get('/danh-muc/tai-khoan-ngan-hang', function() { require __DIR__ . '/../public/views/bank_accounts.php'; });
    $router->get('/api/bank-accounts', function() { (new \Accounting\Interfaces\HTTP\BankAccountController($GLOBALS['container']['bankAccountRepository']))->list(); });
    $router->get('/api/bank-accounts/{id}', function($id) { (new \Accounting\Interfaces\HTTP\BankAccountController($GLOBALS['container']['bankAccountRepository']))->get($id); });
    $router->post('/api/bank-accounts', function() { (new \Accounting\Interfaces\HTTP\BankAccountController($GLOBALS['container']['bankAccountRepository']))->create(); });
    $router->put('/api/bank-accounts/{id}', function($id) { (new \Accounting\Interfaces\HTTP\BankAccountController($GLOBALS['container']['bankAccountRepository']))->update($id); });
    $router->delete('/api/bank-accounts/{id}', function($id) { (new \Accounting\Interfaces\HTTP\BankAccountController($GLOBALS['container']['bankAccountRepository']))->delete($id); });

    // Exchange Rates
    $router->get('/danh-muc/ty-gia', function() { require __DIR__ . '/../public/views/exchange_rates.php'; });
    $router->get('/api/exchange-rates', function() { (new \Accounting\Interfaces\HTTP\ExchangeRateController($GLOBALS['container']['exchangeRateRepository']))->list(); });
    $router->get('/api/exchange-rates/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ExchangeRateController($GLOBALS['container']['exchangeRateRepository']))->get($id); });
    $router->post('/api/exchange-rates', function() { (new \Accounting\Interfaces\HTTP\ExchangeRateController($GLOBALS['container']['exchangeRateRepository']))->create(); });
    $router->put('/api/exchange-rates/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ExchangeRateController($GLOBALS['container']['exchangeRateRepository']))->update($id); });
    $router->delete('/api/exchange-rates/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ExchangeRateController($GLOBALS['container']['exchangeRateRepository']))->delete($id); });

    // Tax Rates
    $router->get('/danh-muc/bieu-thue', function() { require __DIR__ . '/../public/views/tax_rates.php'; });
    $router->get('/api/tax-rates', function() { (new \Accounting\Interfaces\HTTP\TaxRateController($GLOBALS['container']['taxRateRepository']))->list(); });
    $router->get('/api/tax-rates/{id}', function($id) { (new \Accounting\Interfaces\HTTP\TaxRateController($GLOBALS['container']['taxRateRepository']))->get($id); });
    $router->post('/api/tax-rates', function() { (new \Accounting\Interfaces\HTTP\TaxRateController($GLOBALS['container']['taxRateRepository']))->create(); });
    $router->put('/api/tax-rates/{id}', function($id) { (new \Accounting\Interfaces\HTTP\TaxRateController($GLOBALS['container']['taxRateRepository']))->update($id); });
    $router->delete('/api/tax-rates/{id}', function($id) { (new \Accounting\Interfaces\HTTP\TaxRateController($GLOBALS['container']['taxRateRepository']))->delete($id); });

    // Fixed Assets
    $router->get('/danh-muc/tai-san-co-dinh', function() { require __DIR__ . '/../public/views/fixed_assets.php'; });
    $router->get('/api/fixed-assets', function() { (new \Accounting\Interfaces\HTTP\FixedAssetController($GLOBALS['container']['fixedAssetRepository']))->list(); });
    $router->get('/api/fixed-assets/{id}', function($id) { (new \Accounting\Interfaces\HTTP\FixedAssetController($GLOBALS['container']['fixedAssetRepository']))->get($id); });
    $router->post('/api/fixed-assets', function() { (new \Accounting\Interfaces\HTTP\FixedAssetController($GLOBALS['container']['fixedAssetRepository']))->create(); });
    $router->put('/api/fixed-assets/{id}', function($id) { (new \Accounting\Interfaces\HTTP\FixedAssetController($GLOBALS['container']['fixedAssetRepository']))->update($id); });
    $router->delete('/api/fixed-assets/{id}', function($id) { (new \Accounting\Interfaces\HTTP\FixedAssetController($GLOBALS['container']['fixedAssetRepository']))->delete($id); });

    // Valuation Methods
    $router->get('/danh-muc/phuong-phap-tinh-gia', function() { require __DIR__ . '/../public/views/valuation_methods.php'; });
    $router->get('/api/valuation-methods', function() { (new \Accounting\Interfaces\HTTP\ValuationMethodController($GLOBALS['container']['valuationMethodRepository']))->list(); });
    $router->get('/api/valuation-methods/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ValuationMethodController($GLOBALS['container']['valuationMethodRepository']))->get($id); });
    $router->post('/api/valuation-methods', function() { (new \Accounting\Interfaces\HTTP\ValuationMethodController($GLOBALS['container']['valuationMethodRepository']))->create(); });
    $router->put('/api/valuation-methods/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ValuationMethodController($GLOBALS['container']['valuationMethodRepository']))->update($id); });
    $router->delete('/api/valuation-methods/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ValuationMethodController($GLOBALS['container']['valuationMethodRepository']))->delete($id); });

    // Contracts
    $router->get('/danh-muc/hop-dong', function() { require __DIR__ . '/../public/views/contracts.php'; });
    $router->get('/api/contracts', function() { (new \Accounting\Interfaces\HTTP\ContractController($GLOBALS['container']['contractRepository']))->list(); });
    $router->get('/api/contracts/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ContractController($GLOBALS['container']['contractRepository']))->get($id); });
    $router->post('/api/contracts', function() { (new \Accounting\Interfaces\HTTP\ContractController($GLOBALS['container']['contractRepository']))->create(); });
    $router->put('/api/contracts/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ContractController($GLOBALS['container']['contractRepository']))->update($id); });
    $router->delete('/api/contracts/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ContractController($GLOBALS['container']['contractRepository']))->delete($id); });

    // Projects
    $router->get('/danh-muc/du-an', function() { require __DIR__ . '/../public/views/projects.php'; });
    $router->get('/api/projects', function() { (new \Accounting\Interfaces\HTTP\ProjectController($GLOBALS['container']['projectRepository']))->list(); });
    $router->get('/api/projects/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ProjectController($GLOBALS['container']['projectRepository']))->get($id); });
    $router->post('/api/projects', function() { (new \Accounting\Interfaces\HTTP\ProjectController($GLOBALS['container']['projectRepository']))->create(); });
    $router->put('/api/projects/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ProjectController($GLOBALS['container']['projectRepository']))->update($id); });
    $router->delete('/api/projects/{id}', function($id) { (new \Accounting\Interfaces\HTTP\ProjectController($GLOBALS['container']['projectRepository']))->delete($id); });

    // Depreciation Policies
    $router->get('/danh-muc/chinh-sach-khau-hao', function() { require __DIR__ . '/../public/views/depreciation_policies.php'; });
    $router->get('/api/depreciation-policies', function() { (new \Accounting\Interfaces\HTTP\DepreciationPolicyController($GLOBALS['container']['depreciationPolicyRepository']))->list(); });
    $router->get('/api/depreciation-policies/{id}', function($id) { (new \Accounting\Interfaces\HTTP\DepreciationPolicyController($GLOBALS['container']['depreciationPolicyRepository']))->get($id); });
    $router->post('/api/depreciation-policies', function() { (new \Accounting\Interfaces\HTTP\DepreciationPolicyController($GLOBALS['container']['depreciationPolicyRepository']))->create(); });
    $router->put('/api/depreciation-policies/{id}', function($id) { (new \Accounting\Interfaces\HTTP\DepreciationPolicyController($GLOBALS['container']['depreciationPolicyRepository']))->update($id); });
    $router->delete('/api/depreciation-policies/{id}', function($id) { (new \Accounting\Interfaces\HTTP\DepreciationPolicyController($GLOBALS['container']['depreciationPolicyRepository']))->delete($id); });

    // COA
    $router->get('/danh-muc/he-thong-tai-khoan', function() { require __DIR__ . '/../public/views/accounts.php'; });
    $router->get('/api/coa', function() { (new \Accounting\Interfaces\HTTP\AccountController($GLOBALS['container']['accountRepository']))->list(); });
    $router->get('/api/coa/{id}', function($id) { (new \Accounting\Interfaces\HTTP\AccountController($GLOBALS['container']['accountRepository']))->get($id); });
    $router->post('/api/coa', function() { (new \Accounting\Interfaces\HTTP\AccountController($GLOBALS['container']['accountRepository']))->create(); });
    $router->put('/api/coa/{id}', function($id) { (new \Accounting\Interfaces\HTTP\AccountController($GLOBALS['container']['accountRepository']))->update($id); });
    $router->delete('/api/coa/{id}', function($id) { (new \Accounting\Interfaces\HTTP\AccountController($GLOBALS['container']['accountRepository']))->delete($id); });
    $router->post('/api/coa/seed', function() { (new \Accounting\Interfaces\HTTP\AccountController($GLOBALS['container']['accountRepository']))->seed(); });

    // Journal entries
    $router->post('/api/journal', function() { (new \Accounting\Interfaces\HTTP\JournalController($GLOBALS['container']['journalService'], $GLOBALS['container']['accountRepository']))->postEntry(); });
    $router->get('/api/trial-balance', function() { (new \Accounting\Interfaces\HTTP\JournalController($GLOBALS['container']['journalService'], $GLOBALS['container']['accountRepository']))->trialBalance(); });

    // Transfer
    $router->get('/kho/dieu-chuyen', function() { require __DIR__ . '/../public/views/transfers.php'; });

    // Consignment
    $router->get('/kho/hang-gui-ban', function() { require __DIR__ . '/../public/views/consignment.php'; });
    $router->get('/api/consignments', function() { (new \Accounting\Interfaces\HTTP\ConsignmentController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository']
    ))->list(); });
    $router->post('/api/consignments', function() { (new \Accounting\Interfaces\HTTP\ConsignmentController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository']
    ))->consign(); });
    $router->post('/api/consignments/sell', function() { (new \Accounting\Interfaces\HTTP\ConsignmentController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository']
    ))->sell(); });
    $router->post('/api/consignments/return', function() { (new \Accounting\Interfaces\HTTP\ConsignmentController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository']
    ))->returnConsignment(); });

    // Cash & Bank
    $router->get('/thu/quy-tien-mat', function() { require __DIR__ . '/../public/views/cash_receipts.php'; });
    $router->get('/chi/quy-tien-mat', function() { require __DIR__ . '/../public/views/cash_payments.php'; });
    $router->get('/thu/giao-bao-co', function() { require __DIR__ . '/../public/views/bank_credit.php'; });
    $router->get('/chi/giao-bao-no', function() { require __DIR__ . '/../public/views/bank_debit.php'; });
    $router->get('/thu/tien-dang-chuyen', function() { require __DIR__ . '/../public/views/cash_transit.php'; });
    $router->get('/api/cash/receipts', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->receipts(); });
    $router->get('/api/cash/payments', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->payments(); });
    $router->post('/api/cash/receipts', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->createReceipt(); });
    $router->post('/api/cash/payments', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->createPayment(); });
    $router->get('/api/cash/accounts', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->accounts(); });
    $router->get('/api/bank-transactions', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->bankTransactions(); });
    $router->post('/api/bank/deposit', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->createDeposit(); });
    $router->post('/api/bank/withdrawal', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->createWithdrawal(); });
    $router->post('/api/bank/receipt', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->createBankReceipt(); });
    $router->post('/api/bank/payment', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->createBankPayment(); });
    $router->post('/api/bank/interest', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->createInterest(); });
    $router->post('/api/bank/charge', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->createCharge(); });
    $router->get('/api/cash/transit', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->transitRecords(); });
    $router->post('/api/cash/transit', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->createTransit(); });
    $router->post('/api/cash/transit/confirm', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->confirmTransit(); });
    $router->post('/api/cash/transit/reverse', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->reverseTransit(); });
    $router->get('/thu/so-quy-tien-mat', function() { require __DIR__ . '/../public/views/cash_book.php'; });
    $router->get('/api/cash-book', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->cashBook(); });
    $router->get('/thu/tam-ung', function() { require __DIR__ . '/../public/views/petty_cash.php'; });
    $router->get('/api/petty-cash/funds', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->pettyFunds(); });
    $router->post('/api/petty-cash/funds', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->createPettyFund(); });
    $router->post('/api/petty-cash/disburse', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->disbursePetty(); });
    $router->post('/api/petty-cash/replenish', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->replenishPetty(); });
    $router->post('/api/petty-cash/close', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->closePettyFund(); });
    $router->get('/api/petty-cash/{id}/transactions', function($id) { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->pettyTransactions($id); });

    // User & Role management
    $router->get('/he-thong/nguoi-dung', function() { require __DIR__ . '/../public/views/users.php'; });
    $router->get('/he-thong/vai-tro', function() { require __DIR__ . '/../public/views/roles.php'; });

    // Financial Statements
    $router->get('/bao-cao/tinh-hinh-tai-chinh', function() { (new \Accounting\Interfaces\HTTP\FsController(
        $GLOBALS['container']['fsService']
    ))->viewBC01(); });
    $router->get('/bao-cao/ket-qua-kinh-doanh', function() { (new \Accounting\Interfaces\HTTP\FsController(
        $GLOBALS['container']['fsService']
    ))->viewBC02(); });
    $router->get('/api/fs/bc01', function() { (new \Accounting\Interfaces\HTTP\FsController(
        $GLOBALS['container']['fsService']
    ))->bc01(); });
    $router->get('/api/fs/bc02', function() { (new \Accounting\Interfaces\HTTP\FsController(
        $GLOBALS['container']['fsService']
    ))->bc02(); });

    // Period Management
    $router->get('/he-thong/quan-ly-ky', function() { require __DIR__ . '/../public/views/periods.php'; });
    $router->get('/api/periods', function() { (new \Accounting\Interfaces\HTTP\PeriodController(
        $GLOBALS['container']['periodService']
    ))->list(); });
    $router->get('/api/periods/{id}', function($id) { (new \Accounting\Interfaces\HTTP\PeriodController(
        $GLOBALS['container']['periodService']
    ))->get($id); });
    $router->post('/api/periods', function() { (new \Accounting\Interfaces\HTTP\PeriodController(
        $GLOBALS['container']['periodService']
    ))->create(); });
    $router->post('/api/periods/{id}/close', function($id) { (new \Accounting\Interfaces\HTTP\PeriodController(
        $GLOBALS['container']['periodService']
    ))->close($id); });
    $router->post('/api/periods/{id}/reopen', function($id) { (new \Accounting\Interfaces\HTTP\PeriodController(
        $GLOBALS['container']['periodService']
    ))->reOpen($id); });
    $router->get('/api/periods/{id}/can-close', function($id) { (new \Accounting\Interfaces\HTTP\PeriodController(
        $GLOBALS['container']['periodService']
    ))->canClose($id); });
    $router->post('/api/periods/{id}/execute-closing', function($id) { (new \Accounting\Interfaces\HTTP\PeriodController(
        $GLOBALS['container']['periodService']
    ))->executeClosing($id); });

    // Audit Log
    $router->get('/he-thong/nhat-ky-hoat-dong', function() { require __DIR__ . '/../public/views/audit_log.php'; });
    $router->get('/api/audit-log', function() { (new \Accounting\Interfaces\HTTP\AuditLogController($GLOBALS['container']['pdo']))->list(); });
    $router->get('/api/audit-log/{id}', function($id) { (new \Accounting\Interfaces\HTTP\AuditLogController($GLOBALS['container']['pdo']))->get($id); });

    // FX
    $router->get('/thu/danh-gia-lai-ngoai-te', function() { require __DIR__ . '/../public/views/fx_revaluation.php'; });
    $router->get('/api/fx/balances', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->fcBalances(); });
    $router->post('/api/fx/revalue', function() { (new \Accounting\Interfaces\HTTP\CashController(
        $GLOBALS['container']['cashService'], $GLOBALS['container']['accountRepository']
    ))->fcRevalue(); });

    // Cash Reports
    $router->get('/thu/bao-cao-von-bang-tien', function() { require __DIR__ . '/../public/views/cash_reports.php'; });
    $router->get('/api/cash-reports/position', function() { (new \Accounting\Interfaces\HTTP\CashReportController(
        $GLOBALS['container']['cashReportService']
    ))->position(); });
    $router->get('/api/cash-reports/bank-ledger', function() { (new \Accounting\Interfaces\HTTP\CashReportController(
        $GLOBALS['container']['cashReportService']
    ))->bankLedger(); });
    $router->get('/api/cash-reports/daily-flow', function() { (new \Accounting\Interfaces\HTTP\CashReportController(
        $GLOBALS['container']['cashReportService']
    ))->dailyFlow(); });
    $router->get('/api/cash-reports/concentration', function() { (new \Accounting\Interfaces\HTTP\CashReportController(
        $GLOBALS['container']['cashReportService']
    ))->concentration(); });
    $router->get('/api/cash-reports/trend', function() { (new \Accounting\Interfaces\HTTP\CashReportController(
        $GLOBALS['container']['cashReportService']
    ))->trend(); });

    // Bank Reconciliation
    $router->get('/thu/doi-chieu-ngan-hang', function() { require __DIR__ . '/../public/views/bank_reconciliation.php'; });
    $router->get('/api/bank-reconciliation/sessions', function() { (new \Accounting\Interfaces\HTTP\BankReconciliationController(
        $GLOBALS['container']['bankReconciliationService'], $GLOBALS['container']['accountRepository']
    ))->sessions(); });
    $router->post('/api/bank-reconciliation/start', function() { (new \Accounting\Interfaces\HTTP\BankReconciliationController(
        $GLOBALS['container']['bankReconciliationService'], $GLOBALS['container']['accountRepository']
    ))->startSession(); });
    $router->get('/api/bank-reconciliation/{id}/session', function($id) { (new \Accounting\Interfaces\HTTP\BankReconciliationController(
        $GLOBALS['container']['bankReconciliationService'], $GLOBALS['container']['accountRepository']
    ))->getSession($id); });
    $router->get('/api/bank-reconciliation/{id}/items', function($id) { (new \Accounting\Interfaces\HTTP\BankReconciliationController(
        $GLOBALS['container']['bankReconciliationService'], $GLOBALS['container']['accountRepository']
    ))->items($id); });
    $router->get('/api/bank-reconciliation/{id}/unmatched', function($id) { (new \Accounting\Interfaces\HTTP\BankReconciliationController(
        $GLOBALS['container']['bankReconciliationService'], $GLOBALS['container']['accountRepository']
    ))->unmatched($id); });
    $router->post('/api/bank-reconciliation/{id}/statement-entry', function($id) { (new \Accounting\Interfaces\HTTP\BankReconciliationController(
        $GLOBALS['container']['bankReconciliationService'], $GLOBALS['container']['accountRepository']
    ))->addStatementEntry($id); });
    $router->post('/api/bank-reconciliation/{id}/auto-match', function($id) { (new \Accounting\Interfaces\HTTP\BankReconciliationController(
        $GLOBALS['container']['bankReconciliationService'], $GLOBALS['container']['accountRepository']
    ))->autoMatch($id); });
    $router->post('/api/bank-reconciliation/{id}/manual-match', function($id) { (new \Accounting\Interfaces\HTTP\BankReconciliationController(
        $GLOBALS['container']['bankReconciliationService'], $GLOBALS['container']['accountRepository']
    ))->manualMatch($id); });
    $router->post('/api/bank-reconciliation/{id}/adjust', function($id) { (new \Accounting\Interfaces\HTTP\BankReconciliationController(
        $GLOBALS['container']['bankReconciliationService'], $GLOBALS['container']['accountRepository']
    ))->addAdjustingEntry($id); });
    $router->post('/api/bank-reconciliation/{id}/complete', function($id) { (new \Accounting\Interfaces\HTTP\BankReconciliationController(
        $GLOBALS['container']['bankReconciliationService'], $GLOBALS['container']['accountRepository']
    ))->complete($id); });
    $router->get('/api/bank-reconciliation/bank-accounts', function() { (new \Accounting\Interfaces\HTTP\BankReconciliationController(
        $GLOBALS['container']['bankReconciliationService'], $GLOBALS['container']['accountRepository']
    ))->bankAccounts(); });

    // Physical Count
    $router->get('/kho/kiem-ke', function() { require __DIR__ . '/../public/views/physical_count.php'; });
    $router->get('/api/physical-count/sessions', function() { (new \Accounting\Interfaces\HTTP\PhysicalCountController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository']
    ))->sessions(); });
    $router->get('/api/physical-count/lines/{id}', function($id) { (new \Accounting\Interfaces\HTTP\PhysicalCountController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository']
    ))->lines($id); });
    $router->post('/api/physical-count/sessions', function() { (new \Accounting\Interfaces\HTTP\PhysicalCountController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository']
    ))->createSession(); });
    $router->post('/api/physical-count/adjust', function() { (new \Accounting\Interfaces\HTTP\PhysicalCountController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository']
    ))->adjust(); });

    // Impairment
    $router->get('/kho/du-phong-giam-gia', function() { require __DIR__ . '/../public/views/impairment.php'; });
    $router->get('/api/impairments', function() { (new \Accounting\Interfaces\HTTP\ImpairmentController(
        $GLOBALS['container']['inventoryService']
    ))->list(); });
    $router->post('/api/impairments', function() { (new \Accounting\Interfaces\HTTP\ImpairmentController(
        $GLOBALS['container']['inventoryService']
    ))->record(); });
    $router->post('/api/impairments/reverse', function() { (new \Accounting\Interfaces\HTTP\ImpairmentController(
        $GLOBALS['container']['inventoryService']
    ))->reverse(); });

    // Promotional
    $router->post('/api/promotional/issue', function() { (new \Accounting\Interfaces\HTTP\PromotionalController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository']
    ))->issue(); });

    // Periodic Inventory
    $router->get('/kho/kiem-ke-dinh-ky', function() { require __DIR__ . '/../public/views/periodic.php'; });
    $router->get('/api/periodic', function() { (new \Accounting\Interfaces\HTTP\PeriodicController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository']
    ))->list(); });
    $router->post('/api/periodic/close', function() { (new \Accounting\Interfaces\HTTP\PeriodicController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository']
    ))->close(); });

    // In Transit
    $router->get('/kho/hang-dang-di-duong', function() { require __DIR__ . '/../public/views/transit.php'; });
    $router->get('/api/inventory-transit', function() { (new \Accounting\Interfaces\HTTP\InventoryTransitController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository']
    ))->list(); });
    $router->post('/api/inventory-transit', function() { (new \Accounting\Interfaces\HTTP\InventoryTransitController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository']
    ))->record(); });
    $router->post('/api/inventory-transit/receive', function() { (new \Accounting\Interfaces\HTTP\InventoryTransitController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository']
    ))->receive(); });
    $router->get('/api/transfers', function() { (new \Accounting\Interfaces\HTTP\TransferController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository'], $GLOBALS['container']['warehouseRepository']
    ))->list(); });
    $router->post('/api/transfers', function() { (new \Accounting\Interfaces\HTTP\TransferController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository'], $GLOBALS['container']['warehouseRepository']
    ))->transfer(); });
    $router->get('/api/transfers/items', function() { (new \Accounting\Interfaces\HTTP\TransferController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository'], $GLOBALS['container']['warehouseRepository']
    ))->items(); });
    $router->get('/api/transfers/warehouses', function() { (new \Accounting\Interfaces\HTTP\TransferController(
        $GLOBALS['container']['inventoryService'], $GLOBALS['container']['itemRepository'], $GLOBALS['container']['warehouseRepository']
    ))->warehouses(); });
}

$GLOBALS['router'] = new Router();
defineRoutes($GLOBALS['router']);
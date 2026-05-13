<?php

use Accounting\Interfaces\HTTP\Router;

function defineRoutes(Router $router): void
{
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
}

$GLOBALS['router'] = new Router();
defineRoutes($GLOBALS['router']);
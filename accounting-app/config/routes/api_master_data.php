<?php

use Accounting\Infrastructure\JsonResponse;

// === ITEMS ===
$router->get('/api/items', function() use ($c) { $c['ItemController']->list(); });
$router->get('/api/items/:id', function($id) use ($c) { $c['ItemController']->get($id); });
$router->post('/api/items', function() use ($c) { $c['ItemController']->create(); });
$router->put('/api/items/:id', function($id) use ($c) { $c['ItemController']->update($id); });
$router->delete('/api/items/:id', function($id) use ($c) { $c['ItemController']->delete($id); });

// === CUSTOMERS (TK 131) ===
$router->get('/api/customers', function() use ($c) { $c['CustomerController']->list(); });
$router->get('/api/customers/:id', function($id) use ($c) { $c['CustomerController']->get($id); });
$router->post('/api/customers', function() use ($c) { $c['CustomerController']->create(); });
$router->put('/api/customers/:id', function($id) use ($c) { $c['CustomerController']->update($id); });
$router->delete('/api/customers/:id', function($id) use ($c) { $c['CustomerController']->delete($id); });

// === SUPPLIERS (TK 331) ===
$router->get('/api/suppliers', function() use ($c) { $c['SupplierController']->list(); });
$router->get('/api/suppliers/:id', function($id) use ($c) { $c['SupplierController']->get($id); });
$router->post('/api/suppliers', function() use ($c) { $c['SupplierController']->create(); });
$router->put('/api/suppliers/:id', function($id) use ($c) { $c['SupplierController']->update($id); });
$router->delete('/api/suppliers/:id', function($id) use ($c) { $c['SupplierController']->delete($id); });

// === WAREHOUSES ===
$router->get('/api/warehouses', function() use ($c) { $c['WarehouseController']->list(); });
$router->get('/api/warehouses/:id', function($id) use ($c) { $c['WarehouseController']->get($id); });
$router->post('/api/warehouses', function() use ($c) { $c['WarehouseController']->create(); });
$router->put('/api/warehouses/:id', function($id) use ($c) { $c['WarehouseController']->update($id); });
$router->delete('/api/warehouses/:id', function($id) use ($c) { $c['WarehouseController']->delete($id); });

// === DEPARTMENTS ===
$router->get('/api/departments', function() use ($c) { $c['DepartmentController']->list(); });
$router->get('/api/departments/:id', function($id) use ($c) { $c['DepartmentController']->get($id); });
$router->post('/api/departments', function() use ($c) { $c['DepartmentController']->create(); });
$router->put('/api/departments/:id', function($id) use ($c) { $c['DepartmentController']->update($id); });
$router->delete('/api/departments/:id', function($id) use ($c) { $c['DepartmentController']->delete($id); });

// === EMPLOYEES (TK 334) ===
$router->get('/api/employees', function() use ($c) { $c['EmployeeController']->list(); });
$router->get('/api/employees/:id', function($id) use ($c) { $c['EmployeeController']->get($id); });
$router->post('/api/employees', function() use ($c) { $c['EmployeeController']->create(); });
$router->put('/api/employees/:id', function($id) use ($c) { $c['EmployeeController']->update($id); });
$router->delete('/api/employees/:id', function($id) use ($c) { $c['EmployeeController']->delete($id); });

// === UOM (Đơn vị tính) ===
$router->get('/api/uoms', function() use ($c) { $c['UomController']->list(); });
$router->get('/api/uoms/:id', function($id) use ($c) { $c['UomController']->get($id); });
$router->post('/api/uoms', function() use ($c) { $c['UomController']->create(); });
$router->put('/api/uoms/:id', function($id) use ($c) { $c['UomController']->update($id); });
$router->delete('/api/uoms/:id', function($id) use ($c) { $c['UomController']->delete($id); });

// === CCDC (Công cụ dụng cụ - TK 153) ===
$router->get('/api/ccdc', function() use ($c) { $c['CcdcController']->list(); });
$router->get('/api/ccdc/:id', function($id) use ($c) { $c['CcdcController']->get($id); });
$router->post('/api/ccdc', function() use ($c) { $c['CcdcController']->create(); });
$router->put('/api/ccdc/:id', function($id) use ($c) { $c['CcdcController']->update($id); });
$router->delete('/api/ccdc/:id', function($id) use ($c) { $c['CcdcController']->delete($id); });

// === COA (Hệ thống tài khoản - Circular 99) ===
// Các route KHÔNG có :id parameter phải đăng ký TRƯỚC route có :id (first-match routing)
$router->get('/api/coa', function() use ($c) { $c['AccountController']->list(); });
$router->get('/api/coa/flat', function() use ($c) { $c['AccountController']->flatList(); });
$router->get('/api/coa/search', function() use ($c) { $c['AccountController']->search(); });
$router->get('/api/coa/type/:type', function($type) use ($c) { $c['AccountController']->byType($type); });
$router->get('/api/coa/fs-report', function() use ($c) { $c['AccountController']->fsReport(); });
$router->post('/api/coa', function() use ($c) { $c['AccountController']->create(); });
$router->post('/api/coa/seed', function() use ($c) { $c['AccountController']->seed(); });
$router->post('/api/coa/merge', function() use ($c) { $c['AccountController']->merge(); });
$router->post('/api/coa/split', function() use ($c) { $c['AccountController']->split(); });
$router->post('/api/coa/branch', function() use ($c) { $c['AccountController']->branchCoa(); });
$router->get('/api/coa/:id', function($id) use ($c) { $c['AccountController']->get($id); });
$router->put('/api/coa/:id', function($id) use ($c) { $c['AccountController']->update($id); });
$router->delete('/api/coa/:id', function($id) use ($c) { $c['AccountController']->delete($id); });
$router->post('/api/coa/:id/activate', function($id) use ($c) { $c['AccountController']->activate($id); });
$router->post('/api/coa/:id/deactivate', function($id) use ($c) { $c['AccountController']->deactivate($id); });
$router->post('/api/coa/:id/lock', function($id) use ($c) { $c['AccountController']->lockAccount($id); });
$router->post('/api/coa/:id/unlock', function($id) use ($c) { $c['AccountController']->unlockAccount($id); });

// === EXCHANGE RATES ===
$router->get('/api/exchange-rates', function() use ($c) { $c['ExchangeRateController']->list(); });
$router->get('/api/exchange-rates/:id', function($id) use ($c) { $c['ExchangeRateController']->get($id); });
$router->post('/api/exchange-rates', function() use ($c) { $c['ExchangeRateController']->create(); });
$router->put('/api/exchange-rates/:id', function($id) use ($c) { $c['ExchangeRateController']->update($id); });
$router->delete('/api/exchange-rates/:id', function($id) use ($c) { $c['ExchangeRateController']->delete($id); });

// === TAX RATES ===
$router->get('/api/tax-rates', function() use ($c) { $c['TaxRateController']->list(); });
$router->get('/api/vat-rates', function() use ($c) { $c['TaxRateController']->vatRates(); });
$router->get('/api/tax-rates/:id', function($id) use ($c) { $c['TaxRateController']->get($id); });
$router->post('/api/tax-rates', function() use ($c) { $c['TaxRateController']->create(); });
$router->put('/api/tax-rates/:id', function($id) use ($c) { $c['TaxRateController']->update($id); });
$router->delete('/api/tax-rates/:id', function($id) use ($c) { $c['TaxRateController']->delete($id); });

// === VALUATION METHODS ===
$router->get('/api/valuation-methods', function() use ($c) { $c['ValuationMethodController']->list(); });
$router->get('/api/valuation-methods/:id', function($id) use ($c) { $c['ValuationMethodController']->get($id); });
$router->post('/api/valuation-methods', function() use ($c) { $c['ValuationMethodController']->create(); });
$router->put('/api/valuation-methods/:id', function($id) use ($c) { $c['ValuationMethodController']->update($id); });
$router->delete('/api/valuation-methods/:id', function($id) use ($c) { $c['ValuationMethodController']->delete($id); });

// === CONTRACTS ===
$router->get('/api/contracts', function() use ($c) { $c['ContractController']->list(); });
$router->get('/api/contracts/:id', function($id) use ($c) { $c['ContractController']->get($id); });
$router->post('/api/contracts', function() use ($c) { $c['ContractController']->create(); });
$router->put('/api/contracts/:id', function($id) use ($c) { $c['ContractController']->update($id); });
$router->delete('/api/contracts/:id', function($id) use ($c) { $c['ContractController']->delete($id); });

// === PROJECTS ===
$router->get('/api/projects', function() use ($c) { $c['ProjectController']->list(); });
$router->get('/api/projects/:id', function($id) use ($c) { $c['ProjectController']->get($id); });
$router->post('/api/projects', function() use ($c) { $c['ProjectController']->create(); });
$router->put('/api/projects/:id', function($id) use ($c) { $c['ProjectController']->update($id); });
$router->delete('/api/projects/:id', function($id) use ($c) { $c['ProjectController']->delete($id); });

// === DEPRECIATION POLICIES ===
$router->get('/api/depreciation-policies', function() use ($c) { $c['DepreciationPolicyController']->list(); });
$router->get('/api/depreciation-policies/:id', function($id) use ($c) { $c['DepreciationPolicyController']->get($id); });
$router->post('/api/depreciation-policies', function() use ($c) { $c['DepreciationPolicyController']->create(); });
$router->put('/api/depreciation-policies/:id', function($id) use ($c) { $c['DepreciationPolicyController']->update($id); });
$router->delete('/api/depreciation-policies/:id', function($id) use ($c) { $c['DepreciationPolicyController']->delete($id); });

// === BANK ACCOUNTS ===
$router->get('/api/bank-accounts', function() use ($c) { $c['BankAccountController']->list(); });
$router->get('/api/bank-accounts/:id', function($id) use ($c) { $c['BankAccountController']->get($id); });
$router->post('/api/bank-accounts', function() use ($c) { $c['BankAccountController']->create(); });
$router->put('/api/bank-accounts/:id', function($id) use ($c) { $c['BankAccountController']->update($id); });
$router->delete('/api/bank-accounts/:id', function($id) use ($c) { $c['BankAccountController']->delete($id); });

// === FIXED ASSETS (TK 211, 214) ===
$router->get('/api/fixed-assets', function() use ($c) { $c['FixedAssetController']->list(); });
$router->get('/api/fixed-assets/:id', function($id) use ($c) { $c['FixedAssetController']->get($id); });
$router->post('/api/fixed-assets', function() use ($c) { $c['FixedAssetController']->create(); });
$router->put('/api/fixed-assets/:id', function($id) use ($c) { $c['FixedAssetController']->update($id); });
$router->delete('/api/fixed-assets/:id', function($id) use ($c) { $c['FixedAssetController']->delete($id); });

// Fixed Asset Depreciation
$router->post('/api/fixed-assets/depreciate', function() use ($c) {
    $input = json_decode(file_get_contents('php://input'), true);
    $period = $input['period'] ?? date('Y-m');
    $results = $c['fixedAssetService']->postMonthlyDepreciation($period, $_SESSION['user_id'] ?? 'system');
    JsonResponse::ok(['posted' => count($results), 'entries' => $results]);
});
$router->get('/api/fixed-assets/:id/depreciation', function($id) use ($c) {
    JsonResponse::ok($c['fixedAssetService']->getDepreciationHistory($id));
});
$router->get('/api/fixed-assets/depreciation/period/:period', function($period) use ($c) {
    JsonResponse::ok($c['fixedAssetService']->getDepreciationByPeriod($period));
});
$router->get('/api/fixed-assets/:id/schedule', function($id) use ($c) {
    $asset = $c['fixedAssetRepository']->findById($id);
    if (!$asset) { JsonResponse::error('Không tìm thấy tài sản cố định', 404); return; }
    JsonResponse::ok($c['fixedAssetService']->calculateSchedule($asset));
});

// Fixed Asset Acquisition
$router->post('/api/fixed-assets/acquire', function() use ($c) { $c['FixedAssetLifecycleController']->acquire(); });

// Fixed Asset Disposal
$router->post('/api/fixed-assets/dispose', function() use ($c) { $c['FixedAssetLifecycleController']->dispose(); });

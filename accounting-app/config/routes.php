<?php

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Domain\ValueObject\VnWords;
use Accounting\Interfaces\HTTP\Router;

function defineRoutes(Router $router): void
{
    $c = $GLOBALS['container'];
    // Auth
    $router->get('/dang-nhap', function() { require __DIR__ . '/../public/views/login.php'; });
    $router->post('/api/auth/login', function() use ($c) { $c['AuthController']->login(); });
    $router->post('/api/auth/logout', function() use ($c) { $c['AuthController']->logout(); });
    $router->get('/api/auth/me', function() use ($c) { $c['AuthController']->me(); });
    $router->get('/api/auth/csrf', function() { JsonResponse::ok(['token' => Auth::csrfToken()]); });
    $router->get('/api/utils/to-words', function() {
        $n = (float)($_GET['amount'] ?? 0);
        JsonResponse::ok(['words' => VnWords::toWords($n)]);
    });

    // User management
    $router->get('/api/users', function() use ($c) { $c['UserController']->list(); });
    $router->post('/api/users', function() use ($c) { $c['UserController']->create(); });
    $router->put('/api/users/:id', function($id) use ($c) { $c['UserController']->update($id); });
    $router->delete('/api/users/:id', function($id) use ($c) { $c['UserController']->delete($id); });

    // Role management
    $router->get('/api/roles', function() use ($c) { $c['RoleController']->list(); });
    $router->post('/api/roles', function() use ($c) { $c['RoleController']->create(); });
    $router->put('/api/roles/:id', function($id) use ($c) { $c['RoleController']->update($id); });
    $router->delete('/api/roles/:id', function($id) use ($c) { $c['RoleController']->delete($id); });
    $router->get('/api/roles/:id/permissions', function($id) use ($c) { $c['RoleController']->getPermissions($id); });
    $router->put('/api/roles/:id/permissions', function($id) use ($c) { $c['RoleController']->updatePermissions($id); });
    $router->get('/api/user-management/users', function() use ($c) { $c['UserController']->listWithRoles(); });

    // Frontend pages
    $router->get('/', function() { require __DIR__ . '/../public/views/dashboard.php'; });
    $router->get('/danh-muc/vat-tu', function() { require __DIR__ . '/../public/views/items.php'; });
    $router->get('/danh-muc/khach-hang', function() { require __DIR__ . '/../public/views/customers.php'; });
    $router->get('/danh-muc/nha-cung-cap', function() { require __DIR__ . '/../public/views/suppliers.php'; });

    // Item API
    $router->get('/api/items', function() use ($c) { $c['ItemController']->list(); });
    $router->get('/api/items/:id', function($id) use ($c) { $c['ItemController']->get($id); });
    $router->post('/api/items', function() use ($c) { $c['ItemController']->create(); });
    $router->put('/api/items/:id', function($id) use ($c) { $c['ItemController']->update($id); });
    $router->delete('/api/items/:id', function($id) use ($c) { $c['ItemController']->delete($id); });

    // Customer API
    $router->get('/api/customers', function() use ($c) { $c['CustomerController']->list(); });
    $router->get('/api/customers/:id', function($id) use ($c) { $c['CustomerController']->get($id); });
    $router->post('/api/customers', function() use ($c) { $c['CustomerController']->create(); });
    $router->put('/api/customers/:id', function($id) use ($c) { $c['CustomerController']->update($id); });
    $router->delete('/api/customers/:id', function($id) use ($c) { $c['CustomerController']->delete($id); });

    // Supplier API
    $router->get('/api/suppliers', function() use ($c) { $c['SupplierController']->list(); });
    $router->get('/api/suppliers/:id', function($id) use ($c) { $c['SupplierController']->get($id); });
    $router->post('/api/suppliers', function() use ($c) { $c['SupplierController']->create(); });
    $router->put('/api/suppliers/:id', function($id) use ($c) { $c['SupplierController']->update($id); });
    $router->delete('/api/suppliers/:id', function($id) use ($c) { $c['SupplierController']->delete($id); });

    // Frontend pages
    $router->get('/danh-muc/kho', function() { require __DIR__ . '/../public/views/warehouses.php'; });
    $router->get('/danh-muc/phong-ban', function() { require __DIR__ . '/../public/views/departments.php'; });
    $router->get('/danh-muc/nhan-vien', function() { require __DIR__ . '/../public/views/employees.php'; });

    // Warehouse API
    $router->get('/api/warehouses', function() use ($c) { $c['WarehouseController']->list(); });
    $router->get('/api/warehouses/:id', function($id) use ($c) { $c['WarehouseController']->get($id); });
    $router->post('/api/warehouses', function() use ($c) { $c['WarehouseController']->create(); });
    $router->put('/api/warehouses/:id', function($id) use ($c) { $c['WarehouseController']->update($id); });
    $router->delete('/api/warehouses/:id', function($id) use ($c) { $c['WarehouseController']->delete($id); });

    // Department API
    $router->get('/api/departments', function() use ($c) { $c['DepartmentController']->list(); });
    $router->get('/api/departments/:id', function($id) use ($c) { $c['DepartmentController']->get($id); });
    $router->post('/api/departments', function() use ($c) { $c['DepartmentController']->create(); });
    $router->put('/api/departments/:id', function($id) use ($c) { $c['DepartmentController']->update($id); });
    $router->delete('/api/departments/:id', function($id) use ($c) { $c['DepartmentController']->delete($id); });

    // Employee API
    $router->get('/api/employees', function() use ($c) { $c['EmployeeController']->list(); });
    $router->get('/api/employees/:id', function($id) use ($c) { $c['EmployeeController']->get($id); });
    $router->post('/api/employees', function() use ($c) { $c['EmployeeController']->create(); });
    $router->put('/api/employees/:id', function($id) use ($c) { $c['EmployeeController']->update($id); });
    $router->delete('/api/employees/:id', function($id) use ($c) { $c['EmployeeController']->delete($id); });

    // UOM
    $router->get('/danh-muc/don-vi-tinh', function() { require __DIR__ . '/../public/views/uoms.php'; });
    $router->get('/api/uoms', function() use ($c) { $c['UomController']->list(); });
    $router->get('/api/uoms/:id', function($id) use ($c) { $c['UomController']->get($id); });
    $router->post('/api/uoms', function() use ($c) { $c['UomController']->create(); });
    $router->put('/api/uoms/:id', function($id) use ($c) { $c['UomController']->update($id); });
    $router->delete('/api/uoms/:id', function($id) use ($c) { $c['UomController']->delete($id); });

    // CCDC
    $router->get('/danh-muc/cong-cu-dung-cu', function() { require __DIR__ . '/../public/views/ccdc.php'; });
    $router->get('/api/ccdc', function() use ($c) { $c['CcdcController']->list(); });
    $router->get('/api/ccdc/:id', function($id) use ($c) { $c['CcdcController']->get($id); });
    $router->post('/api/ccdc', function() use ($c) { $c['CcdcController']->create(); });
    $router->put('/api/ccdc/:id', function($id) use ($c) { $c['CcdcController']->update($id); });
    $router->delete('/api/ccdc/:id', function($id) use ($c) { $c['CcdcController']->delete($id); });

    // Exchange Rates
    $router->get('/danh-muc/ty-gia', function() { require __DIR__ . '/../public/views/exchange_rates.php'; });
    $router->get('/api/exchange-rates', function() use ($c) { $c['ExchangeRateController']->list(); });
    $router->get('/api/exchange-rates/:id', function($id) use ($c) { $c['ExchangeRateController']->get($id); });
    $router->post('/api/exchange-rates', function() use ($c) { $c['ExchangeRateController']->create(); });
    $router->put('/api/exchange-rates/:id', function($id) use ($c) { $c['ExchangeRateController']->update($id); });
    $router->delete('/api/exchange-rates/:id', function($id) use ($c) { $c['ExchangeRateController']->delete($id); });

    // Tax Rates
    $router->get('/danh-muc/bieu-thue', function() { require __DIR__ . '/../public/views/tax_rates.php'; });
    $router->get('/api/tax-rates', function() use ($c) { $c['TaxRateController']->list(); });
    $router->get('/api/tax-rates/:id', function($id) use ($c) { $c['TaxRateController']->get($id); });
    $router->post('/api/tax-rates', function() use ($c) { $c['TaxRateController']->create(); });
    $router->put('/api/tax-rates/:id', function($id) use ($c) { $c['TaxRateController']->update($id); });
    $router->delete('/api/tax-rates/:id', function($id) use ($c) { $c['TaxRateController']->delete($id); });

    // Fixed Assets
    $router->get('/danh-muc/tai-san-co-dinh', function() { require __DIR__ . '/../public/views/fixed_assets.php'; });
    $router->get('/api/fixed-assets', function() use ($c) { $c['FixedAssetController']->list(); });
    $router->get('/api/fixed-assets/:id', function($id) use ($c) { $c['FixedAssetController']->get($id); });
    $router->post('/api/fixed-assets', function() use ($c) { $c['FixedAssetController']->create(); });
    $router->put('/api/fixed-assets/:id', function($id) use ($c) { $c['FixedAssetController']->update($id); });
    $router->delete('/api/fixed-assets/:id', function($id) use ($c) { $c['FixedAssetController']->delete($id); });

    // Valuation Methods
    $router->get('/danh-muc/phuong-phap-tinh-gia', function() { require __DIR__ . '/../public/views/valuation_methods.php'; });
    $router->get('/api/valuation-methods', function() use ($c) { $c['ValuationMethodController']->list(); });
    $router->get('/api/valuation-methods/:id', function($id) use ($c) { $c['ValuationMethodController']->get($id); });
    $router->post('/api/valuation-methods', function() use ($c) { $c['ValuationMethodController']->create(); });
    $router->put('/api/valuation-methods/:id', function($id) use ($c) { $c['ValuationMethodController']->update($id); });
    $router->delete('/api/valuation-methods/:id', function($id) use ($c) { $c['ValuationMethodController']->delete($id); });

    // Contracts
    $router->get('/danh-muc/hop-dong', function() { require __DIR__ . '/../public/views/contracts.php'; });
    $router->get('/api/contracts', function() use ($c) { $c['ContractController']->list(); });
    $router->get('/api/contracts/:id', function($id) use ($c) { $c['ContractController']->get($id); });
    $router->post('/api/contracts', function() use ($c) { $c['ContractController']->create(); });
    $router->put('/api/contracts/:id', function($id) use ($c) { $c['ContractController']->update($id); });
    $router->delete('/api/contracts/:id', function($id) use ($c) { $c['ContractController']->delete($id); });

    // Projects
    $router->get('/danh-muc/du-an', function() { require __DIR__ . '/../public/views/projects.php'; });
    $router->get('/api/projects', function() use ($c) { $c['ProjectController']->list(); });
    $router->get('/api/projects/:id', function($id) use ($c) { $c['ProjectController']->get($id); });
    $router->post('/api/projects', function() use ($c) { $c['ProjectController']->create(); });
    $router->put('/api/projects/:id', function($id) use ($c) { $c['ProjectController']->update($id); });
    $router->delete('/api/projects/:id', function($id) use ($c) { $c['ProjectController']->delete($id); });

    // Depreciation Policies
    $router->get('/danh-muc/chinh-sach-khau-hao', function() { require __DIR__ . '/../public/views/depreciation_policies.php'; });
    $router->get('/api/depreciation-policies', function() use ($c) { $c['DepreciationPolicyController']->list(); });
    $router->get('/api/depreciation-policies/:id', function($id) use ($c) { $c['DepreciationPolicyController']->get($id); });
    $router->post('/api/depreciation-policies', function() use ($c) { $c['DepreciationPolicyController']->create(); });
    $router->put('/api/depreciation-policies/:id', function($id) use ($c) { $c['DepreciationPolicyController']->update($id); });
    $router->delete('/api/depreciation-policies/:id', function($id) use ($c) { $c['DepreciationPolicyController']->delete($id); });

    // COA
    $router->get('/danh-muc/he-thong-tai-khoan', function() { require __DIR__ . '/../public/views/accounts.php'; });
    $router->get('/api/coa', function() use ($c) { $c['AccountController']->list(); });
    $router->get('/api/coa/:id', function($id) use ($c) { $c['AccountController']->get($id); });
    $router->post('/api/coa', function() use ($c) { $c['AccountController']->create(); });
    $router->put('/api/coa/:id', function($id) use ($c) { $c['AccountController']->update($id); });
    $router->delete('/api/coa/:id', function($id) use ($c) { $c['AccountController']->delete($id); });
    $router->post('/api/coa/seed', function() use ($c) { $c['AccountController']->seed(); });

    // Journal entries
    $router->get('/tong-hop/chung-tu-ghi-so', function() { require __DIR__ . '/../public/views/journal.php'; });
    $router->get('/tong-hop/bang-can-doi-so-phat-sinh', function() { require __DIR__ . '/../public/views/trial_balance.php'; });
    $router->get('/tong-hop/khoa-so-cuoi-ky', function() { require __DIR__ . '/../public/views/period_close.php'; });
    $router->post('/api/journal', function() use ($c) { $c['JournalController']->postEntry(); });
    $router->post('/api/journal/draft', function() use ($c) { $c['JournalController']->createDraft(); });
    $router->post('/api/journal/approve/:id', function($id) use ($c) { $c['JournalController']->approveDraft($id); });
    $router->get('/api/transactions', function() use ($c) { $c['JournalController']->list(); });
    $router->get('/api/transactions/:id', function($id) use ($c) { $c['JournalController']->get($id); });
    $router->get('/api/trial-balance', function() use ($c) { $c['JournalController']->trialBalance(); });

    // Payer search (customers + suppliers + employees)
    $router->get('/api/payers/search', function() {
        $q = $_GET['q'] ?? '';
        $pdo = $GLOBALS['container']['pdo'];
        $results = [];
        if (strlen($q) >= 1) {
            $like = '%' . $q . '%';
            $stmt = $pdo->prepare("SELECT id, code, name, 'customer' as type FROM customers WHERE name LIKE ? OR code LIKE ? LIMIT 10");
            $stmt->execute([$like, $like]); $results = array_merge($results, $stmt->fetchAll(\PDO::FETCH_ASSOC));
            $stmt = $pdo->prepare("SELECT id, code, name, 'supplier' as type FROM suppliers WHERE name LIKE ? OR code LIKE ? LIMIT 10");
            $stmt->execute([$like, $like]); $results = array_merge($results, $stmt->fetchAll(\PDO::FETCH_ASSOC));
            $stmt = $pdo->prepare("SELECT id, code, name, 'employee' as type FROM employees WHERE name LIKE ? OR code LIKE ? LIMIT 10");
            $stmt->execute([$like, $like]); $results = array_merge($results, $stmt->fetchAll(\PDO::FETCH_ASSOC));
        }
        JsonResponse::ok($results);
    });

    // Cash & Bank API
    $router->get('/api/cash/receipts', function() use ($c) { $c['CashController']->receipts(); });
    $router->post('/api/cash/receipts', function() use ($c) { $c['CashController']->createReceipt(); });
    $router->get('/api/cash/payments', function() use ($c) { $c['CashController']->payments(); });
    $router->post('/api/cash/payments', function() use ($c) { $c['CashController']->createPayment(); });
    $router->get('/api/cash/templates', function() use ($c) { $c['CashController']->transactionTemplates(); });
    $router->get('/api/cash/accounts', function() use ($c) { $c['CashController']->accounts(); });
    $router->get('/api/bank-transactions', function() use ($c) { $c['CashController']->bankTransactions(); });
    $router->post('/api/bank/deposit', function() use ($c) { $c['CashController']->createDeposit(); });
    $router->post('/api/bank/withdrawal', function() use ($c) { $c['CashController']->createWithdrawal(); });
    $router->post('/api/bank/receipt', function() use ($c) { $c['CashController']->createBankReceipt(); });
    $router->post('/api/bank/payment', function() use ($c) { $c['CashController']->createBankPayment(); });
    $router->post('/api/bank/interest', function() use ($c) { $c['CashController']->createInterest(); });
    $router->post('/api/bank/charge', function() use ($c) { $c['CashController']->createCharge(); });
    $router->get('/api/cash/transit', function() use ($c) { $c['CashController']->transitRecords(); });
    $router->post('/api/cash/transit', function() use ($c) { $c['CashController']->createTransit(); });
    $router->post('/api/cash/transit/confirm', function() use ($c) { $c['CashController']->confirmTransit(); });
    $router->post('/api/cash/transit/reverse', function() use ($c) { $c['CashController']->reverseTransit(); });
    $router->get('/api/cash-book', function() use ($c) { $c['CashController']->cashBook(); });
    $router->get('/api/petty-cash/funds', function() use ($c) { $c['PettyCashController']->funds(); });
    $router->post('/api/petty-cash/funds', function() use ($c) { $c['PettyCashController']->createFund(); });
    $router->post('/api/petty-cash/disburse', function() use ($c) { $c['PettyCashController']->disburse(); });
    $router->post('/api/petty-cash/replenish', function() use ($c) { $c['PettyCashController']->replenish(); });
    $router->post('/api/petty-cash/close', function() use ($c) { $c['PettyCashController']->closeFund(); });
    $router->get('/api/petty-cash/:id/transactions', function($id) use ($c) { $c['PettyCashController']->transactions($id); });

    // Cash & Bank views
    $router->get('/thu/quy-tien-mat', function() { require __DIR__ . '/../public/views/cash_receipts.php'; });
    $router->get('/chi/quy-tien-mat', function() { require __DIR__ . '/../public/views/cash_payments.php'; });
    $router->get('/thu/giao-bao-co', function() { require __DIR__ . '/../public/views/bank_credit.php'; });
    $router->get('/chi/giao-bao-no', function() { require __DIR__ . '/../public/views/bank_debit.php'; });
    $router->get('/thu/tien-dang-chuyen', function() { require __DIR__ . '/../public/views/cash_transit.php'; });
    $router->get('/thu/so-quy-tien-mat', function() { require __DIR__ . '/../public/views/cash_book.php'; });
    $router->get('/thu/tam-ung', function() { require __DIR__ . '/../public/views/petty_cash.php'; });
    $router->get('/thu/doi-chieu-ngan-hang', function() { require __DIR__ . '/../public/views/bank_reconciliation.php'; });
    $router->get('/thu/bao-cao-von-bang-tien', function() { require __DIR__ . '/../public/views/cash_reports.php'; });
    $router->get('/thu/danh-gia-lai-ngoai-te', function() { require __DIR__ . '/../public/views/fx_revaluation.php'; });
    $router->get('/danh-muc/tai-khoan-ngan-hang', function() { require __DIR__ . '/../public/views/bank_accounts.php'; });

    // Bank Accounts API
    $router->get('/api/bank-accounts', function() use ($c) { $c['BankAccountController']->list(); });
    $router->get('/api/bank-accounts/:id', function($id) use ($c) { $c['BankAccountController']->get($id); });
    $router->post('/api/bank-accounts', function() use ($c) { $c['BankAccountController']->create(); });
    $router->put('/api/bank-accounts/:id', function($id) use ($c) { $c['BankAccountController']->update($id); });
    $router->delete('/api/bank-accounts/:id', function($id) use ($c) { $c['BankAccountController']->delete($id); });

    // FX
    $router->get('/api/fx/balances', function() use ($c) { $c['CashController']->fcBalances(); });
    $router->post('/api/fx/revalue', function() use ($c) { $c['CashController']->fcRevalue(); });

    // Cash Reports API
    $router->get('/api/cash-reports/position', function() use ($c) { $c['CashReportController']->position(); });
    $router->get('/api/cash-reports/bank-ledger', function() use ($c) { $c['CashReportController']->bankLedger(); });
    $router->get('/api/cash-reports/daily-flow', function() use ($c) { $c['CashReportController']->dailyFlow(); });
    $router->get('/api/cash-reports/concentration', function() use ($c) { $c['CashReportController']->concentration(); });
    $router->get('/api/cash-reports/trend', function() use ($c) { $c['CashReportController']->trend(); });

    // Bank Reconciliation API
    $router->get('/api/bank-reconciliation/sessions', function() use ($c) { $c['BankReconciliationController']->sessions(); });
    $router->post('/api/bank-reconciliation/start', function() use ($c) { $c['BankReconciliationController']->startSession(); });
    $router->get('/api/bank-reconciliation/:id/session', function($id) use ($c) { $c['BankReconciliationController']->getSession($id); });
    $router->get('/api/bank-reconciliation/:id/items', function($id) use ($c) { $c['BankReconciliationController']->items($id); });
    $router->get('/api/bank-reconciliation/:id/unmatched', function($id) use ($c) { $c['BankReconciliationController']->unmatched($id); });
    $router->post('/api/bank-reconciliation/:id/statement-entry', function($id) use ($c) { $c['BankReconciliationController']->addStatementEntry($id); });
    $router->post('/api/bank-reconciliation/:id/auto-match', function($id) use ($c) { $c['BankReconciliationController']->autoMatch($id); });
    $router->post('/api/bank-reconciliation/:id/manual-match', function($id) use ($c) { $c['BankReconciliationController']->manualMatch($id); });
    $router->post('/api/bank-reconciliation/:id/adjust', function($id) use ($c) { $c['BankReconciliationController']->addAdjustingEntry($id); });
    $router->post('/api/bank-reconciliation/:id/complete', function($id) use ($c) { $c['BankReconciliationController']->complete($id); });
    $router->get('/api/bank-reconciliation/bank-accounts', function() use ($c) { $c['BankReconciliationController']->bankAccounts(); });

    // Transfer
    $router->get('/kho/dieu-chuyen', function() { require __DIR__ . '/../public/views/transfers.php'; });

    // Consignment
    $router->get('/kho/hang-gui-ban', function() { require __DIR__ . '/../public/views/consignment.php'; });
    $router->get('/api/consignments', function() use ($c) { $c['ConsignmentController']->list(); });
    $router->post('/api/consignments', function() use ($c) { $c['ConsignmentController']->consign(); });
    $router->post('/api/consignments/sell', function() use ($c) { $c['ConsignmentController']->sell(); });
    $router->post('/api/consignments/return', function() use ($c) { $c['ConsignmentController']->returnConsignment(); });

    // User & Role management
    $router->get('/he-thong/nguoi-dung', function() { require __DIR__ . '/../public/views/users.php'; });
    $router->get('/he-thong/vai-tro', function() { require __DIR__ . '/../public/views/roles.php'; });

    // AP (TK 331)
    $router->get('/mua/cong-no-phai-tra', function() { require __DIR__ . '/../public/views/ap_invoices.php'; });
    $router->get('/mua/phan-tich-tuoi-no', function() { require __DIR__ . '/../public/views/ap_aging.php'; });
    $router->get('/mua/so-chi-tiet-cong-no', function() { require __DIR__ . '/../public/views/ap_statement.php'; });
    $router->get('/api/ap/invoices', function() use ($c) { $c['ApController']->invoices(); });
    $router->post('/api/ap/invoices', function() use ($c) { $c['ApController']->create(); });
    $router->get('/api/ap/invoices/:id', function($id) use ($c) { $c['ApController']->get($id); });
    $router->get('/api/ap/invoices/:id/payments', function($id) use ($c) { $c['ApController']->payments($id); });
    $router->post('/api/ap/invoices/:id/pay', function($id) use ($c) { $c['ApController']->pay($id); });
    $router->post('/api/ap/invoices/:id/return', function($id) use ($c) { $c['ApController']->returnGoods($id); });
    $router->post('/api/ap/invoices/:id/discount', function($id) use ($c) { $c['ApController']->discount($id); });
    $router->post('/api/ap/invoices/:id/write-off', function($id) use ($c) { $c['ApController']->writeOff($id); });
    $router->get('/api/ap/prepay', function() use ($c) { $c['ApController']->prepay(); });  // dummy GET to register the view route
    $router->post('/api/ap/prepay', function() use ($c) { $c['ApController']->prepay(); });
    $router->get('/api/ap/aging', function() use ($c) { $c['ApController']->aging(); });
    $router->get('/api/ap/suppliers', function() use ($c) { $c['ApController']->suppliers(); });
    $router->get('/api/ap/suppliers/:id/statement', function($id) use ($c) { $c['ApController']->statement($id); });

    // AR (TK 131)
    $router->get('/ban/cong-no-phai-thu', function() { require __DIR__ . '/../public/views/ar_invoices.php'; });
    $router->get('/ban/phan-tich-tuoi-no', function() { require __DIR__ . '/../public/views/ar_aging.php'; });
    $router->get('/ban/so-chi-tiet-cong-no', function() { require __DIR__ . '/../public/views/ar_statement.php'; });
    $router->get('/api/ar/invoices', function() use ($c) { $c['ArController']->invoices(); });
    $router->post('/api/ar/invoices', function() use ($c) { $c['ArController']->create(); });
    $router->get('/api/ar/invoices/:id', function($id) use ($c) { $c['ArController']->get($id); });
    $router->post('/api/ar/invoices/:id/pay', function($id) use ($c) { $c['ArController']->pay($id); });
    $router->post('/api/ar/invoices/:id/return', function($id) use ($c) { $c['ArController']->returnGoods($id); });
    $router->post('/api/ar/invoices/:id/discount', function($id) use ($c) { $c['ArController']->discount($id); });
    $router->post('/api/ar/invoices/:id/write-off', function($id) use ($c) { $c['ArController']->writeOff($id); });
    $router->post('/api/ar/prepay', function() use ($c) { $c['ArController']->prepay(); });
    $router->get('/api/ar/aging', function() use ($c) { $c['ArController']->aging(); });
    $router->get('/api/ar/customers', function() use ($c) { $c['ArController']->customers(); });
    $router->get('/api/ar/customers/:id/statement', function($id) use ($c) { $c['ArController']->statement($id); });

    // Financial Statements
    $router->get('/bao-cao/tinh-hinh-tai-chinh', function() use ($c) { $c['FsController']->viewBC01(); });
    $router->get('/bao-cao/ket-qua-kinh-doanh', function() use ($c) { $c['FsController']->viewBC02(); });
    $router->get('/api/fs/bc01', function() use ($c) { $c['FsController']->bc01(); });
    $router->get('/api/fs/bc02', function() use ($c) { $c['FsController']->bc02(); });
    $router->get('/api/fs/tt99', function() use ($c) { $c['FsController']->tt99(); });

    // Period Management
    $router->get('/he-thong/quan-ly-ky', function() { require __DIR__ . '/../public/views/periods.php'; });
    $router->get('/api/periods', function() use ($c) { $c['PeriodController']->list(); });
    $router->get('/api/periods/:id', function($id) use ($c) { $c['PeriodController']->get($id); });
    $router->post('/api/periods', function() use ($c) { $c['PeriodController']->create(); });
    $router->post('/api/periods/:id/close', function($id) use ($c) { $c['PeriodController']->close($id); });
    $router->post('/api/periods/:id/reopen', function($id) use ($c) { $c['PeriodController']->reOpen($id); });
    $router->get('/api/periods/:id/can-close', function($id) use ($c) { $c['PeriodController']->canClose($id); });
    $router->post('/api/periods/:id/execute-closing', function($id) use ($c) { $c['PeriodController']->executeClosing($id); });
    $router->post('/api/periods/:id/archive', function($id) use ($c) { $c['PeriodController']->archive($id); });

    // GL (Sổ Cái)
    $router->get('/bao-cao/so-cai', function() use ($c) { $c['GlController']->view(); });
    $router->get('/api/gl/ledger', function() use ($c) { $c['GlController']->ledger(); });
    $router->get('/api/gl/accounts', function() use ($c) { $c['GlController']->accounts(); });

    // Audit Log
    $router->get('/he-thong/nhat-ky-hoat-dong', function() { require __DIR__ . '/../public/views/audit_log.php'; });
    $router->get('/api/audit-log', function() use ($c) { $c['AuditLogController']->list(); });
    $router->get('/api/audit-log/:id', function($id) use ($c) { $c['AuditLogController']->get($id); });

    // Dashboard API
    $router->get('/api/dashboard', function() use ($c) { $c['CashReportController']->kpis(); });

    // Physical Count
    $router->get('/kho/kiem-ke', function() { require __DIR__ . '/../public/views/physical_count.php'; });
    $router->get('/api/physical-count/sessions', function() use ($c) { $c['PhysicalCountController']->sessions(); });
    $router->get('/api/physical-count/lines/:id', function($id) use ($c) { $c['PhysicalCountController']->lines($id); });
    $router->post('/api/physical-count/sessions', function() use ($c) { $c['PhysicalCountController']->createSession(); });
    $router->post('/api/physical-count/adjust', function() use ($c) { $c['PhysicalCountController']->adjust(); });

    // Impairment
    $router->get('/kho/du-phong-giam-gia', function() { require __DIR__ . '/../public/views/impairment.php'; });
    $router->get('/api/impairments', function() use ($c) { $c['ImpairmentController']->list(); });
    $router->post('/api/impairments', function() use ($c) { $c['ImpairmentController']->record(); });
    $router->post('/api/impairments/reverse', function() use ($c) { $c['ImpairmentController']->reverse(); });

    // Promotional
    $router->post('/api/promotional/issue', function() use ($c) { $c['PromotionalController']->issue(); });

    // Periodic Inventory
    $router->get('/kho/kiem-ke-dinh-ky', function() { require __DIR__ . '/../public/views/periodic.php'; });
    $router->get('/api/periodic', function() use ($c) { $c['PeriodicController']->list(); });
    $router->post('/api/periodic/close', function() use ($c) { $c['PeriodicController']->close(); });

    // In Transit
    $router->get('/kho/hang-dang-di-duong', function() { require __DIR__ . '/../public/views/transit.php'; });
    $router->get('/api/inventory-transit', function() use ($c) { $c['InventoryTransitController']->list(); });
    $router->post('/api/inventory-transit', function() use ($c) { $c['InventoryTransitController']->record(); });
    $router->post('/api/inventory-transit/receive', function() use ($c) { $c['InventoryTransitController']->receive(); });
    $router->get('/api/transfers', function() use ($c) { $c['TransferController']->list(); });
    $router->post('/api/transfers', function() use ($c) { $c['TransferController']->transfer(); });
    $router->get('/api/transfers/items', function() use ($c) { $c['TransferController']->items(); });
    $router->get('/api/transfers/warehouses', function() use ($c) { $c['TransferController']->warehouses(); });

    // CSRF
    $router->get('/api/csrf-token', function() { JsonResponse::ok(['token' => Auth::csrfToken()]); });
}

$GLOBALS['router'] = new Router();
defineRoutes($GLOBALS['router']);
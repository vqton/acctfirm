<?php

use Accounting\Infrastructure\JsonResponse;

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

// === CASH (TK 111) & BANK (TK 112) ===
$router->get('/api/cash/receipts', function() use ($c) { $c['CashController']->receipts(); });
$router->post('/api/cash/receipts', function() use ($c) { $c['CashController']->createReceipt(); });
$router->get('/api/cash/payments', function() use ($c) { $c['CashController']->payments(); });
$router->get('/api/cash/payments/:id', function($id) use ($c) { $c['CashController']->getPayment($id); });
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
$router->post('/api/petty-cash/disburse-from-request', function() use ($c) { $c['PettyCashController']->disburseFromRequest(); });
$router->post('/api/petty-cash/replenish', function() use ($c) { $c['PettyCashController']->replenish(); });
$router->post('/api/petty-cash/close', function() use ($c) { $c['PettyCashController']->closeFund(); });
$router->get('/api/petty-cash/:id/transactions', function($id) use ($c) { $c['PettyCashController']->transactions($id); });

// === ADVANCE PAYMENT REQUEST (Mẫu 03-TT) ===
$router->post('/api/advance-payment/draft', function() use ($c) { $c['AdvancePaymentRequestController']->createDraft(); });
$router->post('/api/advance-payment/{id}/submit', function($id) use ($c) { $c['AdvancePaymentRequestController']->submit($id); });
$router->post('/api/advance-payment/{id}/approve', function($id) use ($c) { $c['AdvancePaymentRequestController']->approve($id); });
$router->post('/api/advance-payment/{id}/reject', function($id) use ($c) { $c['AdvancePaymentRequestController']->reject($id); });
$router->post('/api/advance-payment/{id}/cancel', function($id) use ($c) { $c['AdvancePaymentRequestController']->cancel($id); });
$router->post('/api/advance-payment/{id}/paid', function($id) use ($c) { $c['AdvancePaymentRequestController']->markPaid($id); });
$router->post('/api/advance-payment/{id}/settle', function($id) use ($c) { $c['AdvancePaymentRequestController']->settle($id); });
$router->get('/api/advance-payment/{id}', function($id) use ($c) { $c['AdvancePaymentRequestController']->getDetail($id); });
$router->get('/api/advance-payment/list', function() use ($c) { $c['AdvancePaymentRequestController']->list(); });

// === FX ===
$router->get('/api/fx/balances', function() use ($c) { $c['CashController']->fcBalances(); });
$router->post('/api/fx/revalue', function() use ($c) { $c['CashController']->fcRevalue(); });

// === CASH REPORTS ===
$router->get('/api/cash-reports/position', function() use ($c) { $c['CashReportController']->position(); });
$router->get('/api/cash-reports/bank-ledger', function() use ($c) { $c['CashReportController']->bankLedger(); });
$router->get('/api/cash-reports/daily-flow', function() use ($c) { $c['CashReportController']->dailyFlow(); });
$router->get('/api/cash-reports/concentration', function() use ($c) { $c['CashReportController']->concentration(); });
$router->get('/api/cash-reports/trend', function() use ($c) { $c['CashReportController']->trend(); });

// === BANK RECONCILIATION ===
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
$router->post('/api/bank-reconciliation/:id/import-csv', function($id) use ($c) { $c['BankReconciliationController']->importCsv($id); });
$router->get('/api/bank-reconciliation/bank-accounts', function() use ($c) { $c['BankReconciliationController']->bankAccounts(); });

<?php

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

// CSRF token
$router->get('/api/csrf-token', function() { JsonResponse::ok(['token' => Auth::csrfToken()]); });

// === INTERCOMPANY (Nội bộ) ===
$router->get('/api/ic/entities', function() use ($c) { $c['IntercompanyController']->entities(); });
$router->get('/api/ic/match/:entityId', function($entityId) use ($c) { $c['IntercompanyController']->match($entityId); });
$router->post('/api/ic/eliminate/:entityId', function($entityId) use ($c) { $c['IntercompanyController']->eliminate($entityId); });
$router->get('/api/ic/consolidated', function() use ($c) { $c['IntercompanyController']->consolidated(); });

// === OPENING BALANCES ===
$router->get('/api/opening-balances', function() use ($c) { $c['OpeningBalanceController']->list(); });
$router->post('/api/opening-balances/set', function() use ($c) { $c['OpeningBalanceController']->set(); });
$router->post('/api/opening-balances/:accountCode/:period/verify', function($accountCode, $period) use ($c) { $c['OpeningBalanceController']->verify($accountCode, $period); });
$router->post('/api/opening-balances/convert', function() use ($c) { $c['OpeningBalanceController']->convert(); });
$router->get('/he-thong/so-du-dau-ky', function() use ($c) { $c['OpeningBalanceController']->view(); });

// === DEBT COLLECTION ===
$router->get('/api/debt-collection/queue', function() use ($c) { $c['DebtCollectionController']->queueList(); });
$router->get('/api/debt-collection/queue/:id', function($id) use ($c) { $c['DebtCollectionController']->queueDetail($id); });
$router->post('/api/debt-collection/queue/generate', function() use ($c) { $c['DebtCollectionController']->queueGenerate(); });
$router->put('/api/debt-collection/queue/:id/assign', function($id) use ($c) { $c['DebtCollectionController']->queueAssign($id); });
$router->put('/api/debt-collection/queue/:id/hold', function($id) use ($c) { $c['DebtCollectionController']->queueHold($id); });
$router->put('/api/debt-collection/queue/:id/release', function($id) use ($c) { $c['DebtCollectionController']->queueRelease($id); });
$router->put('/api/debt-collection/queue/:id/priority', function($id) use ($c) { $c['DebtCollectionController']->queuePriority($id); });
$router->get('/api/debt-collection/queue/:id/activities', function($id) use ($c) { $c['DebtCollectionController']->activityList($id); });
$router->post('/api/debt-collection/queue/:id/activities', function($id) use ($c) { $c['DebtCollectionController']->activityCreate($id); });
$router->get('/api/debt-collection/queue/:id/promises', function($id) use ($c) { $c['DebtCollectionController']->promiseList($id); });
$router->post('/api/debt-collection/queue/:id/promises', function($id) use ($c) { $c['DebtCollectionController']->promiseCreate($id); });
$router->post('/api/debt-collection/promises/:id/keep', function($id) use ($c) { $c['DebtCollectionController']->promiseKeep($id); });
$router->post('/api/debt-collection/promises/:id/break', function($id) use ($c) { $c['DebtCollectionController']->promiseBreak($id); });
$router->post('/api/debt-collection/queue/:id/propose-writeoff', function($id) use ($c) { $c['DebtCollectionController']->proposeWriteOff($id); });
$router->get('/api/debt-collection/approvals', function() use ($c) { $c['DebtCollectionController']->approvalList(); });
$router->put('/api/debt-collection/approvals/:id/approve', function($id) use ($c) { $c['DebtCollectionController']->approvalApprove($id); });
$router->put('/api/debt-collection/approvals/:id/reject', function($id) use ($c) { $c['DebtCollectionController']->approvalReject($id); });
$router->post('/api/debt-collection/settlements', function() use ($c) { $c['DebtCollectionController']->settlementCreate(); });
$router->post('/api/debt-collection/settlements/:id/pay', function($id) use ($c) { $c['DebtCollectionController']->settlementPay($id); });
$router->get('/api/debt-collection/stats', function() use ($c) { $c['DebtCollectionController']->stats(); });
$router->get('/api/debt-collection/stats/collector/:id', function($id) use ($c) { $c['DebtCollectionController']->collectorStats($id); });

// Debt Collection views
$router->get('/thu-hoi-cong-no', function() use ($c) { $c['DebtCollectionController']->viewDashboard(); });
$router->get('/thu-hoi-cong-no/hang-doi', function() use ($c) { $c['DebtCollectionController']->viewQueue(); });
$router->get('/thu-hoi-cong-no/phe-duyet', function() use ($c) { $c['DebtCollectionController']->viewApprovals(); });

// === SUB-LEDGER REPORTS ===
$router->get('/api/reports/sub-ledger', function() use ($c) { $c['SubLedgerController']->getReport(); });
$router->post('/api/reports/sub-ledger/export', function() use ($c) { $c['SubLedgerController']->exportReport(); });
$router->get('/api/reports/sub-ledger/parameters', function() use ($c) { $c['SubLedgerController']->getParameters(); });
$router->get('/api/reports/sub-ledger/supported', function() use ($c) { $c['SubLedgerController']->getSupportedReports(); });
$router->get('/so-chi-tiet', function() use ($c) { $c['SubLedgerController']->viewIndex(); });

// === EXPORT (PDF/Excel/CSV) Gap 10 ===
// Endpoint xuất file thống nhất cho mọi báo cáo — client gửi JSON body
// Body: { format: "csv"|"xls"|"pdf", title, headers, rows, options }
// Response: file download với Content-Disposition: attachment
// Yêu cầu quyền report.export (Auth::requirePermission)
$router->post('/api/export', function() use ($c) { $c['ExportController']->export(); });

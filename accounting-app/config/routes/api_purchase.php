<?php

// === MUA HÀNG — PROCUREMENT ENGINE (PR → PO → GR → 3-Way Match) ===
// Purchase Requisitions
$router->get('/api/purchase/requisitions', function() use ($c) { $c['ProcurementController']->listPRs(); });
$router->get('/api/purchase/requisitions/:id', function($id) use ($c) { $c['ProcurementController']->getPR($id); });
$router->post('/api/purchase/requisitions', function() use ($c) { $c['ProcurementController']->createPR(); });
$router->post('/api/purchase/requisitions/:id/approve', function($id) use ($c) { $c['ProcurementController']->approvePR($id); });

// Purchase Orders
$router->get('/api/purchase/orders', function() use ($c) { $c['ProcurementController']->listPOs(); });
$router->get('/api/purchase/orders/:id', function($id) use ($c) { $c['ProcurementController']->getPO($id); });
$router->post('/api/purchase/orders', function() use ($c) { $c['ProcurementController']->createPO(); });

// Goods Receipts
$router->get('/api/purchase/receipts', function() use ($c) { $c['ProcurementController']->listGRs(); });
$router->get('/api/purchase/receipts/:id', function($id) use ($c) { $c['ProcurementController']->getGR($id); });
$router->post('/api/purchase/receipts', function() use ($c) { $c['ProcurementController']->createGR(); });

// Invoice Matching
$router->get('/api/purchase/matches', function() use ($c) { $c['ProcurementController']->listMatches(); });
$router->post('/api/purchase/matches', function() use ($c) { $c['ProcurementController']->createMatch(); });

// Budget Control
$router->get('/api/purchase/budgets', function() use ($c) { $c['ProcurementController']->listBudgets(); });
$router->post('/api/purchase/budgets', function() use ($c) { $c['ProcurementController']->setBudget(); });
$router->get('/api/purchase/budgets/check', function() use ($c) { $c['ProcurementController']->checkBudget(); });

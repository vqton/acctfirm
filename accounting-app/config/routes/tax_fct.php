<?php

// === VAT (GTGT) ===
$router->get('/api/vat/declarations', function() use ($c) { $c['VatController']->list(); });
$router->get('/api/vat/declarations/:id', function($id) use ($c) { $c['VatController']->get($id); });
$router->post('/api/vat/declarations/prepare', function() use ($c) { $c['VatController']->prepare(); });
$router->post('/api/vat/declarations/:id/finalise', function($id) use ($c) { $c['VatController']->finalise($id); });
$router->get('/api/vat/scan-non-deductible/:period', function($period) use ($c) { $c['VatController']->scanNonDeductible($period); });
$router->get('/api/vat/reconcile/:period', function($period) use ($c) { $c['VatController']->reconcile($period); });

// === CIT (TNDN) ===
$router->get('/api/cit/calculations', function() use ($c) { $c['CitController']->list(); });
$router->get('/api/cit/calculations/:id', function($id) use ($c) { $c['CitController']->get($id); });
$router->post('/api/cit/calculations/prepare', function() use ($c) { $c['CitController']->prepare(); });
$router->post('/api/cit/calculations/:id/finalise', function($id) use ($c) { $c['CitController']->finalise($id); });
$router->get('/api/cit/scan-non-deductible/:period', function($period) use ($c) { $c['CitController']->scanNonDeductible($period); });
$router->get('/api/cit/loss-carryforward/:period', function($period) use ($c) { $c['CitController']->lossCarryforward($period); });

// === FCT (Thuế nhà thầu nước ngoài) ===
$router->get('/api/fct/contracts', function() use ($c) { $c['FctController']->listContracts(); });
$router->get('/api/fct/contracts/:id', function($id) use ($c) { $c['FctController']->getContract($id); });
$router->post('/api/fct/calculate', function() use ($c) { $c['FctController']->calculate(); });
$router->post('/api/fct/contracts', function() use ($c) { $c['FctController']->record(); });
$router->post('/api/fct/contracts/:id/cancel', function($id) use ($c) { $c['FctController']->cancel($id); });
$router->get('/api/fct/declarations', function() use ($c) { $c['FctController']->listDeclarations(); });
$router->get('/api/fct/declarations/:id', function($id) use ($c) { $c['FctController']->getDeclaration($id); });
$router->post('/api/fct/declarations/prepare', function() use ($c) { $c['FctController']->prepareDeclaration(); });
$router->post('/api/fct/declarations/:id/finalise', function($id) use ($c) { $c['FctController']->finaliseDeclaration($id); });
$router->get('/api/fct/declarations/:id/export', function($id) use ($c) { $c['FctController']->export($id); });

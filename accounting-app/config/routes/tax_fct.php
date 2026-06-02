<?php

// === VAT (GTGT) ===
$router->get('/api/vat/declarations', function() use ($c) { $c['VatController']->list(); });
$router->get('/api/vat/declarations/:id', function($id) use ($c) { $c['VatController']->get($id); });
$router->post('/api/vat/declarations/prepare', function() use ($c) { $c['VatController']->prepare(); });
$router->post('/api/vat/declarations/:id/finalise', function($id) use ($c) { $c['VatController']->finalise($id); });
$router->post('/api/vat/declarations/:id/approve', function($id) use ($c) { $c['VatController']->approve($id); });
$router->post('/api/vat/declarations/:id/reject', function($id) use ($c) { $c['VatController']->reject($id); });
$router->post('/api/vat/declarations/adjustment', function() use ($c) { $c['VatController']->createAdjustment(); });
$router->get('/api/vat/declarations/:id/export-htkk-xml', function($id) use ($c) { $c['VatController']->exportHtkkXml($id); });
$router->get('/api/vat/scan-non-deductible/:period', function($period) use ($c) { $c['VatController']->scanNonDeductible($period); });
$router->get('/api/vat/reconcile/:period', function($period) use ($c) { $c['VatController']->reconcile($period); });
$router->get('/api/vat/reconcile-einvoice/:period', function($period) use ($c) { $c['VatController']->reconcileWithEInvoice($period); });
$router->get('/api/vat/non-deductible-invoices/:period', function($period) use ($c) { $c['VatController']->getNonDeductibleInvoices($period); });
$router->get('/api/vat/input-checklist/:period', function($period) use ($c) { $c['VatController']->getInputVatChecklist($period); });

// === CIT (TNDN) ===
$router->get('/api/cit/calculations', function() use ($c) { $c['CitController']->list(); });
$router->get('/api/cit/calculations/:id', function($id) use ($c) { $c['CitController']->get($id); });
$router->post('/api/cit/calculations/prepare', function() use ($c) { $c['CitController']->prepare(); });
$router->post('/api/cit/calculations/:id/finalise', function($id) use ($c) { $c['CitController']->finalise($id); });
$router->get('/api/cit/scan-non-deductible/:period', function($period) use ($c) { $c['CitController']->scanNonDeductible($period); });
$router->get('/api/cit/loss-carryforward/:period', function($period) use ($c) { $c['CitController']->lossCarryforward($period); });
$router->get('/api/cit/declaration/:id/export-xml', function($id) use ($c) { $c['CitController']->exportXml($id); });
$router->post('/api/cit/declaration/calculate', function() use ($c) {
    $data = json_decode(file_get_contents('php://input'), true);
    $period = $data['period'] ?? date('Y-m');
    $engine = $c['citDeclarationEngine'] ?? new \Accounting\Domain\Service\CitDeclarationEngine($c['pdo']);
    header('Content-Type: application/json');
    echo json_encode(['data' => $engine->calculateIndicators($period)]);
});

// === PIT (TNCN) ===
$router->post('/api/pit/prepare-monthly', function() use ($c) {
    $data = json_decode(file_get_contents('php://input'), true);
    $period = $data['period'] ?? date('Y-m');
    header('Content-Type: application/json');
    echo json_encode(['data' => $c['pitDeclarationService']->prepareMonthly($period)]);
});
$router->post('/api/pit/prepare-annual', function() use ($c) {
    $data = json_decode(file_get_contents('php://input'), true);
    $year = $data['year'] ?? date('Y');
    header('Content-Type: application/json');
    echo json_encode(['data' => $c['pitDeclarationService']->prepareAnnual((string)$year)]);
});
$router->get('/api/pit/export-kk-xml/:period', function($period) use ($c) {
    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="05-KK-TNCN-' . $period . '.xml"');
    echo $c['pitDeclarationService']->exportKkXml($period);
});
$router->get('/api/pit/export-qtt-xml/:year', function($year) use ($c) {
    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="05-QTT-TNCN-' . $year . '.xml"');
    echo $c['pitDeclarationService']->exportQttXml($year);
});

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

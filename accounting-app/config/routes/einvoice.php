<?php
// === E-INVOICE ROUTES ===
// Module: einvoice — Hóa đơn điện tử (TT 32/2025)
// Permissions: einvoice.{read|create|update|delete}

/* @var $router Accounting\Interfaces\HTTP\Router */
/* @var $c array */

$einv = $c['EInvoiceController'];

$router->get('/api/einvoice', fn() => $einv->list());
$router->get('/api/einvoice/:id', fn(string $id) => $einv->get($id));
$router->post('/api/einvoice/create', fn() => $einv->create());
$router->post('/api/einvoice/adjust', fn() => $einv->adjust());
$router->post('/api/einvoice/replace', fn() => $einv->replace());
$router->post('/api/einvoice/cancel', fn() => $einv->cancel());
$router->post('/api/einvoice/retry', fn() => $einv->retry());
$router->get('/api/einvoice/download/:id', fn(string $id) => $einv->downloadXml($id));
$router->get('/api/vat/declarations/:id/export', fn(string $id) => $einv->exportVatXml($id));
$router->post('/api/vat/declarations/calculate', fn() => $einv->calculateVatIndicators());

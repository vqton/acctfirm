<?php
// Routes chức năng Quản lý Hợp đồng (Contract Management)
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/** @var \Accounting\Infrastructure\Router $router */
/** @var array $c container */

$router->get('/api/contracts/dashboard', function() use ($c) {
    $c['ContractManagementController']->dashboard();
});
$router->get('/api/contracts/:id/full', function($id) use ($c) {
    $c['ContractManagementController']->getDetail($id);
});
$router->post('/api/contracts/:id/link', function($id) use ($c) {
    $c['ContractManagementController']->linkTransaction($id);
});
$router->post('/api/contracts/:id/payment-schedule', function($id) use ($c) {
    $c['ContractManagementController']->addPaymentSchedule($id);
});
$router->post('/api/contracts/:id/record-payment', function($id) use ($c) {
    $c['ContractManagementController']->recordPaymentSchedule($id);
});
$router->post('/api/contracts/:id/amendment', function($id) use ($c) {
    $c['ContractManagementController']->addAmendment($id);
});
$router->post('/api/contracts/:id/liquidate', function($id) use ($c) {
    $c['ContractManagementController']->liquidate($id);
});
$router->get('/api/contracts/export', function() use ($c) {
    $c['ContractManagementController']->exportContract();
});
$router->get('/contracts', function() use ($c) {
    $c['ContractManagementController']->viewIndex();
});

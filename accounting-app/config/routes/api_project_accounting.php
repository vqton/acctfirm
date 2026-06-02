<?php
/** @var \Accounting\Infrastructure\Router $router */
/** @var array $c container */

$router->get('/api/projects/dashboard', function() use ($c) { $c['ProjectAccountingController']->dashboard(); });
$router->get('/api/projects/:id/report', function($id) use ($c) { $c['ProjectAccountingController']->report($id); });
$router->post('/api/projects/:id/allocate-cost', function($id) use ($c) { $c['ProjectAccountingController']->allocateCost($id); });
$router->post('/api/projects/:id/billing', function($id) use ($c) { $c['ProjectAccountingController']->createBilling($id); });
$router->post('/api/projects/:id/recognize-revenue', function($id) use ($c) { $c['ProjectAccountingController']->recognizeRevenue($id); });
$router->post('/api/projects/:id/finalize', function($id) use ($c) { $c['ProjectAccountingController']->finalize($id); });
$router->post('/api/projects/:id/budget', function($id) use ($c) { $c['ProjectAccountingController']->setBudget($id); });
$router->get('/api/projects/:id/export', function($id) use ($c) { $c['ProjectAccountingController']->exportReport($id); });
$router->get('/du-an', function() use ($c) { $c['ProjectAccountingController']->viewIndex(); });

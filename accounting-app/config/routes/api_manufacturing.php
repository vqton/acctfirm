<?php
/** @var \Accounting\Infrastructure\Router $router */
/** @var array $c container */

// BOM
$router->get('/api/san-xuat/bom', function() use ($c) { $c['ManufacturingController']->listBom(); });
$router->get('/api/san-xuat/bom/:id', function($id) use ($c) { $c['ManufacturingController']->getBom($id); });
$router->post('/api/san-xuat/bom', function() use ($c) { $c['ManufacturingController']->createBom(); });
$router->post('/api/san-xuat/bom/:id/activate', function($id) use ($c) { $c['ManufacturingController']->activateBom($id); });

// Production Orders
$router->get('/api/san-xuat/dashboard', function() use ($c) { $c['ManufacturingController']->dashboard(); });
$router->get('/api/san-xuat', function() use ($c) { $c['ManufacturingController']->listOrders(); });
$router->get('/api/san-xuat/:id/report', function($id) use ($c) { $c['ManufacturingController']->getOrder($id); });
$router->post('/api/san-xuat', function() use ($c) { $c['ManufacturingController']->createOrder(); });
$router->post('/api/san-xuat/:id/release', function($id) use ($c) { $c['ManufacturingController']->releaseOrder($id); });
$router->post('/api/san-xuat/:id/issue-material', function($id) use ($c) { $c['ManufacturingController']->issueMaterial($id); });
$router->post('/api/san-xuat/:id/labor', function($id) use ($c) { $c['ManufacturingController']->recordLabor($id); });
$router->post('/api/san-xuat/:id/overhead', function($id) use ($c) { $c['ManufacturingController']->recordOverhead($id); });
$router->post('/api/san-xuat/:id/complete', function($id) use ($c) { $c['ManufacturingController']->completeOrder($id); });
$router->post('/api/san-xuat/:id/calculate-cost', function($id) use ($c) { $c['ManufacturingController']->calculateCost($id); });
$router->post('/api/san-xuat/:id/close', function($id) use ($c) { $c['ManufacturingController']->closeOrder($id); });
$router->get('/api/san-xuat/:id/export', function($id) use ($c) { $c['ManufacturingController']->exportReport($id); });

// View
$router->get('/san-xuat', function() use ($c) { $c['ManufacturingController']->viewIndex(); });

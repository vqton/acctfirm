<?php
/** @var \Accounting\Infrastructure\Router $router */
/** @var array $c container */

$router->get('/api/ngan-sach', function() use ($c) { $c['BudgetController']->scenarios(); });
$router->post('/api/ngan-sach', function() use ($c) { $c['BudgetController']->createScenario(); });
$router->post('/api/ngan-sach/:id/activate', function($id) use ($c) { $c['BudgetController']->activateScenario($id); });
$router->get('/api/ngan-sach/:id/lines', function($id) use ($c) { $c['BudgetController']->getBudgetLines($id); });
$router->post('/api/ngan-sach/:id/lines', function($id) use ($c) { $c['BudgetController']->setBudget($id); });
$router->get('/api/ngan-sach/:id/variance', function($id) use ($c) { $c['BudgetController']->variance($id); });
$router->get('/api/ngan-sach/dashboard', function() use ($c) { $c['BudgetController']->dashboard(); });
$router->get('/api/ngan-sach/:id/export', function($id) use ($c) { $c['BudgetController']->export($id); });
$router->get('/ngan-sach', function() use ($c) { $c['BudgetController']->viewIndex(); });

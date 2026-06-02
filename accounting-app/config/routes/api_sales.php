<?php
// === SALES ORDER ===
$router->get('/api/sales/orders', function() use ($c) { $c['SalesOrderController']->list(); });
$router->post('/api/sales/orders', function() use ($c) { $c['SalesOrderController']->create(); });
$router->get('/api/sales/orders/search', function() use ($c) { $c['SalesOrderController']->search(); });
$router->get('/api/sales/orders/dashboard', function() use ($c) { $c['SalesOrderController']->dashboard(); });
$router->get('/api/sales/orders/export', function() use ($c) { $c['SalesOrderController']->export(); });
$router->get('/api/sales/orders/:id', function($id) use ($c) { $c['SalesOrderController']->get($id); });
$router->put('/api/sales/orders/:id', function($id) use ($c) { $c['SalesOrderController']->update($id); });
$router->delete('/api/sales/orders/:id', function($id) use ($c) { $c['SalesOrderController']->delete($id); });
$router->post('/api/sales/orders/:id/confirm', function($id) use ($c) { $c['SalesOrderController']->confirm($id); });
$router->post('/api/sales/orders/:id/ship', function($id) use ($c) { $c['SalesOrderController']->ship($id); });
$router->post('/api/sales/orders/:id/invoice', function($id) use ($c) { $c['SalesOrderController']->invoice($id); });
$router->post('/api/sales/orders/:id/payment', function($id) use ($c) { $c['SalesOrderController']->receivePayment($id); });
$router->post('/api/sales/orders/:id/cancel', function($id) use ($c) { $c['SalesOrderController']->cancel($id); });
$router->get('/api/sales/orders/:id/links', function($id) use ($c) { $c['SalesOrderController']->links($id); });

// === VIEWS ===
$router->get('/ban/don-dat-hang', function() use ($c) { $c['SalesOrderController']->viewIndex(); });
$router->get('/ban/don-dat-hang/them', function() use ($c) { $c['SalesOrderController']->viewForm(); });
$router->get('/ban/don-dat-hang/:id', function($id) use ($c) { $c['SalesOrderController']->viewForm($id); });

<?php

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Domain\ValueObject\VnWords;

// === XÁC THỰC & ĐĂNG NHẬP ===
$router->get('/dang-nhap', function() { require __DIR__ . '/../../public/views/login.php'; });
$router->post('/api/auth/login', function() use ($c) { $c['AuthController']->login(); });
$router->post('/api/auth/logout', function() use ($c) { $c['AuthController']->logout(); });
$router->get('/api/auth/me', function() use ($c) { $c['AuthController']->me(); });
$router->get('/api/auth/csrf', function() { JsonResponse::ok(['token' => Auth::csrfToken()]); });
$router->get('/api/utils/to-words', function() {
    $n = (float)($_GET['amount'] ?? 0);
    JsonResponse::ok(['words' => VnWords::toWords($n)]);
});

// === QUẢN LÝ NGƯỜI DÙNG ===
$router->get('/api/users', function() use ($c) { $c['UserController']->list(); });
$router->post('/api/users', function() use ($c) { $c['UserController']->create(); });
$router->put('/api/users/:id', function($id) use ($c) { $c['UserController']->update($id); });
$router->delete('/api/users/:id', function($id) use ($c) { $c['UserController']->delete($id); });

// === QUẢN LÝ VAI TRÒ ===
$router->get('/api/roles', function() use ($c) { $c['RoleController']->list(); });
$router->post('/api/roles', function() use ($c) { $c['RoleController']->create(); });
$router->put('/api/roles/:id', function($id) use ($c) { $c['RoleController']->update($id); });
$router->delete('/api/roles/:id', function($id) use ($c) { $c['RoleController']->delete($id); });
$router->get('/api/roles/:id/permissions', function($id) use ($c) { $c['RoleController']->getPermissions($id); });
$router->put('/api/roles/:id/permissions', function($id) use ($c) { $c['RoleController']->updatePermissions($id); });
$router->get('/api/user-management/users', function() use ($c) { $c['UserController']->listWithRoles(); });

// User & Role management views
$router->get('/he-thong/nguoi-dung', function() { require __DIR__ . '/../../public/views/users.php'; });
$router->get('/he-thong/vai-tro', function() { require __DIR__ . '/../../public/views/roles.php'; });

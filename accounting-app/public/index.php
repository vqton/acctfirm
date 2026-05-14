<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = __DIR__ . $uri;
$ext = pathinfo($filePath, PATHINFO_EXTENSION);
$staticExts = ['css','js','png','jpg','jpeg','gif','svg','ico','woff','woff2','map'];
if ($uri !== '/' && file_exists($filePath) && in_array($ext, $staticExts)) {
    $mime = ['css'=>'text/css','js'=>'application/javascript','png'=>'image/png',
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','svg'=>'image/svg+xml',
        'ico'=>'image/x-icon','woff'=>'font/woff','woff2'=>'font/woff2','map'=>'application/json'];
    header('Content-Type: ' . ($mime[$ext] ?? 'application/octet-stream'));
    readfile($filePath);
    return;
}

set_exception_handler(function (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal server error',
        'code' => 500,
        'message' => $e->getMessage(),
    ]);
    exit;
});

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

$GLOBALS['request_id'] = uniqid('req_', true);

session_start();

// Auth guard: /api/* and view pages require login, except login page and auth API
$publicPaths = ['/', '/dang-nhap', '/api/auth/login'];
if (!isset($_SESSION['user']) && !in_array($uri, $publicPaths) && !str_starts_with($uri, '/api/auth/')) {
    // API calls return 401, page requests redirect to login
    if (str_starts_with($uri, '/api/')) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Chưa đăng nhập'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: /dang-nhap');
    exit;
}

require __DIR__ . '/../config/services.php';
require __DIR__ . '/../config/routes.php';

$GLOBALS['router']->dispatch();

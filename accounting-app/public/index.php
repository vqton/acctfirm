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

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../config/services.php';
require __DIR__ . '/../config/routes.php';

$router = $GLOBALS['router'];
$container = $GLOBALS['container'];

$router->dispatch();
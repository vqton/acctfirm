<?php

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use Accounting\Infrastructure\Logging\Logger;

$GLOBALS['request_start'] = microtime(true);
Logger::init();
Logger::startRequest();
$GLOBALS['request_id'] = uniqid('req_', true);
$GLOBALS['_logged'] = false;
$GLOBALS['_req_body'] = $_SERVER['REQUEST_METHOD'] === 'POST' ? file_get_contents('php://input') : null;

ob_start();

register_shutdown_function(function () {
    $output = ob_get_clean();

    $status = http_response_code();
    $duration = (microtime(true) - ($GLOBALS['request_start'] ?? microtime(true))) * 1000;
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $size = strlen($output);

    if (!($GLOBALS['_logged'] ?? false)) {
        $isCash = str_starts_with($uri, '/api/cash') || str_starts_with($uri, '/api/bank');
        $tag = $isCash ? "\033[36m[Cash]\033[0m " : '';

        Logger::printRequest($method, $uri, $status, $duration, $size, $tag);

        if ($isCash && $method === 'POST' && $GLOBALS['_req_body']) {
            Logger::printRequestBody("{$GLOBALS['_req_body']}");
        }

        $queries = Logger::getQueries();
        if (!empty($queries) && $uri !== '/' && !str_contains($uri, '.')) {
            foreach ($queries as $q) {
                Logger::printSQL($q['sql'], $q['params'], $q['duration']);
            }
            $totalDb = array_sum(array_column($queries, 'duration'));
            Logger::writeRaw("  \033[90m── " . count($queries) . " queries, {$totalDb}ms total\033[0m\n");
        }

        if ($output !== false && $output !== '') {
            Logger::printErrorBody($status, $output);
        }
    }

    if ($output !== false && $output !== '') {
        echo $output;
    }
});

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('..', '', $uri);
$filePath = __DIR__ . $uri;
$realBase = realpath(__DIR__);
$realPath = realpath($filePath);
$ext = pathinfo($filePath, PATHINFO_EXTENSION);
$staticExts = ['css','js','png','jpg','jpeg','gif','svg','ico','woff','woff2','map'];
if ($uri !== '/' && $realPath !== false && str_starts_with($realPath, $realBase) && file_exists($filePath) && in_array($ext, $staticExts)) {
    $mime = ['css'=>'text/css','js'=>'application/javascript','png'=>'image/png',
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','svg'=>'image/svg+xml',
        'ico'=>'image/x-icon','woff'=>'font/woff','woff2'=>'font/woff2','map'=>'application/json'];
    header('Content-Type: ' . ($mime[$ext] ?? 'application/octet-stream'));
    readfile($filePath);
    $GLOBALS['_logged'] = true;
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
});

use Accounting\Infrastructure\SessionMiddleware;

SessionMiddleware::open();

// Auth guard: /api/* and view pages require login, except login page and auth API
$publicPaths = ['/', '/dang-nhap', '/api/auth/login', '/api/utils/to-words'];
if (!isset($_SESSION['user']) && !in_array($uri, $publicPaths) && !str_starts_with($uri, '/api/auth/')) {
    SessionMiddleware::close();
    if (str_starts_with($uri, '/api/')) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Chưa đăng nhập'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $return = urlencode($uri);
    header("Location: /dang-nhap?return={$return}");
    exit;
}

// Release session lock for API routes — concurrent AJAX calls no longer block
// Exclude auth endpoints that need write access (login, csrf)
$writeEndpoints = ['/api/auth/login', '/api/auth/csrf'];
if (str_starts_with($uri, '/api/') && !in_array($uri, $writeEndpoints)) {
    SessionMiddleware::close();
}

require __DIR__ . '/../config/services.php';
require __DIR__ . '/../config/routes.php';

$GLOBALS['router']->dispatch();

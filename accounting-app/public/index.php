<?php

// === ENTRY POINT — Mọi request đều đi qua đây ===
// Đây là front controller của toàn bộ hệ thống kế toán
// Xử lý: autoload → khởi tạo request → static files → auth guard → routing

// Đặt timezone Việt Nam cho toàn bộ ứng dụng
// (DB thường dùng VN timezone; nếu không set, PHP mặc định UTC → lệch 7h khi so sánh timestamp)
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Autoload PSR-4-like: Accounting\ → src/Accounting/
// KHÔNG dùng Composer autoload — tự viết để kiểm soát hoàn toàn
// Mapping: Accounting\Domain\Service\JournalService → src/Accounting/Domain/Service/JournalService.php
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
use Accounting\Infrastructure\Logging\ActionJournal;
use Accounting\Infrastructure\JsonResponse;

// === KHỞI TẠO REQUEST ===
// Khởi tạo các global cần thiết cho toàn bộ vòng đời request
// $request_start: tính thời gian xử lý request — log duration cuối request
// $request_id: unique ID cho mỗi request — dùng trong audit trail để trace
// $_req_body: lưu request body để log (vì sau khi controller đọc php://input thì không đọc lại được)
$GLOBALS['request_start'] = microtime(true);
Logger::init();
ActionJournal::init();
Logger::startRequest();
$GLOBALS['request_id'] = uniqid('req_', true);
$GLOBALS['_logged'] = false;
$GLOBALS['_req_body'] = $_SERVER['REQUEST_METHOD'] === 'POST' ? file_get_contents('php://input') : null;

// === SHUTDOWN HANDLER — log request + action journal sau khi response kết thúc ===
// Dùng output buffering (ob_start) để bắt toàn bộ output → ghi ActionJournal trước khi gửi response
// Thứ tự: ob_start() → controller xử lý → shutdown function lấy output → log → echo output thật
// RỦI RO: Nếu có fatal error trước ob_start(), output không được bắt — mất ActionJournal entry
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

    // Ghi ActionJournal — ghi mọi request vào file .jsonl để kiểm toán
    // Chỉ ghi một lần, tránh ghi lại nếu file tĩnh đã set _logged = true
    // User ID lấy từ $_SESSION — nếu chưa login thì ghi null (anonymous)
    if (!($GLOBALS['_logged'] ?? false)) {
        ActionJournal::record(
            $method,
            $uri,
            $status,
            $GLOBALS['_req_body'] ?? null,
            $output,
            $duration,
            $GLOBALS['_req_user_id'] ?? $_SESSION['user']['id'] ?? null,
            $GLOBALS['request_id'] ?? null
        );
    }

    // Output thực tế gửi về client — lấy từ buffer đã bắt ở ob_start()
    // Nếu output = false (ob_get_clean thất bại) thì không echo
    if ($output !== false && $output !== '') {
        echo $output;
    }
});

// === STATIC FILE SERVING — phục vụ file tĩnh (CSS, JS, images) ===
// Chống path traversal bằng realpath() + str_starts_with()
// Chỉ cho phép các extension: css, js, png, jpg, jpeg, gif, svg, ico, woff, woff2, map
// RỦI RO: Nếu cho phép .php, .phtml trong staticExts → attacker có thể chạy PHP tùy ý
// realpath(): chuẩn hóa đường dẫn, loại bỏ ../ — chống path traversal
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

// === XỬ LÝ EXCEPTION — trả về JSON lỗi 500 cho mọi API ===
// Bắt mọi exception không được xử lý — tránh leak stack trace ra client
// RỦI RO: Trong production, $e->getMessage() có thể lộ đường dẫn file, tên bảng, cấu trúc DB
// Cân nhắc: Dùng logger để ghi chi tiết lỗi, chỉ trả về message chung "Internal Server Error"
set_exception_handler(function (\Throwable $e) {
    JsonResponse::error($e->getMessage(), 500);
});

use Accounting\Infrastructure\SessionMiddleware;

// === XÁC THỰC NGƯỜI DÙNG ===
// Mở session — kiểm tra timeout, tự động đăng xuất nếu hết 8h
SessionMiddleware::open();
$GLOBALS['_req_user_id'] = $_SESSION['user']['id'] ?? null;

// === AUTH GUARD — bảo vệ tất cả API và trang trừ trang login ===
// publicPaths: danh sách các đường dẫn không cần đăng nhập
$publicPaths = ['/dang-nhap', '/api/auth/login', '/api/utils/to-words'];
if (!isset($_SESSION['user']) && !in_array($uri, $publicPaths) && !str_starts_with($uri, '/api/auth/')) {
    SessionMiddleware::close();
    if (str_starts_with($uri, '/api/')) {
        JsonResponse::error('Chưa đăng nhập', 401);
        exit;
    }
    $return = urlencode($uri);
    header("Location: /dang-nhap?return={$return}");
    exit;
}

// === GIẢI PHÓNG SESSION — cho phép AJAX concurrent khi gọi API ===
// Không giải phóng cho auth endpoints (login, csrf) vì cần ghi session
// LƯU Ý QUAN TRỌNG: Sau khi close(), không được ghi $_SESSION — dữ liệu sẽ bị mất
// writeEndpoints: login (ghi session mới), csrf (tạo token mới), logout (xóa session)
$writeEndpoints = ['/api/auth/login', '/api/auth/csrf', '/api/auth/logout'];
if (str_starts_with($uri, '/api/') && !in_array($uri, $writeEndpoints)) {
    SessionMiddleware::close();
}

require __DIR__ . '/../config/services.php';
require __DIR__ . '/../config/routes.php';

// === DISPATCH — router tìm route phù hợp và gọi controller ===
// Router đã được khởi tạo trong config/routes.php
// Nếu không tìm thấy route → HttpError::notFound() → JSON 404
$GLOBALS['router']->dispatch();

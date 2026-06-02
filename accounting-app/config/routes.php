<?php

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Domain\ValueObject\VnWords;
use Accounting\Interfaces\HTTP\Router;

// Route definitions — toàn bộ endpoints của hệ thống kế toán
// Mỗi route được nhóm theo module nghiệp vụ (Auth, User, Item, Customer, ...)
// Pattern: /api/{module}/{action}[/:id] — RESTful, hỗ trợ tham số động
// Controller lấy từ DI container $GLOBALS['container'] — đảm bảo sẵn sàng khi gọi
function defineRoutes(Router $router): void
{
    // $c: DI container chứa tất cả controller instances
    $c = $GLOBALS['container'];

    require __DIR__ . '/routes/auth.php';
    require __DIR__ . '/routes/api_master_data.php';
    require __DIR__ . '/routes/api_cash.php';
    require __DIR__ . '/routes/api_inventory.php';
    require __DIR__ . '/routes/api_financial.php';
    require __DIR__ . '/routes/api_payroll.php';
    require __DIR__ . '/routes/api_purchase.php';
    require __DIR__ . '/routes/tax_fct.php';
    require __DIR__ . '/routes/einvoice.php';
    require __DIR__ . '/routes/misc.php';
    require __DIR__ . '/routes/views.php';
}

$GLOBALS['router'] = new Router();
defineRoutes($GLOBALS['router']);

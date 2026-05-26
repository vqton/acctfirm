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
    // Mỗi route gọi controller từ container — đảm bảo mọi dependency đã được inject
    $c = $GLOBALS['container'];
    // === XÁC THỰC & ĐĂNG NHẬP ===
    // Bảo vệ toàn bộ hệ thống — không cho phép truy cập nếu chưa đăng nhập
    // Auth login/logout/me/csrf là các endpoint public (không cần auth guard)
    $router->get('/dang-nhap', function() { require __DIR__ . '/../public/views/login.php'; });
    // login: xác thực username/password → tạo session → regenerate session ID (chống fixation)
    // logout: xóa session + hủy cookie → redirect về trang đăng nhập
    // me: lấy thông tin user hiện tại + permissions — dùng cho UI render menu
    // csrf: cung cấp CSRF token cho client — bắt buộc cho mọi POST/PUT/DELETE
    $router->post('/api/auth/login', function() use ($c) { $c['AuthController']->login(); });
    $router->post('/api/auth/logout', function() use ($c) { $c['AuthController']->logout(); });
    $router->get('/api/auth/me', function() use ($c) { $c['AuthController']->me(); });
    $router->get('/api/auth/csrf', function() { JsonResponse::ok(['token' => Auth::csrfToken()]); });
    $router->get('/api/utils/to-words', function() {
        $n = (float)($_GET['amount'] ?? 0);
        JsonResponse::ok(['words' => VnWords::toWords($n)]);
    });

    // === QUẢN LÝ NGƯỜI DÙNG ===
    // User management — CRUD người dùng hệ thống, gán vai trò, phân quyền
    // Chỉ admin/Kế toán trưởng mới được quản lý người dùng
    $router->get('/api/users', function() use ($c) { $c['UserController']->list(); });
    $router->post('/api/users', function() use ($c) { $c['UserController']->create(); });
    $router->put('/api/users/:id', function($id) use ($c) { $c['UserController']->update($id); });
    $router->delete('/api/users/:id', function($id) use ($c) { $c['UserController']->delete($id); });

    // === QUẢN LÝ VAI TRÒ ===
    // Role management — RBAC roles, gán permission cho từng module/action
    // Quyết định ai có quyền xem/tạo/sửa/xóa/post/print trên mỗi module
    $router->get('/api/roles', function() use ($c) { $c['RoleController']->list(); });
    $router->post('/api/roles', function() use ($c) { $c['RoleController']->create(); });
    $router->put('/api/roles/:id', function($id) use ($c) { $c['RoleController']->update($id); });
    $router->delete('/api/roles/:id', function($id) use ($c) { $c['RoleController']->delete($id); });
    $router->get('/api/roles/:id/permissions', function($id) use ($c) { $c['RoleController']->getPermissions($id); });
    $router->put('/api/roles/:id/permissions', function($id) use ($c) { $c['RoleController']->updatePermissions($id); });
    $router->get('/api/user-management/users', function() use ($c) { $c['UserController']->listWithRoles(); });

    // === TRANG CHỦ & DANH MỤC ===
    // Frontend pages — các trang HTML render từ PHP views
    // View pattern: require + ob_start trong layout.php
    $router->get('/', function() { require __DIR__ . '/../public/views/dashboard.php'; });
    $router->get('/danh-muc/vat-tu', function() { require __DIR__ . '/../public/views/items.php'; });
    $router->get('/danh-muc/khach-hang', function() { require __DIR__ . '/../public/views/customers.php'; });
    $router->get('/danh-muc/nha-cung-cap', function() { require __DIR__ . '/../public/views/suppliers.php'; });

    // === VẬT TƯ, HÀNG HÓA (TK 152, 153, 155, 156) ===
    // Item API — danh mục vật tư, hàng hóa, thành phẩm
    // Liên quan đến InventoryService: nhập/xuất kho, tính giá xuất (FIFO/Bình quân)
    $router->get('/api/items', function() use ($c) { $c['ItemController']->list(); });
    $router->get('/api/items/:id', function($id) use ($c) { $c['ItemController']->get($id); });
    $router->post('/api/items', function() use ($c) { $c['ItemController']->create(); });
    $router->put('/api/items/:id', function($id) use ($c) { $c['ItemController']->update($id); });
    $router->delete('/api/items/:id', function($id) use ($c) { $c['ItemController']->delete($id); });

    // === KHÁCH HÀNG (TK 131) ===
    // Customer API — danh mục khách hàng, phục vụ quản lý công nợ phải thu
    // Liên quan đến ArService: bán hàng, thu tiền, chiết khấu, trả lại
    $router->get('/api/customers', function() use ($c) { $c['CustomerController']->list(); });
    $router->get('/api/customers/:id', function($id) use ($c) { $c['CustomerController']->get($id); });
    $router->post('/api/customers', function() use ($c) { $c['CustomerController']->create(); });
    $router->put('/api/customers/:id', function($id) use ($c) { $c['CustomerController']->update($id); });
    $router->delete('/api/customers/:id', function($id) use ($c) { $c['CustomerController']->delete($id); });

    // === NHÀ CUNG CẤP (TK 331) ===
    // Supplier API — danh mục nhà cung cấp, phục vụ quản lý công nợ phải trả
    // Liên quan đến ApService: mua hàng, trả tiền, chiết khấu, trả lại
    $router->get('/api/suppliers', function() use ($c) { $c['SupplierController']->list(); });
    $router->get('/api/suppliers/:id', function($id) use ($c) { $c['SupplierController']->get($id); });
    $router->post('/api/suppliers', function() use ($c) { $c['SupplierController']->create(); });
    $router->put('/api/suppliers/:id', function($id) use ($c) { $c['SupplierController']->update($id); });
    $router->delete('/api/suppliers/:id', function($id) use ($c) { $c['SupplierController']->delete($id); });

    // === DANH MỤC KHÁC: KHO, PHÒNG BAN, NHÂN VIÊN ===
    // Frontend pages
    $router->get('/danh-muc/kho', function() { require __DIR__ . '/../public/views/warehouses.php'; });
    $router->get('/danh-muc/phong-ban', function() { require __DIR__ . '/../public/views/departments.php'; });
    $router->get('/danh-muc/nhan-vien', function() { require __DIR__ . '/../public/views/employees.php'; });

    // === KHO ===
    // Warehouse API — danh mục kho, phục vụ quản lý tồn kho đa kho
    $router->get('/api/warehouses', function() use ($c) { $c['WarehouseController']->list(); });
    $router->get('/api/warehouses/:id', function($id) use ($c) { $c['WarehouseController']->get($id); });
    $router->post('/api/warehouses', function() use ($c) { $c['WarehouseController']->create(); });
    $router->put('/api/warehouses/:id', function($id) use ($c) { $c['WarehouseController']->update($id); });
    $router->delete('/api/warehouses/:id', function($id) use ($c) { $c['WarehouseController']->delete($id); });

    // === PHÒNG BAN ===
    // Department API — danh mục phòng ban, phục vụ phân bổ chi phí, kế toán tập hợp chi phí
    $router->get('/api/departments', function() use ($c) { $c['DepartmentController']->list(); });
    $router->get('/api/departments/:id', function($id) use ($c) { $c['DepartmentController']->get($id); });
    $router->post('/api/departments', function() use ($c) { $c['DepartmentController']->create(); });
    $router->put('/api/departments/:id', function($id) use ($c) { $c['DepartmentController']->update($id); });
    $router->delete('/api/departments/:id', function($id) use ($c) { $c['DepartmentController']->delete($id); });

    // === NHÂN VIÊN (TK 334) ===
    // Employee API — danh mục nhân viên, phục vụ tính lương, tạm ứng, công nợ
    // Liên quan đến TK 334 (Phải trả người lao động)
    $router->get('/api/employees', function() use ($c) { $c['EmployeeController']->list(); });
    $router->get('/api/employees/:id', function($id) use ($c) { $c['EmployeeController']->get($id); });
    $router->post('/api/employees', function() use ($c) { $c['EmployeeController']->create(); });
    $router->put('/api/employees/:id', function($id) use ($c) { $c['EmployeeController']->update($id); });
    $router->delete('/api/employees/:id', function($id) use ($c) { $c['EmployeeController']->delete($id); });

    // === ĐƠN VỊ TÍNH ===
    // UOM
    $router->get('/danh-muc/don-vi-tinh', function() { require __DIR__ . '/../public/views/uoms.php'; });
    $router->get('/api/uoms', function() use ($c) { $c['UomController']->list(); });
    $router->get('/api/uoms/:id', function($id) use ($c) { $c['UomController']->get($id); });
    $router->post('/api/uoms', function() use ($c) { $c['UomController']->create(); });
    $router->put('/api/uoms/:id', function($id) use ($c) { $c['UomController']->update($id); });
    $router->delete('/api/uoms/:id', function($id) use ($c) { $c['UomController']->delete($id); });

    // === CCDC (CÔNG CỤ DỤNG CỤ - TK 153) ===
    // CCDC — Công cụ dụng cụ, phân biệt với TSCĐ (thời gian sử dụng < 1 năm)
    $router->get('/danh-muc/cong-cu-dung-cu', function() { require __DIR__ . '/../public/views/ccdc.php'; });
    $router->get('/api/ccdc', function() use ($c) { $c['CcdcController']->list(); });
    $router->get('/api/ccdc/:id', function($id) use ($c) { $c['CcdcController']->get($id); });
    $router->post('/api/ccdc', function() use ($c) { $c['CcdcController']->create(); });
    $router->put('/api/ccdc/:id', function($id) use ($c) { $c['CcdcController']->update($id); });
    $router->delete('/api/ccdc/:id', function($id) use ($c) { $c['CcdcController']->delete($id); });

    // === TỶ GIÁ NGOẠI TỆ ===
    // Exchange Rates — quản lý tỷ giá ngoại tệ, phục vụ hạch toán nghiệp vụ ngoại tệ
    // Áp dụng Thông tư 200: ghi nhận chênh lệch tỷ giá cuối kỳ (TK 413, 515, 635)
    $router->get('/danh-muc/ty-gia', function() { require __DIR__ . '/../public/views/exchange_rates.php'; });
    $router->get('/api/exchange-rates', function() use ($c) { $c['ExchangeRateController']->list(); });
    $router->get('/api/exchange-rates/:id', function($id) use ($c) { $c['ExchangeRateController']->get($id); });
    $router->post('/api/exchange-rates', function() use ($c) { $c['ExchangeRateController']->create(); });
    $router->put('/api/exchange-rates/:id', function($id) use ($c) { $c['ExchangeRateController']->update($id); });
    $router->delete('/api/exchange-rates/:id', function($id) use ($c) { $c['ExchangeRateController']->delete($id); });

    // === THUẾ SUẤT (TK 133, 3331) ===
    // Tax Rates — quản lý thuế suất GTGT, TNCN, TNDN
    // TK 133 - Thuế GTGT đầu vào được khấu trừ
    // TK 3331 - Thuế GTGT đầu ra phải nộp
    $router->get('/danh-muc/bieu-thue', function() { require __DIR__ . '/../public/views/tax_rates.php'; });
    $router->get('/api/tax-rates', function() use ($c) { $c['TaxRateController']->list(); });
    $router->get('/api/tax-rates/:id', function($id) use ($c) { $c['TaxRateController']->get($id); });
    $router->post('/api/tax-rates', function() use ($c) { $c['TaxRateController']->create(); });
    $router->put('/api/tax-rates/:id', function($id) use ($c) { $c['TaxRateController']->update($id); });
    $router->delete('/api/tax-rates/:id', function($id) use ($c) { $c['TaxRateController']->delete($id); });

    // === TÀI SẢN CỐ ĐỊNH (TK 211, 214) ===
    // Fixed Assets — quản lý TSCĐ hữu hình/vô hình, tính khấu hao
    // TK 211: Nguyên giá TSCĐ, TK 214: Hao mòn lũy kế
    // Phương pháp khấu hao: đường thẳng, số dư giảm dần, sản lượng
    $router->get('/danh-muc/tai-san-co-dinh', function() { require __DIR__ . '/../public/views/fixed_assets.php'; });
    $router->get('/danh-muc/tai-san-co-dinh/tinh-khau-hao', function() { require __DIR__ . '/../public/views/fixed_asset_depreciation.php'; });
    $router->get('/api/fixed-assets', function() use ($c) { $c['FixedAssetController']->list(); });
    $router->get('/api/fixed-assets/:id', function($id) use ($c) { $c['FixedAssetController']->get($id); });
    $router->post('/api/fixed-assets', function() use ($c) { $c['FixedAssetController']->create(); });
    $router->put('/api/fixed-assets/:id', function($id) use ($c) { $c['FixedAssetController']->update($id); });
    $router->delete('/api/fixed-assets/:id', function($id) use ($c) { $c['FixedAssetController']->delete($id); });

    // Fixed Asset Depreciation — tính và ghi nhận khấu hao TSCĐ theo tháng
    // Gọi FixedAssetService::postMonthlyDepreciation → JournalService → ledger_entries
    // Bút toán: Nợ 627/641/642 / Có 214 (tùy bộ phận sử dụng)
    $router->post('/api/fixed-assets/depreciate', function() use ($c) {
        $input = json_decode(file_get_contents('php://input'), true);
        $period = $input['period'] ?? date('Y-m');
        $results = $c['fixedAssetService']->postMonthlyDepreciation($period, $_SESSION['user_id'] ?? 'system');
        JsonResponse::ok(['posted' => count($results), 'entries' => $results]);
    });
    $router->get('/api/fixed-assets/:id/depreciation', function($id) use ($c) {
        JsonResponse::ok($c['fixedAssetService']->getDepreciationHistory($id));
    });
    $router->get('/api/fixed-assets/depreciation/period/:period', function($period) use ($c) {
        JsonResponse::ok($c['fixedAssetService']->getDepreciationByPeriod($period));
    });
    $router->get('/api/fixed-assets/:id/schedule', function($id) use ($c) {
        $asset = $c['fixedAssetRepository']->findById($id);
        if (!$asset) { JsonResponse::error('Asset not found', 404); return; }
        JsonResponse::ok($c['fixedAssetService']->calculateSchedule($asset));
    });

    // === PHƯƠNG PHÁP TÍNH GIÁ ===
    // Valuation Methods — phương pháp tính giá xuất kho
    // FIFO, Bình quân gia quyền (cuối kỳ/tức thời), Thực tế đích danh
    // Áp dụng theo Thông tư 200/2014/TT-BTC
    $router->get('/danh-muc/phuong-phap-tinh-gia', function() { require __DIR__ . '/../public/views/valuation_methods.php'; });
    $router->get('/api/valuation-methods', function() use ($c) { $c['ValuationMethodController']->list(); });
    $router->get('/api/valuation-methods/:id', function($id) use ($c) { $c['ValuationMethodController']->get($id); });
    $router->post('/api/valuation-methods', function() use ($c) { $c['ValuationMethodController']->create(); });
    $router->put('/api/valuation-methods/:id', function($id) use ($c) { $c['ValuationMethodController']->update($id); });
    $router->delete('/api/valuation-methods/:id', function($id) use ($c) { $c['ValuationMethodController']->delete($id); });

    // === HỢP ĐỒNG ===
    // Contracts — quản lý hợp đồng mua/bán, liên kết với công nợ và giao dịch
    $router->get('/danh-muc/hop-dong', function() { require __DIR__ . '/../public/views/contracts.php'; });
    $router->get('/api/contracts', function() use ($c) { $c['ContractController']->list(); });
    $router->get('/api/contracts/:id', function($id) use ($c) { $c['ContractController']->get($id); });
    $router->post('/api/contracts', function() use ($c) { $c['ContractController']->create(); });
    $router->put('/api/contracts/:id', function($id) use ($c) { $c['ContractController']->update($id); });
    $router->delete('/api/contracts/:id', function($id) use ($c) { $c['ContractController']->delete($id); });

    // === DỰ ÁN ===
    // Projects — quản lý dự án, hạch toán chi phí theo dự án (kế toán quản trị)
    $router->get('/danh-muc/du-an', function() { require __DIR__ . '/../public/views/projects.php'; });
    $router->get('/api/projects', function() use ($c) { $c['ProjectController']->list(); });
    $router->get('/api/projects/:id', function($id) use ($c) { $c['ProjectController']->get($id); });
    $router->post('/api/projects', function() use ($c) { $c['ProjectController']->create(); });
    $router->put('/api/projects/:id', function($id) use ($c) { $c['ProjectController']->update($id); });
    $router->delete('/api/projects/:id', function($id) use ($c) { $c['ProjectController']->delete($id); });

    // === CHÍNH SÁCH KHẤU HAO TSCĐ ===
    // Depreciation Policies — chính sách khấu hao theo Thông tư 45/2013/TT-BTC
    // Quy định thời gian sử dụng, phương pháp khấu hao cho từng loại TSCĐ
    $router->get('/danh-muc/chinh-sach-khau-hao', function() { require __DIR__ . '/../public/views/depreciation_policies.php'; });
    $router->get('/api/depreciation-policies', function() use ($c) { $c['DepreciationPolicyController']->list(); });
    $router->get('/api/depreciation-policies/:id', function($id) use ($c) { $c['DepreciationPolicyController']->get($id); });
    $router->post('/api/depreciation-policies', function() use ($c) { $c['DepreciationPolicyController']->create(); });
    $router->put('/api/depreciation-policies/:id', function($id) use ($c) { $c['DepreciationPolicyController']->update($id); });
    $router->delete('/api/depreciation-policies/:id', function($id) use ($c) { $c['DepreciationPolicyController']->delete($id); });

    // === HỆ THỐNG TÀI KHOẢN (Circular 99) ===
    // COA — Chart of Accounts theo Thông tư 99/2025/TT-BTC
    // Seed: tạo mới 75+ tài khoản chuẩn từ data/coa_circular_99.json
    // Mỗi tài khoản có: code, name, type (asset/liability/equity/revenue/expense), is_control
    $router->get('/danh-muc/he-thong-tai-khoan', function() { require __DIR__ . '/../public/views/accounts.php'; });
    $router->get('/api/coa', function() use ($c) { $c['AccountController']->list(); });
    $router->get('/api/coa/:id', function($id) use ($c) { $c['AccountController']->get($id); });
    $router->post('/api/coa', function() use ($c) { $c['AccountController']->create(); });
    $router->put('/api/coa/:id', function($id) use ($c) { $c['AccountController']->update($id); });
    $router->delete('/api/coa/:id', function($id) use ($c) { $c['AccountController']->delete($id); });
    $router->post('/api/coa/seed', function() use ($c) { $c['AccountController']->seed(); });

    // === BÚT TOÁN KẾ TOÁN & SỔ KẾ TOÁN ===
    // Journal entries — lõi của hệ thống: mọi giao dịch đều qua JournalService
    // postEntry: ghi nhận bút toán chính thức (Dr = Cr, validate posting rules)
    // createDraft: tạo bút toán nháp — chờ phê duyệt
    // approveDraft: phê duyệt bút toán nháp → chính thức
    // trialBalance: bảng cân đối số phát sinh (BC 01)
    $router->get('/tong-hop/chung-tu-ghi-so', function() { require __DIR__ . '/../public/views/journal.php'; });
    $router->get('/tong-hop/phe-duyet', function() { require __DIR__ . '/../public/views/approvals.php'; });
    $router->get('/tong-hop/bang-can-doi-so-phat-sinh', function() { require __DIR__ . '/../public/views/trial_balance.php'; });
    $router->get('/tong-hop/khoa-so-cuoi-ky', function() { require __DIR__ . '/../public/views/period_close.php'; });
    $router->post('/api/journal', function() use ($c) { $c['JournalController']->postEntry(); });
    $router->post('/api/journal/draft', function() use ($c) { $c['JournalController']->createDraft(); });
    $router->post('/api/journal/approve/:id', function($id) use ($c) { $c['JournalController']->approveDraft($id); });
    $router->get('/api/transactions', function() use ($c) { $c['JournalController']->list(); });
    $router->get('/api/transactions/:id', function($id) use ($c) { $c['JournalController']->get($id); });
    $router->get('/api/trial-balance', function() use ($c) { $c['JournalController']->trialBalance(); });

    // === PHÊ DUYỆT CHỨNG TỪ ===
    // Approval workflow — quy trình phê duyệt bút toán, hóa đơn
    // Routing: xác định ai phê duyệt dựa trên số tiền, module, phòng ban
    // Approve/Reject: cập nhật trạng thái, ghi audit trail
    $router->get('/api/approvals/pending', function() use ($c) { $c['ApprovalController']->getPending(); });
    $router->post('/api/approvals/:id/approve', function($id) use ($c) { $c['ApprovalController']->approve($id); });
    $router->post('/api/approvals/:id/reject', function($id) use ($c) { $c['ApprovalController']->reject($id); });
    $router->get('/api/approvals/history/:id', function($id) use ($c) { $c['ApprovalController']->history($id); });
    $router->get('/api/approvals/routing', function() use ($c) { $c['ApprovalController']->routing(); });

    // Payer search (customers + suppliers + employees) — tìm kiếm đối tượng thanh toán
    // Gộp 3 loại đối tượng: khách hàng (TK 131), nhà cung cấp (TK 331), nhân viên (TK 334)
    // Dùng trong: lập phiếu thu/chi, chọn đối tượng thanh toán trên form
    $router->get('/api/payers/search', function() {
        $q = $_GET['q'] ?? '';
        $pdo = $GLOBALS['container']['pdo'];
        $results = [];
        if (strlen($q) >= 1) {
            $like = '%' . $q . '%';
            $stmt = $pdo->prepare("SELECT id, code, name, 'customer' as type FROM customers WHERE name LIKE ? OR code LIKE ? LIMIT 10");
            $stmt->execute([$like, $like]); $results = array_merge($results, $stmt->fetchAll(\PDO::FETCH_ASSOC));
            $stmt = $pdo->prepare("SELECT id, code, name, 'supplier' as type FROM suppliers WHERE name LIKE ? OR code LIKE ? LIMIT 10");
            $stmt->execute([$like, $like]); $results = array_merge($results, $stmt->fetchAll(\PDO::FETCH_ASSOC));
            $stmt = $pdo->prepare("SELECT id, code, name, 'employee' as type FROM employees WHERE name LIKE ? OR code LIKE ? LIMIT 10");
            $stmt->execute([$like, $like]); $results = array_merge($results, $stmt->fetchAll(\PDO::FETCH_ASSOC));
        }
        JsonResponse::ok($results);
    });

    // === TIỀN MẶT (TK 111) & TIỀN GỬI NGÂN HÀNG (TK 112) ===
    // Cash & Bank API — quản lý thu/chi tiền mặt (TK 1111, 1112, 1113)
    // và tiền gửi ngân hàng (TK 1121, 1122, 1123)
    // receipt: phiếu thu — Nợ 1111 / Có TK đối ứng
    // payment: phiếu chi — Nợ TK đối ứng / Có 1111
    // deposit: nộp tiền vào ngân hàng — Nợ 112 / Có 111
    // withdrawal: rút tiền ngân hàng — Nợ 111 / Có 112
    // bank receipt: báo Có — Nợ 112 / Có TK đối ứng
    // bank payment: báo Nợ — Nợ TK đối ứng / Có 112
    // interest: lãi ngân hàng — Nợ 112 / Có 515
    // charge: phí ngân hàng — Nợ 642 / Có 112
    // transit: tiền đang chuyển (TK 113)
    $router->get('/api/cash/receipts', function() use ($c) { $c['CashController']->receipts(); });
    $router->post('/api/cash/receipts', function() use ($c) { $c['CashController']->createReceipt(); });
    $router->get('/api/cash/payments', function() use ($c) { $c['CashController']->payments(); });
    $router->post('/api/cash/payments', function() use ($c) { $c['CashController']->createPayment(); });
    $router->get('/api/cash/templates', function() use ($c) { $c['CashController']->transactionTemplates(); });
    $router->get('/api/cash/accounts', function() use ($c) { $c['CashController']->accounts(); });
    $router->get('/api/bank-transactions', function() use ($c) { $c['CashController']->bankTransactions(); });
    $router->post('/api/bank/deposit', function() use ($c) { $c['CashController']->createDeposit(); });
    $router->post('/api/bank/withdrawal', function() use ($c) { $c['CashController']->createWithdrawal(); });
    $router->post('/api/bank/receipt', function() use ($c) { $c['CashController']->createBankReceipt(); });
    $router->post('/api/bank/payment', function() use ($c) { $c['CashController']->createBankPayment(); });
    $router->post('/api/bank/interest', function() use ($c) { $c['CashController']->createInterest(); });
    $router->post('/api/bank/charge', function() use ($c) { $c['CashController']->createCharge(); });
    $router->get('/api/cash/transit', function() use ($c) { $c['CashController']->transitRecords(); });
    $router->post('/api/cash/transit', function() use ($c) { $c['CashController']->createTransit(); });
    $router->post('/api/cash/transit/confirm', function() use ($c) { $c['CashController']->confirmTransit(); });
    $router->post('/api/cash/transit/reverse', function() use ($c) { $c['CashController']->reverseTransit(); });
    $router->get('/api/cash-book', function() use ($c) { $c['CashController']->cashBook(); });
    $router->get('/api/petty-cash/funds', function() use ($c) { $c['PettyCashController']->funds(); });
    $router->post('/api/petty-cash/funds', function() use ($c) { $c['PettyCashController']->createFund(); });
    $router->post('/api/petty-cash/disburse', function() use ($c) { $c['PettyCashController']->disburse(); });
    $router->post('/api/petty-cash/replenish', function() use ($c) { $c['PettyCashController']->replenish(); });
    $router->post('/api/petty-cash/close', function() use ($c) { $c['PettyCashController']->closeFund(); });
    $router->get('/api/petty-cash/:id/transactions', function($id) use ($c) { $c['PettyCashController']->transactions($id); });

    // === GIAO DIỆN THU/CHI ===
    // Cash & Bank views — giao diện người dùng cho module tiền mặt/ngân hàng
    // Các mẫu chứng từ: S03-DN (Sổ quỹ), S07-DN (Sổ tiền gửi)
    $router->get('/thu/quy-tien-mat', function() { require __DIR__ . '/../public/views/cash_receipts.php'; });
    $router->get('/chi/quy-tien-mat', function() { require __DIR__ . '/../public/views/cash_payments.php'; });
    $router->get('/thu/giao-bao-co', function() { require __DIR__ . '/../public/views/bank_credit.php'; });
    $router->get('/chi/giao-bao-no', function() { require __DIR__ . '/../public/views/bank_debit.php'; });
    $router->get('/thu/tien-dang-chuyen', function() { require __DIR__ . '/../public/views/cash_transit.php'; });
    $router->get('/thu/so-quy-tien-mat', function() { require __DIR__ . '/../public/views/cash_book.php'; });
    $router->get('/thu/tam-ung', function() { require __DIR__ . '/../public/views/petty_cash.php'; });
    $router->get('/thu/doi-chieu-ngan-hang', function() { require __DIR__ . '/../public/views/bank_reconciliation.php'; });
    $router->get('/thu/bao-cao-von-bang-tien', function() { require __DIR__ . '/../public/views/cash_reports.php'; });
    $router->get('/thu/danh-gia-lai-ngoai-te', function() { require __DIR__ . '/../public/views/fx_revaluation.php'; });
    $router->get('/danh-muc/tai-khoan-ngan-hang', function() { require __DIR__ . '/../public/views/bank_accounts.php'; });

    // === TÀI KHOẢN NGÂN HÀNG ===
    // Bank Accounts API — danh mục tài khoản ngân hàng của doanh nghiệp
    // Mỗi tài khoản ngân hàng tương ứng một TK 112xx
    $router->get('/api/bank-accounts', function() use ($c) { $c['BankAccountController']->list(); });
    $router->get('/api/bank-accounts/:id', function($id) use ($c) { $c['BankAccountController']->get($id); });
    $router->post('/api/bank-accounts', function() use ($c) { $c['BankAccountController']->create(); });
    $router->put('/api/bank-accounts/:id', function($id) use ($c) { $c['BankAccountController']->update($id); });
    $router->delete('/api/bank-accounts/:id', function($id) use ($c) { $c['BankAccountController']->delete($id); });

    // === ĐÁNH GIÁ LẠI NGOẠI TỆ ===
    // FX — đánh giá lại số dư ngoại tệ cuối kỳ theo Thông tư 200
    // Chênh lệch tỷ giá ghi nhận vào TK 413 (chênh lệch tỷ giá hối đoái)
    // Lãi: ghi Có 515, Lỗ: ghi Nợ 635 khi kết chuyển cuối năm
    $router->get('/api/fx/balances', function() use ($c) { $c['CashController']->fcBalances(); });
    $router->post('/api/fx/revalue', function() use ($c) { $c['CashController']->fcRevalue(); });

    // Cash Reports API — báo cáo vốn bằng tiền (BC 01 chỉ tiêu tiền và tương đương tiền)
    // position: tình hình vốn bằng tiền, bankLedger: sổ phụ ngân hàng
    // dailyFlow: dòng tiền theo ngày, concentration: tập trung tiền
    // trend: xu hướng dòng tiền
    $router->get('/api/cash-reports/position', function() use ($c) { $c['CashReportController']->position(); });
    $router->get('/api/cash-reports/bank-ledger', function() use ($c) { $c['CashReportController']->bankLedger(); });
    $router->get('/api/cash-reports/daily-flow', function() use ($c) { $c['CashReportController']->dailyFlow(); });
    $router->get('/api/cash-reports/concentration', function() use ($c) { $c['CashReportController']->concentration(); });
    $router->get('/api/cash-reports/trend', function() use ($c) { $c['CashReportController']->trend(); });

    // Bank Reconciliation API — đối chiếu ngân hàng (Bank Reconciliation)
    // So khớp giữa sổ phụ ngân hàng và sổ kế toán (TK 112)
    // Phát hiện chênh lệch: séc chưa thu, chưa ghi sổ, lỗi ngân hàng
    // Tạo bút toán điều chỉnh nếu cần — qua JournalService
    $router->get('/api/bank-reconciliation/sessions', function() use ($c) { $c['BankReconciliationController']->sessions(); });
    $router->post('/api/bank-reconciliation/start', function() use ($c) { $c['BankReconciliationController']->startSession(); });
    $router->get('/api/bank-reconciliation/:id/session', function($id) use ($c) { $c['BankReconciliationController']->getSession($id); });
    $router->get('/api/bank-reconciliation/:id/items', function($id) use ($c) { $c['BankReconciliationController']->items($id); });
    $router->get('/api/bank-reconciliation/:id/unmatched', function($id) use ($c) { $c['BankReconciliationController']->unmatched($id); });
    $router->post('/api/bank-reconciliation/:id/statement-entry', function($id) use ($c) { $c['BankReconciliationController']->addStatementEntry($id); });
    $router->post('/api/bank-reconciliation/:id/auto-match', function($id) use ($c) { $c['BankReconciliationController']->autoMatch($id); });
    $router->post('/api/bank-reconciliation/:id/manual-match', function($id) use ($c) { $c['BankReconciliationController']->manualMatch($id); });
    $router->post('/api/bank-reconciliation/:id/adjust', function($id) use ($c) { $c['BankReconciliationController']->addAdjustingEntry($id); });
    $router->post('/api/bank-reconciliation/:id/complete', function($id) use ($c) { $c['BankReconciliationController']->complete($id); });
    $router->get('/api/bank-reconciliation/bank-accounts', function() use ($c) { $c['BankReconciliationController']->bankAccounts(); });

    // === NHẬP KHO ===
    // Receipt — nhập kho (PNK), ghi nhận hàng mua vào
    // Bút toán: Nợ 152/153/155/156 / Có 331 (hoặc 111/112)
    // Nếu có VAT: thêm Nợ 1331
    $router->get('/kho/nhap-kho', function() { require __DIR__ . '/../public/views/receipt.php'; });
    $router->post('/api/inventory/receive', function() use ($c) { $c['ReceiptController']->receive(); });
    $router->get('/api/inventory/receipts', function() use ($c) { $c['ReceiptController']->list(); });
    $router->get('/api/inventory/receive/items', function() use ($c) { $c['ReceiptController']->items(); });

    // === XUẤT KHO ===
    // Issue — xuất kho (PXK), ghi nhận hàng bán ra hoặc sử dụng trong SX
    // Bút toán giá vốn: Nợ 632 / Có 152/153/155/156
    // Tính giá xuất theo phương pháp đã chọn (FIFO/Bình quân)
    $router->get('/kho/xuat-kho', function() { require __DIR__ . '/../public/views/issue.php'; });
    $router->post('/api/inventory/issue', function() use ($c) { $c['IssueController']->issue(); });
    $router->get('/api/inventory/issues', function() use ($c) { $c['IssueController']->list(); });
    $router->get('/api/inventory/issue/items', function() use ($c) { $c['IssueController']->items(); });

    // === HÀNG BÁN TRẢ LẠI ===
    // Customer return — khách hàng trả lại hàng (TK 131 ghi giảm công nợ)
    // Bút toán: Nợ 156 / Có 632 (ghi giảm giá vốn) + Nợ 511/Có 131 (ghi giảm doanh thu)
    $router->get('/kho/hang-ban-tra-lai', function() { require __DIR__ . '/../public/views/customer_return.php'; });
    $router->post('/api/inventory/customer-return', function() use ($c) { $c['CustomerReturnController']->return(); });
    $router->get('/api/inventory/customer-returns', function() use ($c) { $c['CustomerReturnController']->list(); });
    $router->get('/api/inventory/customer-return/items', function() use ($c) { $c['CustomerReturnController']->items(); });

    // === ĐIỀU CHUYỂN KHO ===
    // Transfer — điều chuyển hàng giữa các kho (không thay đổi sở hữu)
    // Bút toán điều chỉnh nội bộ: không ảnh hưởng BC KQKD
    $router->get('/kho/dieu-chuyen', function() { require __DIR__ . '/../public/views/transfers.php'; });

    // === HÀNG GỬI BÁN (TK 157) ===
    // Consignment — hàng gửi bán đại lý, chưa ghi nhận doanh thu
    // Chỉ khi bán được mới ghi nhận doanh thu (Nợ 632/Có 157)
    $router->get('/kho/hang-gui-ban', function() { require __DIR__ . '/../public/views/consignment.php'; });
    $router->get('/api/consignments', function() use ($c) { $c['ConsignmentController']->list(); });
    $router->post('/api/consignments', function() use ($c) { $c['ConsignmentController']->consign(); });
    $router->post('/api/consignments/sell', function() use ($c) { $c['ConsignmentController']->sell(); });
    $router->post('/api/consignments/return', function() use ($c) { $c['ConsignmentController']->returnConsignment(); });

    // User & Role management
    $router->get('/he-thong/nguoi-dung', function() { require __DIR__ . '/../public/views/users.php'; });
    $router->get('/he-thong/vai-tro', function() { require __DIR__ . '/../public/views/roles.php'; });

    // === CÔNG NỢ PHẢI TRẢ (TK 331) ===
    // AP — Accounts Payable: quản lý nợ nhà cung cấp
    // Invoice: hóa đơn mua hàng — tăng công nợ
    // Pay: thanh toán — giảm công nợ + ghi nhận tiền chi
    // Return: trả lại hàng mua — giảm công nợ + giảm hàng tồn kho
    // Discount: chiết khấu thanh toán — giảm công nợ + ghi nhận doanh thu tài chính
    // Write-off: xóa nợ không có khả năng thanh toán
    // Aging: phân tích tuổi nợ, statement: sổ chi tiết công nợ
    $router->get('/mua/cong-no-phai-tra', function() { require __DIR__ . '/../public/views/ap_invoices.php'; });
    $router->get('/mua/phan-tich-tuoi-no', function() { require __DIR__ . '/../public/views/ap_aging.php'; });
    $router->get('/mua/so-chi-tiet-cong-no', function() { require __DIR__ . '/../public/views/ap_statement.php'; });
    $router->get('/api/ap/invoices', function() use ($c) { $c['ApController']->invoices(); });
    $router->post('/api/ap/invoices', function() use ($c) { $c['ApController']->create(); });
    $router->get('/api/ap/invoices/:id', function($id) use ($c) { $c['ApController']->get($id); });
    $router->get('/api/ap/invoices/:id/payments', function($id) use ($c) { $c['ApController']->payments($id); });
    $router->post('/api/ap/invoices/:id/pay', function($id) use ($c) { $c['ApController']->pay($id); });
    $router->post('/api/ap/invoices/:id/return', function($id) use ($c) { $c['ApController']->returnGoods($id); });
    $router->post('/api/ap/invoices/:id/discount', function($id) use ($c) { $c['ApController']->discount($id); });
    $router->post('/api/ap/invoices/:id/write-off', function($id) use ($c) { $c['ApController']->writeOff($id); });
    $router->get('/api/ap/prepay', function() use ($c) { $c['ApController']->prepay(); });  // dummy GET to register the view route
    $router->post('/api/ap/prepay', function() use ($c) { $c['ApController']->prepay(); });
    $router->get('/api/ap/aging', function() use ($c) { $c['ApController']->aging(); });
    $router->get('/api/ap/suppliers', function() use ($c) { $c['ApController']->suppliers(); });
    $router->get('/api/ap/suppliers/:id/statement', function($id) use ($c) { $c['ApController']->statement($id); });

    // === CÔNG NỢ PHẢI THU (TK 131) ===
    // AR — Accounts Receivable: quản lý nợ phải thu khách hàng
    // Invoice: hóa đơn bán hàng — tăng công nợ
    // Pay: thu tiền — giảm công nợ + ghi nhận tiền thu
    // Return: khách trả lại hàng — giảm công nợ + giảm doanh thu
    // Discount: chiết khấu thanh toán — giảm công nợ + ghi nhận chi phí tài chính
    // Write-off: xóa nợ xấu, Aging: phân tích tuổi nợ, statement: sổ chi tiết
    $router->get('/ban/cong-no-phai-thu', function() { require __DIR__ . '/../public/views/ar_invoices.php'; });
    $router->get('/ban/phan-tich-tuoi-no', function() { require __DIR__ . '/../public/views/ar_aging.php'; });
    $router->get('/ban/so-chi-tiet-cong-no', function() { require __DIR__ . '/../public/views/ar_statement.php'; });
    $router->get('/api/ar/invoices', function() use ($c) { $c['ArController']->invoices(); });
    $router->post('/api/ar/invoices', function() use ($c) { $c['ArController']->create(); });
    $router->get('/api/ar/invoices/:id', function($id) use ($c) { $c['ArController']->get($id); });
    $router->post('/api/ar/invoices/:id/pay', function($id) use ($c) { $c['ArController']->pay($id); });
    $router->post('/api/ar/invoices/:id/return', function($id) use ($c) { $c['ArController']->returnGoods($id); });
    $router->post('/api/ar/invoices/:id/discount', function($id) use ($c) { $c['ArController']->discount($id); });
    $router->post('/api/ar/invoices/:id/write-off', function($id) use ($c) { $c['ArController']->writeOff($id); });
    $router->post('/api/ar/prepay', function() use ($c) { $c['ArController']->prepay(); });
    $router->get('/api/ar/aging', function() use ($c) { $c['ArController']->aging(); });
    $router->get('/api/ar/customers', function() use ($c) { $c['ArController']->customers(); });
    $router->get('/api/ar/customers/:id/statement', function($id) use ($c) { $c['ArController']->statement($id); });

    // === BÁO CÁO TÀI CHÍNH (BC01, BC02, BC03) ===
    // Financial Statements — báo cáo tài chính theo Thông tư 99
    // BC01: Bảng cân đối kế toán (Mẫu B01-DN)
    // BC02: Báo cáo kết quả hoạt động kinh doanh (Mẫu B02-DN)
    // BC03: Báo cáo lưu chuyển tiền tệ (Mẫu B03-DN)
    // TT99: Thuyết minh báo cáo tài chính (Mẫu B09-DN)
    $router->get('/bao-cao/tinh-hinh-tai-chinh', function() use ($c) { $c['FsController']->viewBC01(); });
    $router->get('/bao-cao/ket-qua-kinh-doanh', function() use ($c) { $c['FsController']->viewBC02(); });
    $router->get('/api/fs/bc01', function() use ($c) { $c['FsController']->bc01(); });
    $router->get('/api/fs/bc02', function() use ($c) { $c['FsController']->bc02(); });
    $router->get('/api/fs/tt99', function() use ($c) { $c['FsController']->tt99(); });
    $router->get('/bao-cao/luu-chuyen-tien-te', function() use ($c) { $c['FsController']->viewBC03(); });
    $router->get('/api/fs/bc03', function() use ($c) { $c['FsController']->bc03(); });

    // === QUẢN LÝ KỲ KẾ TOÁN ===
    // Period Management — quản lý kỳ kế toán (tháng/quý/năm)
    // Close: đóng kỳ — không cho phép post bút toán mới vào kỳ đã đóng
    // Reopen: mở lại kỳ (chỉ Kế toán trưởng) — cần audit trail đặc biệt
    // Close with checklist: kiểm tra đầy đủ chứng từ trước khi đóng
    // Archive: lưu trữ dữ liệu kỳ đã đóng
    $router->get('/he-thong/quan-ly-ky', function() { require __DIR__ . '/../public/views/periods.php'; });
    $router->get('/api/periods', function() use ($c) { $c['PeriodController']->list(); });
    $router->get('/api/periods/:id', function($id) use ($c) { $c['PeriodController']->get($id); });
    $router->post('/api/periods', function() use ($c) { $c['PeriodController']->create(); });
    $router->post('/api/periods/:id/close', function($id) use ($c) { $c['PeriodController']->close($id); });
    $router->post('/api/periods/:id/reopen', function($id) use ($c) { $c['PeriodController']->reOpen($id); });
    $router->post('/api/periods/:id/close-with-checklist', function($id) use ($c) { $c['PeriodController']->closeWithChecklist($id); });
    $router->post('/api/periods/:id/deadline', function($id) use ($c) { $c['PeriodController']->setDeadline($id); });
    $router->post('/api/periods/:id/deadline/override', function($id) use ($c) { $c['PeriodController']->overrideDeadline($id); });
    $router->get('/api/periods/:id/can-close', function($id) use ($c) { $c['PeriodController']->canClose($id); });
    $router->post('/api/periods/:id/execute-closing', function($id) use ($c) { $c['PeriodController']->executeClosing($id); });
    $router->post('/api/periods/:id/archive', function($id) use ($c) { $c['PeriodController']->archive($id); });

    // === SỔ CÁI ===
    // GL (Sổ Cái) — sổ kế toán theo dõi chi tiết từng tài khoản
    // Mẫu S01-DN: Sổ Cái, S02-DN: Sổ Nhật ký - Sổ Cái
    // ledger: danh sách phát sinh theo tài khoản và kỳ
    $router->get('/bao-cao/so-cai', function() use ($c) { $c['GlController']->view(); });
    $router->get('/api/gl/ledger', function() use ($c) { $c['GlController']->ledger(); });
    $router->get('/api/gl/accounts', function() use ($c) { $c['GlController']->accounts(); });

    // === ĐỐI SOÁT ===
    // Reconciliation — kiểm tra đối chiếu dữ liệu kế toán
    // So sánh số liệu giữa các bộ phận, phát hiện chênh lệch
    $router->get('/api/reconciliation/run', function() use ($c) { $c['ReconciliationController']->run(); });
    $router->get('/bao-cao/doi-soat', function() { require __DIR__ . '/../public/views/reconciliation.php'; });

    // === ĐÁNH GIÁ LẠI NGOẠI TỆ ===
    // FX Revaluation — đánh giá lại số dư ngoại tệ cuối kỳ
    // Chênh lệch ghi nhận vào TK 413, kết chuyển TK 515/635 cuối năm
    // Áp dụng theo Thông tư 200/2014/TT-BTC và Thông tư 53/2016/TT-BTC
    $router->get('/bao-cao/ty-gia', function() use ($c) { $c['FxController']->view(); });
    $router->post('/api/fx/revaluate/:periodId', function($periodId) use ($c) { $c['FxController']->revaluate($periodId); });
    $router->get('/api/fx/report/:periodId', function($periodId) use ($c) { $c['FxController']->report($periodId); });

    // === NỘI BỘ (HỢP NHẤT) ===
    // Intercompany — giao dịch nội bộ, hợp nhất báo cáo tài chính
    // Match: đối chiếu công nợ nội bộ, Eliminate: loại trừ giao dịch nội bộ
    // Consolidated: báo cáo tài chính hợp nhất
    $router->get('/he-thong/noi-bo', function() use ($c) { $c['IntercompanyController']->view(); });
    $router->get('/api/ic/entities', function() use ($c) { $c['IntercompanyController']->entities(); });
    $router->get('/api/ic/match/:entityId', function($entityId) use ($c) { $c['IntercompanyController']->match($entityId); });
    $router->post('/api/ic/eliminate/:entityId', function($entityId) use ($c) { $c['IntercompanyController']->eliminate($entityId); });
    $router->get('/api/ic/consolidated', function() use ($c) { $c['IntercompanyController']->consolidated(); });

    // === SỔ NHẬT KÝ CHUNG (Mẫu S03a-DN) ===
    // Sổ Nhật ký chung (S03a-DN) — ghi chép mọi bút toán theo thời gian
    // Mẫu bắt buộc theo Thông tư 200: doanh nghiệp phải mở sổ Nhật ký chung
    $router->get('/bao-cao/nhat-ky-chung', function() use ($c) { $c['JournalBookController']->view(); });
    $router->get('/api/general-journal', function() use ($c) { $c['JournalBookController']->journal(); });

    // === NHẬT KÝ KIỂM TOÁN ===
    // Audit Log — xem nhật ký kiểm toán (audit_log table)
    // Phục vụ kiểm toán viên: tra cứu ai đã thay đổi dữ liệu gì, lúc nào
    $router->get('/he-thong/nhat-ky-hoat-dong', function() { require __DIR__ . '/../public/views/audit_log.php'; });
    $router->get('/api/audit-log', function() use ($c) { $c['AuditLogController']->list(); });
    $router->get('/api/audit-log/:id', function($id) use ($c) { $c['AuditLogController']->get($id); });

    // === DASHBOARD & CHỈ SỐ KPI ===
    // Dashboard API — chỉ số KPI tài chính tổng quan: số dư tiền, công nợ, tồn kho
    $router->get('/api/dashboard', function() use ($c) { $c['CashReportController']->kpis(); });

    // === KIỂM KÊ KHO ===
    // Physical Count — kiểm kê thực tế, so sánh với tồn kho sổ sách
    // Adjust: điều chỉnh chênh lệch — ghi nhận thừa/thiếu qua JournalService
    // Bút toán: Nợ 152/153/155/156 / Có 632 (thừa) hoặc ngược lại (thiếu)
    $router->get('/kho/kiem-ke', function() { require __DIR__ . '/../public/views/physical_count.php'; });
    $router->get('/api/physical-count/sessions', function() use ($c) { $c['PhysicalCountController']->sessions(); });
    $router->get('/api/physical-count/lines/:id', function($id) use ($c) { $c['PhysicalCountController']->lines($id); });
    $router->post('/api/physical-count/sessions', function() use ($c) { $c['PhysicalCountController']->createSession(); });
    $router->post('/api/physical-count/adjust', function() use ($c) { $c['PhysicalCountController']->adjust(); });

    // === DỰ PHÒNG GIẢM GIÁ HÀNG TỒN KHO ===
    // Impairment — dự phòng giảm giá hàng tồn kho (TK 2294)
    // Áp dụng khi giá trị thuần có thể thực hiện được < giá gốc
    // Bút toán: Nợ 632 / Có 2294 — hoàn nhập: ngược lại
    $router->get('/kho/du-phong-giam-gia', function() { require __DIR__ . '/../public/views/impairment.php'; });
    $router->get('/api/impairments', function() use ($c) { $c['ImpairmentController']->list(); });
    $router->post('/api/impairments', function() use ($c) { $c['ImpairmentController']->record(); });
    $router->post('/api/impairments/reverse', function() use ($c) { $c['ImpairmentController']->reverse(); });

    // === HÀNG KHUYẾN MÃI ===
    // Promotional — xuất hàng khuyến mãi, quảng cáo
    // Bút toán: Nợ 641 (chi phí bán hàng) / Có 156
    $router->post('/api/promotional/issue', function() use ($c) { $c['PromotionalController']->issue(); });

    // === HÀNG MUA TRẢ LẠI ===
    // Supplier Return — trả lại hàng cho nhà cung cấp
    // Bút toán: Nợ 331 / Có 152/153/155/156 (+ Nợ 331/Có 1331 nếu có VAT)
    $router->get('/kho/hang-mua-tra-lai', function() { require __DIR__ . '/../public/views/supplier_return.php'; });
    $router->post('/api/inventory/supplier-return', function() use ($c) { $c['ReturnToSupplierController']->return(); });
    $router->get('/api/inventory/supplier-returns', function() use ($c) { $c['ReturnToSupplierController']->list(); });
    $router->get('/api/inventory/supplier-return/items', function() use ($c) { $c['ReturnToSupplierController']->items(); });

    // === XUẤT HỦY HÀNG TỒN KHO ===
    // Write-off — xuất hủy hàng tồn kho hư hỏng, mất phẩm chất
    // Cần biên bản hủy và phê duyệt của Kế toán trưởng
    // Bút toán: Nợ 632 / Có 152/153/155/156
    $router->get('/kho/xuat-huy', function() { require __DIR__ . '/../public/views/write_off.php'; });
    $router->post('/api/inventory/write-off', function() use ($c) { $c['WriteOffController']->writeOff(); });
    $router->get('/api/inventory/write-offs', function() use ($c) { $c['WriteOffController']->list(); });

    // === BÁO CÁO TỒN KHO ===
    // Inventory Reports — báo cáo tồn kho: aging (thời gian lưu kho)
    // turnover (vòng quay hàng tồn kho), valuation (giá trị tồn kho)
    $router->get('/api/inventory/aging', function() use ($c) { $c['InventoryReportController']->aging(); });
    $router->get('/api/inventory/turnover', function() use ($c) { $c['InventoryReportController']->turnover(); });
    $router->get('/api/inventory/valuation', function() use ($c) { $c['InventoryReportController']->valuation(); });

    // === KIỂM KÊ ĐỊNH KỲ ===
    // Periodic Inventory — kiểm kê định kỳ theo quy định (tối thiểu 1 lần/năm)
    // Điều chỉnh tồn kho thực tế với tồn kho sổ sách
    $router->get('/kho/kiem-ke-dinh-ky', function() { require __DIR__ . '/../public/views/periodic.php'; });
    $router->get('/api/periodic', function() use ($c) { $c['PeriodicController']->list(); });
    $router->post('/api/periodic/close', function() use ($c) { $c['PeriodicController']->close(); });

    // === HÀNG ĐANG ĐI ĐƯỜNG (TK 157) ===
    // In Transit — hàng mua đã thanh toán nhưng chưa về kho
    // Khi hàng về: Nợ 152/153... / Có 157
    $router->get('/kho/hang-dang-di-duong', function() { require __DIR__ . '/../public/views/transit.php'; });
    $router->get('/api/inventory-transit', function() use ($c) { $c['InventoryTransitController']->list(); });
    $router->post('/api/inventory-transit', function() use ($c) { $c['InventoryTransitController']->record(); });
    $router->post('/api/inventory-transit/receive', function() use ($c) { $c['InventoryTransitController']->receive(); });
    $router->get('/api/transfers', function() use ($c) { $c['TransferController']->list(); });
    $router->post('/api/transfers', function() use ($c) { $c['TransferController']->transfer(); });
    $router->get('/api/transfers/items', function() use ($c) { $c['TransferController']->items(); });
    $router->get('/api/transfers/warehouses', function() use ($c) { $c['TransferController']->warehouses(); });

    // === TIỀN LƯƠNG ===
    // Payroll API — bang luong, tinh luong, BHXH, thue TNCN
    // Ky luong
    $router->get('/api/payroll/periods', function() use ($c) { $c['PayrollController']->listPeriods(); });
    $router->post('/api/payroll/periods', function() use ($c) { $c['PayrollController']->createPeriod(); });
    $router->get('/api/payroll/periods/open', function() use ($c) { $c['PayrollController']->listOpenPeriods(); });
    $router->get('/api/payroll/periods/:id', function($id) use ($c) { $c['PayrollController']->getPeriod($id); });
    $router->post('/api/payroll/periods/:id/close', function($id) use ($c) { $c['PayrollController']->closePeriod($id); });
    // Bang luong
    $router->get('/api/payroll/entries', function() use ($c) { $c['PayrollController']->listEntries(); });
    $router->get('/api/payroll/entries/pending', function() use ($c) { $c['PayrollController']->listPendingEntries(); });
    $router->get('/api/payroll/entries/:id', function($id) use ($c) { $c['PayrollController']->getEntry($id); });
    $router->get('/api/payroll/entries/:id/details', function($id) use ($c) { $c['PayrollController']->getEntryDetails($id); });
    // Tinh luong
    $router->post('/api/payroll/process', function() use ($c) { $c['PayrollController']->processPayroll(); });
    $router->get('/api/payroll/calculate/insurance', function() use ($c) { $c['PayrollController']->calculateInsurance(); });
    $router->get('/api/payroll/calculate/tax', function() use ($c) { $c['PayrollController']->calculateTax(); });
    $router->get('/api/payroll/calculate/employee', function() use ($c) { $c['PayrollController']->calculateEmployeePay(); });
    // Duyet/Post/Dieu chinh
    $router->post('/api/payroll/entries/:id/approve', function($id) use ($c) { $c['PayrollController']->approveEntry($id); });
    $router->post('/api/payroll/entries/:id/post', function($id) use ($c) { $c['PayrollController']->postEntry($id); });
    $router->post('/api/payroll/entries/:id/adjust', function($id) use ($c) { $c['PayrollController']->adjustEntry($id); });
    // Nhan vien
    $router->get('/api/payroll/employees', function() use ($c) { $c['PayrollController']->listPayrollEmployees(); });

    // === GIAO DIEN TIEN LUONG ===
    $router->get('/tien-luong/bang-luong', function() { require __DIR__ . '/../public/views/payroll_entries.php'; });
    $router->get('/tien-luong/tinh-luong', function() { require __DIR__ . '/../public/views/payroll_calculate.php'; });
    $router->get('/tien-luong/bao-hiem', function() { require __DIR__ . '/../public/views/payroll_insurance.php'; });
    $router->get('/tien-luong/thue-tncn', function() { require __DIR__ . '/../public/views/payroll_tax.php'; });
    $router->get('/tien-luong/phieu-luong', function() { require __DIR__ . '/../public/views/payroll_payslip.php'; });
    $router->get('/tien-luong/ke-khai-bhxh', function() { require __DIR__ . '/../public/views/payroll_bhxh.php'; });

    // CSRF — endpoint lấy token bảo vệ CSRF cho client
    // Dùng khi token trong meta tag không khả dụng (VD: SPA, mobile app)
    $router->get('/api/csrf-token', function() { JsonResponse::ok(['token' => Auth::csrfToken()]); });
}

$GLOBALS['router'] = new Router();
defineRoutes($GLOBALS['router']);
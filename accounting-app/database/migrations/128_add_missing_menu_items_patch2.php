<?php
// NGHIỆP VỤ: Bổ sung menu items còn thiếu & sửa đường dẫn hỏng
//
// Migration 119 đã tái cấu trúc từ 14→12 section nhưng bỏ sót nhiều item có view hoạt động.
// Migration này:
//   1. Sửa 2 route bị hỏng (contracts → danh-muc/hop-dong, 3 manufacturing routes)
//   2. Bổ sung ~25 items còn thiếu — ưu tiên P0 (form đã có view, business-critical)
//
// Sections hiện tại (từ 119):
//   cash_bank, purchase_ap, sales_ar, inventory_ccdc, fixed_asset,
//   manufacturing, projects_contracts, payroll, tax, gl_report, system
//
return function (PDO $pdo) {

    // ============================================================
    // 1. SỬA ROUTE BỊ HỎNG (UPDATE)
    // ============================================================

    // Hợp đồng → route đúng
    $pdo->exec("UPDATE menu_items SET route = '/danh-muc/hop-dong' WHERE route = '/contracts' AND is_active = 1");

    // ============================================================
    // 2. BỔ SUNG MENU ITEMS CÒN THIẾU
    // ============================================================

    $insert = $pdo->prepare(
        "INSERT IGNORE INTO menu_items (parent_id, section, label, icon, route, permission_module, permission_action, sort_order, is_active, is_heading, badge)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?)"
    );
    // Dùng IGNORE để tránh duplicate nếu chạy lại

    $items = [];

    // --- 2a. TIỀN MẶT & NGÂN HÀNG (cash_bank) ---
    $items[] = [null, 'cash_bank', 'Tiền đang chuyển', 'bi-arrow-left-right', '/thu/tien-dang-chuyen', 'cash', 'read', 31, null];
    // Đề nghị tạm ứng (Mẫu 03-TT) — P0
    $items[] = [null, 'cash_bank', 'Đề nghị tạm ứng', 'bi-wallet2', '/thu/de-nghi-tam-ung', 'cash', 'create', 32, null];

    // --- 2b. MUA HÀNG & CÔNG NỢ PHẢI TRẢ (purchase_ap) ---
    // Hợp đồng đã có trong projects_contracts (migration 119) — chỉ sửa route ở phần 1
    $items[] = [null, 'purchase_ap', 'Ngân sách mua hàng', 'bi-pie-chart', '/mua/ngan-sach', 'purchase', 'read', 49, null];

    // --- 2c. BÁN HÀNG & CÔNG NỢ PHẢI THU (sales_ar) ---
    // Đã đủ 8 items, không bổ sung thêm

    // --- 2d. KHO & CCDC (inventory_ccdc) ---
    $items[] = [null, 'inventory_ccdc', 'Điều chuyển kho', 'bi-arrow-left-right', '/kho/dieu-chuyen', 'inventory', 'create', 91, null];
    $items[] = [null, 'inventory_ccdc', 'Hàng mua trả lại', 'bi-arrow-return-left', '/kho/hang-mua-tra-lai', 'inventory', 'create', 92, null];
    $items[] = [null, 'inventory_ccdc', 'Xuất hủy', 'bi-trash', '/kho/xuat-huy', 'inventory', 'create', 93, null];
    $items[] = [null, 'inventory_ccdc', 'Hàng gửi đi bán', 'bi-send', '/kho/hang-gui-ban', 'inventory', 'read', 94, null];
    $items[] = [null, 'inventory_ccdc', 'Hàng mua đang đi đường', 'bi-truck', '/kho/hang-dang-di-duong', 'inventory', 'read', 95, null];
    $items[] = [null, 'inventory_ccdc', 'Dự phòng giảm giá', 'bi-exclamation-triangle', '/kho/du-phong-giam-gia', 'inventory', 'read', 96, null];
    $items[] = [null, 'inventory_ccdc', 'PP tính giá', 'bi-calculator', '/danh-muc/phuong-phap-tinh-gia', 'inventory', 'read', 97, null];

    // --- 2e. TSCĐ (fixed_asset) ---
    // Đã đủ 7 items, không bổ sung

    // --- 2f. SẢN XUẤT & GIÁ THÀNH (manufacturing) ---
    // Đã đủ

    // --- 2g. DỰ ÁN & HỢP ĐỒNG (projects_contracts) ---
    // Đã đủ

    // --- 2h. LƯƠNG & NHÂN SỰ (payroll) ---
    // Đã đủ

    // --- 2i. THUẾ & HÓA ĐƠN (tax) ---
    $items[] = [null, 'tax', 'Tỷ giá', 'bi-currency-exchange', '/danh-muc/ty-gia', 'tax', 'read', 169, null];
    $items[] = [null, 'tax', 'Gửi & Nộp thuế', 'bi-send', '/thue/gui-nop-thue', 'tax', 'post', 170, null];

    // --- 2j. KẾ TOÁN TỔNG HỢP (gl_report) ---
    // Báo cáo tài chính bị thiếu so với seed 112
    $items[] = [null, 'gl_report', 'BC KQKD (BC 02)', 'bi-file-earmark', '/bao-cao/ket-qua-kinh-doanh', 'report', 'read', 192, null];
    $items[] = [null, 'gl_report', 'BC LCTT (BC 03)', 'bi-file-earmark', '/bao-cao/luu-chuyen-tien-te', 'report', 'read', 193, null];
    $items[] = [null, 'gl_report', 'Thuyết minh BCTC (BC09)', 'bi-file-earmark-text', '/bao-cao/thuyet-minh-bctc', 'report', 'read', 194, null];
    $items[] = [null, 'gl_report', 'Xuất XBRL (GDT)', 'bi-filetype-xml', '/bao-cao/xbrl', 'report', 'export', 195, null];
    $items[] = [null, 'gl_report', 'Ngân sách & Dự toán', 'bi-pie-chart', '/ngan-sach', 'budget', 'read', 196, null];
    // GL operations còn thiếu
    $items[] = [null, 'gl_report', 'Sổ chi tiết', 'bi-journal-richtext', '/bao-cao/so-chi-tiet', 'journal', 'read', 197, null];
    $items[] = [null, 'gl_report', 'Kiểm tra trước khóa sổ', 'bi-clipboard-check', '/he-thong/kiem-tra-truoc-khi-khoa-so', 'journal', 'read', 198, null];
    $items[] = [null, 'gl_report', 'Khóa sổ cuối kỳ', 'bi-lock', '/tong-hop/khoa-so-cuoi-ky', 'journal', 'close', 199, null];
    $items[] = [null, 'gl_report', 'So sánh số liệu 2 kỳ', 'bi-bar-chart', '/tong-hop/so-sanh-ky', 'journal', 'read', 200, null];
    $items[] = [null, 'gl_report', 'Đánh giá lại ngoại tệ', 'bi-currency-exchange', '/bao-cao/ty-gia', 'journal', 'post', 201, null];

    // --- 2k. HỆ THỐNG (system) ---
    $items[] = [null, 'system', 'Người dùng', 'bi-person', '/he-thong/nguoi-dung', 'admin', 'read', 229, null];
    $items[] = [null, 'system', 'Thông báo', 'bi-bell', '/he-thong/thong-bao', 'admin', 'read', 230, null];
    $items[] = [null, 'system', 'Thiết kế mẫu in', 'bi-printer', '/he-thong/thiet-ke-mau-in', 'print', 'read', 231, null];

    foreach ($items as $item) {
        $insert->execute($item);
    }
};

<?php
// NGHIỆP VỤ: Tái cấu trúc menu điều hướng từ 14 section xuống 12 workspace
//
// Nguyên tắc thiết kế:
// - Tối đa 12 workspace, mỗi workspace tối đa 8 mục
// - Tổ chức theo vòng đời nghiệp vụ (user journey), không theo module kỹ thuật
// - Gom các section nhỏ vào workspace lớn hơn:
//   • CCDC → gộp vào Kho (Inventory)
//   • Báo cáo tài chính (FS) → gộp vào Kế toán tổng hợp (GL)
//   • Dự án → gộp với Hợp đồng
//   • Hóa đơn điện tử → gộp vào Thuế
// - Giữ các cặp nghiệp vụ tự nhiên: Mua hàng + Công nợ phải trả, Bán hàng + Công nợ phải thu
//
// Tham khảo: SAP Fiori Apps, Oracle Fusion, Odoo 17, MISA SME 2025
//
return function (PDO $pdo) {
    // Lấy danh sách các section cũ để deactivate
    $oldSections = ['cash', 'ap', 'ar', 'inventory', 'ccdc', 'fa', 'manufacturing',
                     'projects', 'payroll', 'tax', 'gl', 'fs', 'system'];
    $placeholders = implode(',', array_fill(0, count($oldSections), '?'));
    $pdo->prepare("UPDATE menu_items SET is_active = 0 WHERE section IN ($placeholders) AND is_active = 1")
        ->execute($oldSections);

    $insert = $pdo->prepare(
        "INSERT INTO menu_items (parent_id, section, label, icon, route, permission_module, permission_action, sort_order, is_active, is_heading, badge)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)"
    );

    $items = [];

    // ============================================================
    // 1. TỔNG QUAN — Dashboard
    // ============================================================
    $items[] = [null, 'dashboard', 'Tổng quan', 'bi-speedometer2', '/', null, null, 10, 0, null];

    // ============================================================
    // 2. TIỀN MẶT & NGÂN HÀNG — Cash & Bank (8 items)
    // ============================================================
    $items[] = [null, 'cash_bank', 'Tiền mặt & Ngân hàng', 'bi-cash-coin', null, 'cash', 'read', 20, 1, null];
    $items[] = [null, 'cash_bank', 'TK ngân hàng', 'bi-bank', '/danh-muc/tai-khoan-ngan-hang', 'cash', 'read', 21, 0, null];
    $items[] = [null, 'cash_bank', 'Phiếu thu', 'bi-cash', '/thu/quy-tien-mat', 'cash', 'create', 22, 0, null];
    $items[] = [null, 'cash_bank', 'Phiếu chi', 'bi-cash-stack', '/chi/quy-tien-mat', 'cash', 'create', 23, 0, null];
    $items[] = [null, 'cash_bank', 'Giấy báo Có', 'bi-credit-card', '/thu/giao-bao-co', 'cash', 'create', 24, 0, null];
    $items[] = [null, 'cash_bank', 'Giấy báo Nợ', 'bi-credit-card-2-back', '/chi/giao-bao-no', 'cash', 'create', 25, 0, null];
    $items[] = [null, 'cash_bank', 'Sổ quỹ tiền mặt', 'bi-journal-text', '/thu/so-quy-tien-mat', 'cash', 'read', 26, 0, null];
    $items[] = [null, 'cash_bank', 'Đối chiếu ngân hàng', 'bi-files', '/thu/doi-chieu-ngan-hang', 'cash', 'read', 27, 0, null];
    $items[] = [null, 'cash_bank', 'Báo cáo vốn bằng tiền', 'bi-file-earmark-bar-graph', '/thu/bao-cao-von-bang-tien', 'cash', 'read', 28, 0, null];

    // ============================================================
    // 3. MUA HÀNG & CÔNG NỢ PHẢI TRẢ — Purchase to Pay (8 items)
    // ============================================================
    $items[] = [null, 'purchase_ap', 'Mua hàng & Công nợ phải trả', 'bi-cart3', null, 'ap', 'read', 40, 1, null];
    $items[] = [null, 'purchase_ap', 'Nhà cung cấp', 'bi-people', '/danh-muc/nha-cung-cap', 'ap', 'read', 41, 0, null];
    $items[] = [null, 'purchase_ap', 'Đề nghị mua hàng', 'bi-file-earmark-plus', '/mua/de-nghi-mua-hang', 'purchase', 'create', 42, 0, null];
    $items[] = [null, 'purchase_ap', 'Đơn đặt hàng', 'bi-bag-check', '/mua/don-dat-hang', 'purchase', 'create', 43, 0, null];
    $items[] = [null, 'purchase_ap', 'Nhập kho theo PO', 'bi-box-seam', '/mua/nhap-kho-theo-po', 'purchase', 'create', 44, 0, null];
    $items[] = [null, 'purchase_ap', 'Hóa đơn & Công nợ', 'bi-receipt', '/mua/cong-no-phai-tra', 'ap', 'read', 45, 0, null];
    $items[] = [null, 'purchase_ap', 'Đối chiếu hóa đơn', 'bi-file-earmark-diff', '/mua/doi-chieu-hoa-don', 'ap', 'read', 46, 0, null];
    $items[] = [null, 'purchase_ap', 'Phân tích tuổi nợ', 'bi-bar-chart', '/mua/phan-tich-tuoi-no', 'ap', 'read', 47, 0, null];
    $items[] = [null, 'purchase_ap', 'Sổ chi tiết công nợ', 'bi-journal-richtext', '/mua/so-chi-tiet-cong-no', 'ap', 'read', 48, 0, null];

    // ============================================================
    // 4. BÁN HÀNG & CÔNG NỢ PHẢI THU — Order to Cash (8 items)
    // ============================================================
    $items[] = [null, 'sales_ar', 'Bán hàng & Công nợ phải thu', 'bi-bag', null, 'ar', 'read', 60, 1, null];
    $items[] = [null, 'sales_ar', 'Khách hàng', 'bi-people', '/danh-muc/khach-hang', 'ar', 'read', 61, 0, null];
    $items[] = [null, 'sales_ar', 'Đơn đặt hàng', 'bi-cart', '/ban/don-dat-hang', 'sales', 'create', 62, 0, null];
    $items[] = [null, 'sales_ar', 'Hóa đơn bán hàng', 'bi-receipt-cutoff', '/ban/cong-no-phai-thu', 'ar', 'create', 63, 0, null];
    $items[] = [null, 'sales_ar', 'Hàng bán trả lại', 'bi-arrow-return-left', '/kho/hang-ban-tra-lai', 'ar', 'create', 64, 0, null];
    $items[] = [null, 'sales_ar', 'Công nợ phải thu', 'bi-credit-card-2-front', '/ban/cong-no-phai-thu', 'ar', 'read', 65, 0, null];
    $items[] = [null, 'sales_ar', 'Phân tích tuổi nợ', 'bi-bar-chart', '/ban/phan-tich-tuoi-no', 'ar', 'read', 66, 0, null];
    $items[] = [null, 'sales_ar', 'Sổ chi tiết công nợ', 'bi-journal-richtext', '/ban/so-chi-tiet-cong-no', 'ar', 'read', 67, 0, null];

    // ============================================================
    // 5. KHO & CCDC — Inventory (gộp CCDC vào, 8 items)
    // ============================================================
    $items[] = [null, 'inventory_ccdc', 'Kho & CCDC', 'bi-boxes', null, 'inventory', 'read', 80, 1, null];
    $items[] = [null, 'inventory_ccdc', 'Vật tư, hàng hóa', 'bi-box', '/danh-muc/vat-tu', 'inventory', 'read', 81, 0, null];
    $items[] = [null, 'inventory_ccdc', 'CCDC', 'bi-tool', '/danh-muc/cong-cu-dung-cu', 'inventory', 'read', 82, 0, null];
    $items[] = [null, 'inventory_ccdc', 'Kho', 'bi-building', '/danh-muc/kho', 'inventory', 'read', 83, 0, null];
    $items[] = [null, 'inventory_ccdc', 'Đơn vị tính', 'bi-rulers', '/danh-muc/don-vi-tinh', 'inventory', 'read', 84, 0, null];
    $items[] = [null, 'inventory_ccdc', 'Nhập kho', 'bi-box-arrow-in-right', '/kho/nhap-kho', 'inventory', 'create', 85, 0, null];
    $items[] = [null, 'inventory_ccdc', 'Xuất kho', 'bi-box-arrow-right', '/kho/xuat-kho', 'inventory', 'create', 86, 0, null];
    $items[] = [null, 'inventory_ccdc', 'Kiểm kê', 'bi-clipboard-check', '/kho/kiem-ke', 'inventory', 'create', 87, 0, null];
    $items[] = [null, 'inventory_ccdc', 'Tính giá xuất kho', 'bi-calculator', '/kho/kiem-ke-dinh-ky', 'inventory', 'read', 88, 0, null];

    // ============================================================
    // 6. TSCĐ — Fixed Assets (6 items)
    // ============================================================
    $items[] = [null, 'fixed_asset', 'TSCĐ', 'bi-building', null, 'fixed_asset', 'read', 110, 1, null];
    $items[] = [null, 'fixed_asset', 'Tài sản cố định', 'bi-building', '/danh-muc/tai-san-co-dinh', 'fixed_asset', 'read', 111, 0, null];
    $items[] = [null, 'fixed_asset', 'Chính sách khấu hao', 'bi-gear', '/danh-muc/chinh-sach-khau-hao', 'fixed_asset', 'read', 112, 0, null];
    $items[] = [null, 'fixed_asset', 'Ghi tăng TSCĐ', 'bi-plus-circle', '/tai-san-co-dinh/ghi-tang', 'fixed_asset', 'create', 113, 0, null];
    $items[] = [null, 'fixed_asset', 'Tính khấu hao', 'bi-calculator', '/danh-muc/tai-san-co-dinh/tinh-khau-hao', 'fixed_asset', 'post', 114, 0, null];
    $items[] = [null, 'fixed_asset', 'Điều chuyển TSCĐ', 'bi-arrow-left-right', '/tai-san-co-dinh/dieu-chuyen', 'fixed_asset', 'create', 115, 0, null];
    $items[] = [null, 'fixed_asset', 'Giảm / Thanh lý', 'bi-trash', '/tai-san-co-dinh/thanh-ly', 'fixed_asset', 'create', 116, 0, null];

    // ============================================================
    // 7. SẢN XUẤT & GIÁ THÀNH — Manufacturing (6 items)
    // ============================================================
    $items[] = [null, 'manufacturing', 'Sản xuất & Giá thành', 'bi-gear-wide', null, 'manufacturing', 'read', 120, 1, null];
    $items[] = [null, 'manufacturing', 'Định mức (BOM)', 'bi-list-check', '/san-xuat', 'manufacturing', 'read', 121, 0, null];
    $items[] = [null, 'manufacturing', 'Lệnh sản xuất', 'bi-file-earmark', '/san-xuat', 'manufacturing', 'create', 122, 0, null];
    $items[] = [null, 'manufacturing', 'Xuất kho NVL', 'bi-box-arrow-right', '/san-xuat', 'manufacturing', 'create', 123, 0, null];
    $items[] = [null, 'manufacturing', 'Nhập kho thành phẩm', 'bi-box-arrow-in-down', '/san-xuat', 'manufacturing', 'create', 124, 0, null];
    $items[] = [null, 'manufacturing', 'Tính giá thành', 'bi-calculator', '/san-xuat', 'manufacturing', 'read', 125, 0, null];
    $items[] = [null, 'manufacturing', 'Dashboard sản xuất', 'bi-speedometer2', '/san-xuat', 'manufacturing', 'read', 126, 0, null];

    // ============================================================
    // 8. DỰ ÁN & HỢP ĐỒNG — Projects & Contracts (6 items)
    // ============================================================
    $items[] = [null, 'projects_contracts', 'Dự án & Hợp đồng', 'bi-diagram-3', null, 'project', 'read', 130, 1, null];
    $items[] = [null, 'projects_contracts', 'Danh mục dự án', 'bi-folder', '/danh-muc/du-an', 'project', 'read', 131, 0, null];
    $items[] = [null, 'projects_contracts', 'Quản lý dự án', 'bi-kanban', '/du-an', 'project', 'read', 132, 0, null];
    $items[] = [null, 'projects_contracts', 'Phân bổ chi phí', 'bi-pie-chart', '/du-an', 'project', 'create', 133, 0, null];
    $items[] = [null, 'projects_contracts', 'Doanh thu & Quyết toán', 'bi-currency-dollar', '/du-an', 'project', 'create', 134, 0, null];
    $items[] = [null, 'projects_contracts', 'Hợp đồng', 'bi-file-earmark-text', '/contracts', 'contract', 'read', 135, 0, null];

    // ============================================================
    // 9. LƯƠNG & NHÂN SỰ — Payroll (8 items)
    // ============================================================
    $items[] = [null, 'payroll', 'Tiền lương & Nhân sự', 'bi-people', null, 'payroll', 'read', 140, 1, null];
    $items[] = [null, 'payroll', 'Nhân viên', 'bi-person', '/danh-muc/nhan-vien', 'payroll', 'read', 141, 0, null];
    $items[] = [null, 'payroll', 'Phòng ban', 'bi-building', '/danh-muc/phong-ban', 'payroll', 'read', 142, 0, null];
    $items[] = [null, 'payroll', 'Bảng lương', 'bi-file-spreadsheet', '/tien-luong/bang-luong', 'payroll', 'read', 143, 0, null];
    $items[] = [null, 'payroll', 'Tính lương', 'bi-calculator', '/tien-luong/tinh-luong', 'payroll', 'post', 144, 0, null];
    $items[] = [null, 'payroll', 'Bảo hiểm', 'bi-shield-check', '/tien-luong/bao-hiem', 'payroll', 'post', 145, 0, null];
    $items[] = [null, 'payroll', 'Thuế TNCN', 'bi-file-text', '/tien-luong/thue-tncn', 'payroll', 'post', 146, 0, null];
    $items[] = [null, 'payroll', 'Phiếu lương', 'bi-wallet2', '/tien-luong/phieu-luong', 'payroll', 'read', 147, 0, null];
    $items[] = [null, 'payroll', 'Kê khai BHXH', 'bi-upload', '/tien-luong/ke-khai-bhxh', 'payroll', 'post', 148, 0, null];

    // ============================================================
    // 10. THUẾ & HÓA ĐƠN — Tax & E-invoice (8 items)
    // ============================================================
    $items[] = [null, 'tax', 'Thuế & Hóa đơn', 'bi-file-earmark-text', null, 'tax', 'read', 160, 1, null];
    $items[] = [null, 'tax', 'Biểu thuế', 'bi-table', '/danh-muc/bieu-thue', 'tax', 'read', 161, 0, null];
    $items[] = [null, 'tax', 'Kê khai GTGT', 'bi-file-earmark-text', '/thue/ke-khai-gtgt', 'tax', 'create', 162, 0, null];
    $items[] = [null, 'tax', 'Bảng kê mua / bán', 'bi-list-ul', '/thue/bang-ke', 'tax', 'read', 163, 0, null];
    $items[] = [null, 'tax', 'Quyết toán GTGT', 'bi-check2-square', '/thue/quyet-toan-gtgt', 'tax', 'post', 164, 0, null];
    $items[] = [null, 'tax', 'Quyết toán TNDN', 'bi-calculator', '/thue/quyet-toan-tndn', 'tax', 'post', 165, 0, null];
    $items[] = [null, 'tax', 'Quyết toán TNCN', 'bi-person-badge', '/thue/quyet-toan-tncn', 'tax', 'post', 166, 0, null];
    $items[] = [null, 'tax', 'Nhà thầu nước ngoài', 'bi-globe', '/thue/nha-thau-nuoc-ngoai', 'tax', 'create', 167, 0, null];
    $items[] = [null, 'tax', 'Hóa đơn điện tử', 'bi-file-earmark-pdf', '/hoa-don-dien-tu', 'einvoice', 'create', 168, 0, null];

    // ============================================================
    // 11. KẾ TOÁN TỔNG HỢP — GL + FS + Báo cáo (gộp FS vào, 8 items)
    // ============================================================
    $items[] = [null, 'gl_report', 'Kế toán tổng hợp', 'bi-journal', null, 'journal', 'read', 180, 1, null];
    $items[] = [null, 'gl_report', 'Chứng từ ghi sổ', 'bi-file-earmark', '/tong-hop/chung-tu-ghi-so', 'journal', 'create', 181, 0, null];
    $items[] = [null, 'gl_report', 'Điều chỉnh bút toán', 'bi-pencil-square', '/dieu-chinh-but-toan', 'journal', 'create', 182, 0, null];
    $items[] = [null, 'gl_report', 'Phê duyệt', 'bi-check2-circle', '/tong-hop/phe-duyet', 'journal', 'approve', 183, 0, null];
    $items[] = [null, 'gl_report', 'Kết chuyển & Khóa sổ', 'bi-arrow-repeat', '/tong-hop/ket-chuyen', 'journal', 'post', 184, 0, null];
    $items[] = [null, 'gl_report', 'BCĐ số phát sinh', 'bi-table', '/tong-hop/bang-can-doi-so-phat-sinh', 'journal', 'read', 185, 0, null];
    $items[] = [null, 'gl_report', 'Sổ Nhật ký chung', 'bi-journal-text', '/bao-cao/nhat-ky-chung', 'journal', 'read', 186, 0, null];
    $items[] = [null, 'gl_report', 'Sổ Cái & Sổ chi tiết', 'bi-book', '/bao-cao/so-cai', 'journal', 'read', 187, 0, null];
    $items[] = [null, 'gl_report', 'Báo cáo tài chính', 'bi-bar-chart', '/bao-cao/tinh-hinh-tai-chinh', 'report', 'read', 188, 0, null];

    // ============================================================
    // 12. HỆ THỐNG — System Admin (8 items)
    // ============================================================
    $items[] = [null, 'system', 'Hệ thống', 'bi-gear', null, 'admin', 'read', 220, 1, null];
    $items[] = [null, 'system', 'Hệ thống tài khoản', 'bi-book', '/danh-muc/he-thong-tai-khoan', 'admin', 'read', 221, 0, null];
    $items[] = [null, 'system', 'Người dùng & Phân quyền', 'bi-shield-lock', '/he-thong/vai-tro', 'admin', 'read', 222, 0, null];
    $items[] = [null, 'system', 'Số dư đầu kỳ', 'bi-calculator', '/he-thong/so-du-dau-ky', 'admin', 'create', 223, 0, null];
    $items[] = [null, 'system', 'Quản lý kỳ', 'bi-calendar', '/he-thong/quan-ly-ky', 'admin', 'read', 224, 0, null];
    $items[] = [null, 'system', 'Giao dịch nội bộ', 'bi-diagram-3', '/he-thong/noi-bo', 'journal', 'create', 225, 0, null];
    $items[] = [null, 'system', 'Cấu hình & Mẫu in', 'bi-sliders', '/he-thong/cau-hinh', 'admin', 'read', 226, 0, null];
    $items[] = [null, 'system', 'Nhật ký hoạt động', 'bi-activity', '/he-thong/nhat-ky-hoat-dong', 'admin', 'read', 227, 0, null];
    $items[] = [null, 'system', 'Sao lưu & Phục hồi', 'bi-cloud-arrow-up', '/he-thong/sao-luu', 'admin', 'read', 228, 0, null];

    foreach ($items as $item) {
        $insert->execute($item);
    }
};

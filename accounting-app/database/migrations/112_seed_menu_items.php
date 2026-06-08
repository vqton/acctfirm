<?php
// NGHIỆP VỤ: Seed menu điều hướng — cấu trúc workflow-based
//
// Menu được tổ chức theo vòng đời nghiệp vụ (user journey), không theo module kỹ thuật.
// Kế toán viên làm theo quy trình: tiếp nhận → xử lý → ghi sổ → báo cáo.
//
// Tham khảo: MISA SME 2025, FAST Accounting 2024, BRAVO 8 — tất cả đều chuyển
// từ module-based sang workflow-based từ năm 2022-2024.
//
// 14 sections, ~100 menu items — đầy đủ cho ERP doanh nghiệp vừa và nhỏ.
//
return function (PDO $pdo) {
    // Chỉ seed nếu chưa có dữ liệu
    $exists = $pdo->query("SELECT COUNT(*) FROM menu_items")->fetchColumn();
    if ((int)$exists > 0) return;

    $insert = $pdo->prepare(
        "INSERT INTO menu_items (parent_id, section, label, icon, route, permission_module, permission_action, sort_order, is_active, is_heading, badge)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)"
    );

    $items = [];

    // ============================================================
    // 1. TỔNG QUAN (Overview/Dashboard)
    // ============================================================
    $items[] = [null, 'dashboard', 'Tổng quan', 'bi-speedometer2', '/', null, null, 10, 0, null];

    // ============================================================
    // 2. TIỀN MẶT & NGÂN HÀNG (Cash & Bank)
    // ============================================================
    $items[] = [null, 'cash', 'Tiền mặt & Ngân hàng', 'bi-cash-coin', null, 'cash', 'read', 20, 1, null];
    $items[] = [null, 'cash', 'TK ngân hàng', 'bi-bank', '/danh-muc/tai-khoan-ngan-hang', 'cash', 'read', 21, 0, null];
    $items[] = [null, 'cash', 'Phiếu thu', 'bi-cash', '/thu/quy-tien-mat', 'cash', 'create', 22, 0, null];
    $items[] = [null, 'cash', 'Phiếu chi', 'bi-cash-stack', '/chi/quy-tien-mat', 'cash', 'create', 23, 0, null];
    $items[] = [null, 'cash', 'Giấy báo Có', 'bi-credit-card', '/thu/giao-bao-co', 'cash', 'create', 24, 0, null];
    $items[] = [null, 'cash', 'Giấy báo Nợ', 'bi-credit-card-2-back', '/chi/giao-bao-no', 'cash', 'create', 25, 0, null];
    $items[] = [null, 'cash', 'Tiền đang chuyển', 'bi-arrow-left-right', '/thu/tien-dang-chuyen', 'cash', 'read', 26, 0, null];
    $items[] = [null, 'cash', 'Tạm ứng', 'bi-wallet2', '/thu/tam-ung', 'cash', 'create', 27, 0, null];
    $items[] = [null, 'cash', 'Sổ quỹ tiền mặt', 'bi-journal-text', '/thu/so-quy-tien-mat', 'cash', 'read', 28, 0, null];
    $items[] = [null, 'cash', 'Đối chiếu ngân hàng', 'bi-files', '/thu/doi-chieu-ngan-hang', 'cash', 'read', 29, 0, null];
    $items[] = [null, 'cash', 'Báo cáo vốn bằng tiền', 'bi-file-earmark-bar-graph', '/thu/bao-cao-von-bang-tien', 'cash', 'read', 30, 0, null];
    $items[] = [null, 'cash', 'Đánh giá lại ngoại tệ', 'bi-currency-exchange', '/bao-cao/ty-gia', 'cash', 'read', 31, 0, null];

    // ============================================================
    // 3. MUA HÀNG & CÔNG NỢ PHẢI TRẢ (AP)
    // ============================================================
    $items[] = [null, 'ap', 'Mua hàng & Công nợ phải trả', 'bi-cart3', null, 'ap', 'read', 40, 1, null];
    $items[] = [null, 'ap', 'Nhà cung cấp', 'bi-people', '/danh-muc/nha-cung-cap', 'ap', 'read', 41, 0, null];
    $items[] = [null, 'ap', 'Hợp đồng', 'bi-file-earmark-text', '/contracts', 'contract', 'read', 42, 0, null];
    $items[] = [null, 'ap', 'Đề nghị mua hàng', 'bi-file-earmark-plus', '/mua/de-nghi-mua-hang', 'purchase', 'create', 43, 0, null];
    $items[] = [null, 'ap', 'Đơn đặt hàng', 'bi-bag-check', '/mua/don-dat-hang', 'purchase', 'create', 44, 0, null];
    $items[] = [null, 'ap', 'Nhập kho theo PO', 'bi-box-seam', '/mua/nhap-kho-theo-po', 'purchase', 'create', 45, 0, null];
    $items[] = [null, 'ap', 'Hóa đơn mua hàng', 'bi-receipt', '/mua/mua-hang/hoa-don', 'ap', 'create', 46, 0, null];
    $items[] = [null, 'ap', 'Đối chiếu hóa đơn', 'bi-file-earmark-diff', '/mua/doi-chieu-hoa-don', 'ap', 'read', 47, 0, null];
    $items[] = [null, 'ap', 'Dashboard công nợ phải trả', 'bi-speedometer2', '/mua/dashboard-cong-no', 'ap', 'read', 48, 0, null];
    $items[] = [null, 'ap', 'Công nợ phải trả', 'bi-credit-card-2-front', '/mua/cong-no-phai-tra', 'ap', 'read', 49, 0, null];
    $items[] = [null, 'ap', 'Phân tích tuổi nợ', 'bi-bar-chart', '/mua/phan-tich-tuoi-no', 'ap', 'read', 50, 0, null];
    $items[] = [null, 'ap', 'Sổ chi tiết công nợ', 'bi-journal-richtext', '/mua/so-chi-tiet-cong-no', 'ap', 'read', 51, 0, null];
    $items[] = [null, 'ap', 'Ngân sách mua hàng', 'bi-pie-chart', '/mua/ngan-sach', 'purchase', 'read', 52, 0, null];

    // ============================================================
    // 4. BÁN HÀNG & CÔNG NỢ PHẢI THU (AR)
    // ============================================================
    $items[] = [null, 'ar', 'Bán hàng & Công nợ phải thu', 'bi-bag', null, 'ar', 'read', 60, 1, null];
    $items[] = [null, 'ar', 'Khách hàng', 'bi-people', '/danh-muc/khach-hang', 'ar', 'read', 61, 0, null];
    $items[] = [null, 'ar', 'Đơn đặt hàng', 'bi-cart', '/ban/don-dat-hang', 'sales', 'create', 62, 0, null];
    $items[] = [null, 'ar', 'Hóa đơn bán hàng', 'bi-receipt-cutoff', '/ban/hoa-don-ban-hang', 'ar', 'create', 63, 0, null];
    $items[] = [null, 'ar', 'Dashboard công nợ phải thu', 'bi-speedometer2', '/ban/dashboard-cong-no', 'ar', 'read', 64, 0, null];
    $items[] = [null, 'ar', 'Công nợ phải thu', 'bi-credit-card-2-front', '/ban/cong-no-phai-thu', 'ar', 'read', 65, 0, null];
    $items[] = [null, 'ar', 'Phân tích tuổi nợ', 'bi-bar-chart', '/ban/phan-tich-tuoi-no', 'ar', 'read', 66, 0, null];
    $items[] = [null, 'ar', 'Sổ chi tiết công nợ', 'bi-journal-richtext', '/ban/so-chi-tiet-cong-no', 'ar', 'read', 67, 0, null];
    $items[] = [null, 'ar', 'Bảng giá & Chiết khấu', 'bi-tags', '/ban/bang-gia', 'ar', 'read', 68, 0, null];
    $items[] = [null, 'ar', 'Bù trừ công nợ', 'bi-arrow-left-right', '/ban/bu-tru-cong-no', 'ar', 'update', 69, 0, null];

    // ============================================================
    // 5. HÀNG TỒN KHO (Inventory)
    // ============================================================
    $items[] = [null, 'inventory', 'Hàng tồn kho', 'bi-boxes', null, 'inventory', 'read', 80, 1, null];
    $items[] = [null, 'inventory', 'Vật tư, hàng hóa', 'bi-box', '/danh-muc/vat-tu', 'inventory', 'read', 81, 0, null];
    $items[] = [null, 'inventory', 'Đơn vị tính', 'bi-rulers', '/danh-muc/don-vi-tinh', 'inventory', 'read', 82, 0, null];
    $items[] = [null, 'inventory', 'Kho', 'bi-building', '/danh-muc/kho', 'inventory', 'read', 83, 0, null];
    $items[] = [null, 'inventory', 'PP tính giá', 'bi-calculator', '/danh-muc/phuong-phap-tinh-gia', 'inventory', 'read', 84, 0, null];
    $items[] = [null, 'inventory', 'Nhập kho', 'bi-box-arrow-in-right', '/kho/nhap-kho', 'inventory', 'create', 85, 0, null];
    $items[] = [null, 'inventory', 'Xuất kho', 'bi-box-arrow-right', '/kho/xuat-kho', 'inventory', 'create', 86, 0, null];
    $items[] = [null, 'inventory', 'Hàng bán trả lại', 'bi-arrow-return-left', '/kho/hang-ban-tra-lai', 'inventory', 'create', 87, 0, null];
    $items[] = [null, 'inventory', 'Điều chuyển kho', 'bi-arrow-left-right', '/kho/dieu-chuyen', 'inventory', 'create', 88, 0, null];
    $items[] = [null, 'inventory', 'Kiểm kê', 'bi-clipboard-check', '/kho/kiem-ke', 'inventory', 'create', 89, 0, null];
    $items[] = [null, 'inventory', 'Tính giá xuất kho (Định kỳ)', 'bi-calculator', '/kho/kiem-ke-dinh-ky', 'inventory', 'read', 90, 0, null];
    $items[] = [null, 'inventory', 'Hàng mua đang đi đường', 'bi-truck', '/kho/hang-dang-di-duong', 'inventory', 'read', 91, 0, null];
    $items[] = [null, 'inventory', 'Hàng gửi đi bán', 'bi-send', '/kho/hang-gui-ban', 'inventory', 'read', 92, 0, null];
    $items[] = [null, 'inventory', 'Dự phòng giảm giá', 'bi-exclamation-triangle', '/kho/du-phong-giam-gia', 'inventory', 'read', 93, 0, null];

    // ============================================================
    // 6. CCDC & CÔNG CỤ DỤNG CỤ (Separated from Fixed Assets)
    // ============================================================
    $items[] = [null, 'ccdc', 'CCDC & Công cụ dụng cụ', 'bi-tools', null, 'inventory', 'read', 100, 1, null];
    $items[] = [null, 'ccdc', 'Danh mục CCDC', 'bi-tool', '/danh-muc/cong-cu-dung-cu', 'inventory', 'read', 101, 0, null];
    $items[] = [null, 'ccdc', 'Phân bổ CCDC', 'bi-pie-chart', '/ccdc/phan-bo', 'inventory', 'create', 102, 0, null];

    // ============================================================
    // 7. TSCĐ (Fixed Assets)
    // ============================================================
    $items[] = [null, 'fa', 'TSCĐ', 'bi-building', null, 'fixed_asset', 'read', 110, 1, null];
    $items[] = [null, 'fa', 'Tài sản cố định', 'bi-building', '/danh-muc/tai-san-co-dinh', 'fixed_asset', 'read', 111, 0, null];
    $items[] = [null, 'fa', 'CS khấu hao', 'bi-gear', '/danh-muc/chinh-sach-khau-hao', 'fixed_asset', 'read', 112, 0, null];
    $items[] = [null, 'fa', 'Ghi tăng TSCĐ', 'bi-plus-circle', '/tai-san-co-dinh/ghi-tang', 'fixed_asset', 'create', 113, 0, null];
    $items[] = [null, 'fa', 'Tính khấu hao', 'bi-calculator', '/danh-muc/tai-san-co-dinh/tinh-khau-hao', 'fixed_asset', 'post', 114, 0, null];
    $items[] = [null, 'fa', 'Điều chuyển TSCĐ', 'bi-arrow-left-right', '/tai-san-co-dinh/dieu-chuyen', 'fixed_asset', 'create', 115, 0, null];
    $items[] = [null, 'fa', 'Giảm / Thanh lý', 'bi-trash', '/tai-san-co-dinh/thanh-ly', 'fixed_asset', 'create', 116, 0, null];

    // ============================================================
    // 8. SẢN XUẤT & GIÁ THÀNH (Manufacturing)
    // ============================================================
    $items[] = [null, 'manufacturing', 'Sản xuất & Giá thành', 'bi-gear-wide', null, 'manufacturing', 'read', 120, 1, null];
    $items[] = [null, 'manufacturing', 'Định mức (BOM)', 'bi-list-check', '/san-xuat/dinh-muc', 'manufacturing', 'read', 121, 0, null];
    $items[] = [null, 'manufacturing', 'Lệnh sản xuất', 'bi-file-earmark', '/san-xuat/lenh-san-xuat', 'manufacturing', 'create', 122, 0, null];
    $items[] = [null, 'manufacturing', 'Xuất kho NVL', 'bi-box-arrow-right', '/san-xuat/xuat-kho-nvl', 'manufacturing', 'create', 123, 0, null];
    $items[] = [null, 'manufacturing', 'Nhập kho thành phẩm', 'bi-box-arrow-in-down', '/san-xuat/nhap-kho-tp', 'manufacturing', 'create', 124, 0, null];
    $items[] = [null, 'manufacturing', 'Tính giá thành', 'bi-calculator', '/san-xuat/tinh-gia-thanh', 'manufacturing', 'read', 125, 0, null];
    $items[] = [null, 'manufacturing', 'Dashboard sản xuất', 'bi-speedometer2', '/san-xuat', 'manufacturing', 'read', 126, 0, null];

    // ============================================================
    // 9. DỰ ÁN (Projects — separated from Sales)
    // ============================================================
    $items[] = [null, 'projects', 'Dự án', 'bi-diagram-3', null, 'project', 'read', 130, 1, null];
    $items[] = [null, 'projects', 'Danh mục dự án', 'bi-folder', '/danh-muc/du-an', 'project', 'read', 131, 0, null];
    $items[] = [null, 'projects', 'Quản lý dự án', 'bi-kanban', '/du-an', 'project', 'read', 132, 0, null];
    $items[] = [null, 'projects', 'Phân bổ chi phí', 'bi-pie-chart', '/du-an/phan-bo-chi-phi', 'project', 'create', 133, 0, null];
    $items[] = [null, 'projects', 'Doanh thu dự án', 'bi-currency-dollar', '/du-an/doanh-thu', 'project', 'create', 134, 0, null];
    $items[] = [null, 'projects', 'Quyết toán dự án', 'bi-check2-square', '/du-an/quyet-toan', 'project', 'post', 135, 0, null];

    // ============================================================
    // 10. TIỀN LƯƠNG & NHÂN SỰ (Payroll)
    // ============================================================
    $items[] = [null, 'payroll', 'Tiền lương & Nhân sự', 'bi-people', null, 'payroll', 'read', 140, 1, null];
    $items[] = [null, 'payroll', 'Nhân viên', 'bi-person', '/danh-muc/nhan-vien', 'payroll', 'read', 141, 0, null];
    $items[] = [null, 'payroll', 'Phòng ban', 'bi-building', '/danh-muc/phong-ban', 'payroll', 'read', 142, 0, null];
    $items[] = [null, 'payroll', 'Bảng lương', 'bi-file-spreadsheet', '/tien-luong/bang-luong', 'payroll', 'read', 143, 0, null];
    $items[] = [null, 'payroll', 'Tính lương', 'bi-calculator', '/tien-luong/tinh-luong', 'payroll', 'post', 144, 0, null];
    $items[] = [null, 'payroll', 'Trích bảo hiểm', 'bi-shield-check', '/tien-luong/bao-hiem', 'payroll', 'post', 145, 0, null];
    $items[] = [null, 'payroll', 'Tính thuế TNCN', 'bi-file-text', '/tien-luong/thue-tncn', 'payroll', 'post', 146, 0, null];
    $items[] = [null, 'payroll', 'Phiếu lương', 'bi-wallet2', '/tien-luong/phieu-luong', 'payroll', 'read', 147, 0, null];
    $items[] = [null, 'payroll', 'Kê khai BHXH', 'bi-upload', '/tien-luong/ke-khai-bhxh', 'payroll', 'post', 148, 0, null];

    // ============================================================
    // 11. THUẾ (Tax)
    // ============================================================
    $items[] = [null, 'tax', 'Thuế', 'bi-file-earmark-text', null, 'tax', 'read', 160, 1, null];
    $items[] = [null, 'tax', 'Biểu thuế', 'bi-table', '/danh-muc/bieu-thue', 'tax', 'read', 161, 0, null];
    $items[] = [null, 'tax', 'Tỷ giá', 'bi-currency-exchange', '/danh-muc/ty-gia', 'tax', 'read', 162, 0, null];
    $items[] = [null, 'tax', 'Kê khai GTGT', 'bi-file-earmark-text', '/thue/ke-khai-gtgt', 'tax', 'create', 163, 0, null];
    $items[] = [null, 'tax', 'Bảng kê mua / bán', 'bi-list-ul', '/thue/bang-ke', 'tax', 'read', 164, 0, null];
    $items[] = [null, 'tax', 'Quyết toán GTGT', 'bi-check2-square', '/thue/quyet-toan-gtgt', 'tax', 'post', 165, 0, null];
    $items[] = [null, 'tax', 'Quyết toán TNDN', 'bi-calculator', '/thue/quyet-toan-tndn', 'tax', 'post', 166, 0, null];
    $items[] = [null, 'tax', 'Quyết toán TNCN', 'bi-person-badge', '/thue/quyet-toan-tncn', 'tax', 'post', 167, 0, null];
    $items[] = [null, 'tax', 'Nhà thầu nước ngoài (FCT)', 'bi-globe', '/thue/nha-thau-nuoc-ngoai', 'tax', 'create', 168, 0, null];
    $items[] = [null, 'tax', 'Hóa đơn điện tử', 'bi-file-earmark-pdf', '/hoa-don-dien-tu', 'einvoice', 'create', 169, 0, null];
    $items[] = [null, 'tax', 'Gửi & Nộp thuế', 'bi-send', '/thue/gui-nop-thue', 'tax', 'post', 170, 0, null];

    // ============================================================
    // 12. KẾ TOÁN TỔNG HỢP (GL)
    // ============================================================
    $items[] = [null, 'gl', 'Kế toán tổng hợp', 'bi-journal', null, 'journal', 'read', 180, 1, null];
    $items[] = [null, 'gl', 'Chứng từ ghi sổ', 'bi-file-earmark', '/tong-hop/chung-tu-ghi-so', 'journal', 'create', 181, 0, null];
    $items[] = [null, 'gl', 'Điều chỉnh bút toán', 'bi-pencil-square', '/dieu-chinh-but-toan', 'journal', 'create', 182, 0, null];
    $items[] = [null, 'gl', 'Phê duyệt', 'bi-check2-circle', '/tong-hop/phe-duyet', 'journal', 'approve', 183, 0, null];
    $items[] = [null, 'gl', 'Kết chuyển cuối kỳ', 'bi-arrow-repeat', '/tong-hop/ket-chuyen', 'journal', 'post', 184, 0, null];
    $items[] = [null, 'gl', 'Đánh giá lại ngoại tệ', 'bi-currency-exchange', '/bao-cao/ty-gia', 'journal', 'post', 185, 0, null];
    $items[] = [null, 'gl', 'Giao dịch nội bộ', 'bi-diagram-3', '/he-thong/noi-bo', 'journal', 'create', 186, 0, null];
    $items[] = [null, 'gl', 'BCĐ số phát sinh', 'bi-table', '/tong-hop/bang-can-doi-so-phat-sinh', 'journal', 'read', 187, 0, null];
    $items[] = [null, 'gl', 'Sổ Nhật ký chung', 'bi-journal-text', '/bao-cao/nhat-ky-chung', 'journal', 'read', 188, 0, null];
    $items[] = [null, 'gl', 'Sổ cái', 'bi-book', '/bao-cao/so-cai', 'journal', 'read', 189, 0, null];
    $items[] = [null, 'gl', 'Sổ chi tiết tổng hợp', 'bi-journal-richtext', '/so-chi-tiet', 'journal', 'read', 190, 0, null];
    $items[] = [null, 'gl', 'Kiểm tra trước khóa sổ', 'bi-clipboard-check', '/he-thong/kiem-tra-truoc-khi-khoa-so', 'journal', 'read', 191, 0, null];
    $items[] = [null, 'gl', 'Khóa sổ cuối kỳ', 'bi-lock', '/tong-hop/khoa-so-cuoi-ky', 'journal', 'close', 192, 0, null];
    $items[] = [null, 'gl', 'So sánh số liệu 2 kỳ', 'bi-bar-chart', '/tong-hop/so-sanh-ky', 'journal', 'read', 193, 0, null];

    // ============================================================
    // 13. BÁO CÁO TÀI CHÍNH (FS — Separate section)
    // ============================================================
    $items[] = [null, 'fs', 'Báo cáo tài chính', 'bi-bar-chart', null, 'report', 'read', 200, 1, null];
    $items[] = [null, 'fs', 'BC CĐKT (BC 01)', 'bi-file-earmark', '/bao-cao/tinh-hinh-tai-chinh', 'report', 'read', 201, 0, null];
    $items[] = [null, 'fs', 'KQKD (BC 02)', 'bi-file-earmark', '/bao-cao/ket-qua-kinh-doanh', 'report', 'read', 202, 0, null];
    $items[] = [null, 'fs', 'LCTT (BC 03)', 'bi-file-earmark', '/bao-cao/luu-chuyen-tien-te', 'report', 'read', 203, 0, null];
    $items[] = [null, 'fs', 'Thuyết minh BCTC (BC09)', 'bi-file-earmark-text', '/bao-cao-tai-chinh/thuyet-minh-bc09', 'report', 'read', 204, 0, null];
    $items[] = [null, 'fs', 'Xuất XBRL (GDT)', 'bi-filetype-xml', '/bao-cao/xbrl', 'report', 'export', 205, 0, null];
    $items[] = [null, 'fs', 'Báo cáo thuế', 'bi-file-earmark-bar-graph', '/bao-cao/thue', 'report', 'read', 206, 0, null];
    $items[] = [null, 'fs', 'Báo cáo quản trị', 'bi-graph-up', '/bao-cao/quan-tri', 'report', 'read', 207, 0, null];
    $items[] = [null, 'fs', 'Ngân sách & Dự toán', 'bi-pie-chart', '/ngan-sach', 'budget', 'read', 208, 0, null];
    $items[] = [null, 'fs', 'Tự thiết kế báo cáo', 'bi-gear', '/bao-cao/tu-thiet-ke', 'report', 'read', 209, 0, null];

    // ============================================================
    // 14. HỆ THỐNG (System Admin)
    // ============================================================
    $items[] = [null, 'system', 'Hệ thống', 'bi-gear', null, 'admin', 'read', 220, 1, null];
    $items[] = [null, 'system', 'Người dùng', 'bi-person', '/he-thong/nguoi-dung', 'admin', 'read', 221, 0, null];
    $items[] = [null, 'system', 'Vai trò & Phân quyền', 'bi-shield-lock', '/he-thong/vai-tro', 'admin', 'read', 222, 0, null];
    $items[] = [null, 'system', 'Cấu hình hệ thống', 'bi-sliders', '/he-thong/cau-hinh', 'admin', 'read', 223, 0, null];
    $items[] = [null, 'system', 'Số dư đầu kỳ', 'bi-calculator', '/he-thong/so-du-dau-ky', 'admin', 'create', 224, 0, null];
    $items[] = [null, 'system', 'Quản lý kỳ', 'bi-calendar', '/he-thong/quan-ly-ky', 'admin', 'read', 225, 0, null];
    $items[] = [null, 'system', 'Nhật ký hoạt động', 'bi-activity', '/he-thong/nhat-ky-hoat-dong', 'admin', 'read', 226, 0, null];
    $items[] = [null, 'system', 'Thông báo', 'bi-bell', '/he-thong/thong-bao', 'admin', 'read', 227, 0, null];
    $items[] = [null, 'system', 'Thiết kế mẫu in', 'bi-printer', '/he-thong/thiet-ke-mau-in', 'print', 'read', 228, 0, null];
    $items[] = [null, 'system', 'Sao lưu & Phục hồi', 'bi-cloud-arrow-up', '/he-thong/sao-luu', 'admin', 'read', 229, 0, null];

    // Thực thi insert
    foreach ($items as $item) {
        $insert->execute($item);
    }
};

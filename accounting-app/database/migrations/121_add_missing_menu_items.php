<?php
// Bổ sung 7 menu item còn thiếu theo IA review của Kế toán trưởng
// Tham khảo: docs/analysis/menu-ia-review.md
return function (PDO $pdo) {
    // 1. Rename "Kê khai BHXH" → "Báo cáo BHXH" (chuẩn nghiệp vụ)
    $pdo->exec(
        "UPDATE menu_items SET label = 'Báo cáo BHXH' WHERE section = 'payroll' AND label = 'Kê khai BHXH' AND is_active = 1"
    );

    // 2. Thêm menu items mới
    $items = [
        // Tiền mặt & Ngân hàng: bổ sung 2 mục
        [null, 'cash_bank', 'Tạm ứng', 'bi-wallet2', '/thu/tam-ung', 'cash', 'create', 29, null],
        [null, 'cash_bank', 'Đánh giá lại tỷ giá', 'bi-currency-exchange', '/thu/danh-gia-lai-ngoai-te', 'cash', 'read', 30, null],

        // Kho & CCDC: bổ sung 2 mục
        [null, 'inventory_ccdc', 'Phân bổ CCDC', 'bi-pie-chart', '/ccdc/phan-bo', 'inventory', 'create', 89, null],
        [null, 'inventory_ccdc', 'Xử lý chênh lệch kiểm kê', 'bi-exclamation-triangle', '/kho/xu-ly-chenh-lech-kiem-ke', 'inventory', 'create', 90, null],

        // Kế toán tổng hợp: bổ sung 3 mục
        [null, 'gl_report', 'Phân bổ chi phí trả trước', 'bi-calendar-check', '/tong-hop/phan-bo-chi-phi-tra-truoc', 'journal', 'create', 189, null],
        [null, 'gl_report', 'Điều chỉnh hồi tố', 'bi-arrow-counterclockwise', '/tong-hop/dieu-chinh-hoi-to', 'journal', 'create', 190, null],
        [null, 'gl_report', 'Báo cáo quản trị', 'bi-graph-up', '/bao-cao/quan-tri', 'report', 'read', 191, null],
    ];

    $insert = $pdo->prepare(
        "INSERT INTO menu_items (parent_id, section, label, icon, route, permission_module, permission_action, sort_order, is_active, is_heading, badge)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?)"
    );
    foreach ($items as $item) {
        $insert->execute($item);
    }
};

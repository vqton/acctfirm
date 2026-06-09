<?php
// NGHIỆP VỤ: Thêm sub-heading cho các section quá nhiều menu item
//
// Sections >12 items được chia làm 2-3 sub-menu (Danh mục / Nhập liệu / Xử lý & Báo cáo)
// Dùng parent_id sẵn có, MenuService::buildTree đã hỗ trợ nesting 3 cấp.
//
return function (PDO $pdo) {

    // ================================================================
    // 1. INVENTORY_CCDC — 3 sub-menus (17 items)
    // Section heading id=155
    // ================================================================

    // Sub-heading: Danh mục
    $pdo->exec("INSERT INTO menu_items (parent_id, section, label, icon, route, permission_module, permission_action, sort_order, is_active, is_heading, badge)
                 VALUES (155, 'inventory_ccdc', 'Danh mục', 'bi-list-ul', NULL, 'inventory', 'read', 81, 1, 1, NULL)");
    $dmId = (int)$pdo->lastInsertId();

    // Sub-heading: Nhập liệu
    $pdo->exec("INSERT INTO menu_items (parent_id, section, label, icon, route, permission_module, permission_action, sort_order, is_active, is_heading, badge)
                 VALUES (155, 'inventory_ccdc', 'Nhập liệu', 'bi-pencil-square', NULL, 'inventory', 'create', 85, 1, 1, NULL)");
    $nlId = (int)$pdo->lastInsertId();

    // Sub-heading: Xử lý & Báo cáo
    $pdo->exec("INSERT INTO menu_items (parent_id, section, label, icon, route, permission_module, permission_action, sort_order, is_active, is_heading, badge)
                 VALUES (155, 'inventory_ccdc', 'Xử lý & Báo cáo', 'bi-gear', NULL, 'inventory', 'read', 89, 1, 1, NULL)");
    $xlId = (int)$pdo->lastInsertId();

    // Assign items → Danh mục
    foreach ([156, 157, 158, 159, 236] as $id) {
        $pdo->prepare("UPDATE menu_items SET parent_id = ? WHERE id = ?")->execute([$dmId, $id]);
    }
    // Assign items → Nhập liệu
    foreach ([160, 161, 230, 231, 232, 233, 234, 222] as $id) {
        $pdo->prepare("UPDATE menu_items SET parent_id = ? WHERE id = ?")->execute([$nlId, $id]);
    }
    // Assign items → Xử lý & Báo cáo
    foreach ([162, 163, 223, 235] as $id) {
        $pdo->prepare("UPDATE menu_items SET parent_id = ? WHERE id = ?")->execute([$xlId, $id]);
    }

    // ================================================================
    // 2. GL_REPORT — 3 sub-menus (21 items)
    // Section heading id=202
    // ================================================================

    // Sub-heading: Nhập liệu
    $pdo->exec("INSERT INTO menu_items (parent_id, section, label, icon, route, permission_module, permission_action, sort_order, is_active, is_heading, badge)
                 VALUES (202, 'gl_report', 'Nhập liệu', 'bi-pencil-square', NULL, 'journal', 'create', 181, 1, 1, NULL)");
    $glNlId = (int)$pdo->lastInsertId();

    // Sub-heading: Xử lý cuối kỳ
    $pdo->exec("INSERT INTO menu_items (parent_id, section, label, icon, route, permission_module, permission_action, sort_order, is_active, is_heading, badge)
                 VALUES (202, 'gl_report', 'Xử lý cuối kỳ', 'bi-arrow-repeat', NULL, 'journal', 'post', 184, 1, 1, NULL)");
    $glXlId = (int)$pdo->lastInsertId();

    // Sub-heading: Báo cáo & Phân tích
    $pdo->exec("INSERT INTO menu_items (parent_id, section, label, icon, route, permission_module, permission_action, sort_order, is_active, is_heading, badge)
                 VALUES (202, 'gl_report', 'Báo cáo & Phân tích', 'bi-bar-chart', NULL, 'report', 'read', 188, 1, 1, NULL)");
    $glBcId = (int)$pdo->lastInsertId();

    // Assign items → Nhập liệu
    foreach ([203, 204, 224, 225] as $id) {
        $pdo->prepare("UPDATE menu_items SET parent_id = ? WHERE id = ?")->execute([$glNlId, $id]);
    }
    // Assign items → Xử lý cuối kỳ
    foreach ([205, 206, 245, 246, 248, 247] as $id) {
        $pdo->prepare("UPDATE menu_items SET parent_id = ? WHERE id = ?")->execute([$glXlId, $id]);
    }
    // Assign items → Báo cáo & Phân tích
    foreach ([207, 208, 209, 210, 239, 240, 241, 242, 244, 243, 226] as $id) {
        $pdo->prepare("UPDATE menu_items SET parent_id = ? WHERE id = ?")->execute([$glBcId, $id]);
    }

    // ================================================================
    // 3. CASH_BANK — 2 sub-menus (12 items)
    // Section heading id=129
    // ================================================================

    // Sub-heading: Giao dịch
    $pdo->exec("INSERT INTO menu_items (parent_id, section, label, icon, route, permission_module, permission_action, sort_order, is_active, is_heading, badge)
                 VALUES (129, 'cash_bank', 'Giao dịch', 'bi-cash-coin', NULL, 'cash', 'create', 21, 1, 1, NULL)");
    $cbGdId = (int)$pdo->lastInsertId();

    // Sub-heading: Báo cáo & Đối chiếu
    $pdo->exec("INSERT INTO menu_items (parent_id, section, label, icon, route, permission_module, permission_action, sort_order, is_active, is_heading, badge)
                 VALUES (129, 'cash_bank', 'Báo cáo & Đối chiếu', 'bi-file-earmark-bar-graph', NULL, 'cash', 'read', 26, 1, 1, NULL)");
    $cbBcId = (int)$pdo->lastInsertId();

    // Assign items → Giao dịch
    foreach ([130, 131, 132, 133, 134, 220, 227, 228] as $id) {
        $pdo->prepare("UPDATE menu_items SET parent_id = ? WHERE id = ?")->execute([$cbGdId, $id]);
    }
    // Assign items → Báo cáo & Đối chiếu
    foreach ([135, 136, 137, 221] as $id) {
        $pdo->prepare("UPDATE menu_items SET parent_id = ? WHERE id = ?")->execute([$cbBcId, $id]);
    }
};

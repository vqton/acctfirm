<?php
//
// RBAC cho print module (R-10 Print Designer)
//
return function (PDO $pdo) {
    // Quản trị dữ liệu + Kế toán trưởng: full quyền print
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_post, can_print)
                SELECT id, 'print', 1, 0, 1, 0, 0, 1 FROM roles WHERE name IN ('Quản trị dữ liệu', 'Kế toán trưởng')");
    // Kế toán các loại + Kiểm toán + Lãnh đạo: view + edit
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_post, can_print)
                SELECT id, 'print', 1, 0, 1, 0, 0, 0 FROM roles WHERE name IN ('Kế toán bán hàng', 'Kế toán kho', 'Kế toán mua hàng', 'Kế toán thuế', 'Kế toán vốn bằng tiền', 'Kiểm toán', 'Lãnh đạo')");
};

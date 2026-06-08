<?php
// NGHIỆP VỤ: Menu điều hướng động (Dynamic Navigation Menu)
//
// Bối cảnh: Sidebar trước đây hardcode trong layout.php ~270 dòng HTML.
// Không thể phân quyền theo role, không tùy chỉnh, maintain khó.
//
// Migration này tạo bảng menu_items để lưu toàn bộ cấu trúc menu.
// Menu được sắp xếp theo workflow nghiệp vụ (user journey), không theo module kỹ thuật.
//
// Quyết định thiết kế:
//   - Mỗi menu item có section (nhóm cấp 1), parent_id (cấp 2+), sort_order
//   - RBAC qua permission_module + permission_action (null = public)
//   - is_heading = 1 cho section header (không có link)
//   - badge cho thông báo tĩnh (e.g. "NEW", "BETA")
//   - route có thể null cho heading/section không có link
//
// RỦI RO:
//   - Menu items lưu DB → query mỗi request → cần cache hoặc index
//   - Seed data thiếu item → user không thấy chức năng → gọi support
//   - Sai permission → user thấy menu không được phép vào → 403
//
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parent_id INT UNSIGNED NULL COMMENT 'FK to parent menu item (self-ref)',
        section VARCHAR(50) NOT NULL COMMENT 'Logical group key (e.g. cash, ap, inventory)',
        label VARCHAR(100) NOT NULL COMMENT 'Display text (Tiếng Việt)',
        icon VARCHAR(50) NULL COMMENT 'Bootstrap icon class (e.g. bi-cash)',
        route VARCHAR(255) NULL COMMENT 'URL path (null for heading-only)',
        permission_module VARCHAR(50) NULL COMMENT 'RBAC module (null = public)',
        permission_action VARCHAR(50) NULL COMMENT 'RBAC action (null = any)',
        sort_order INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Sort within section',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        is_heading TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = section header, no click',
        badge VARCHAR(50) NULL COMMENT 'Static badge text (e.g. NEW)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_section (section),
        INDEX idx_parent (parent_id),
        INDEX idx_active_sort (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};

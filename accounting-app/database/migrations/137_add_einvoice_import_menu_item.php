<?php
return function (PDO $pdo) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO menu_items (parent_id, section, label, icon, route, permission_module, permission_action, sort_order, is_active, is_heading, badge)
                           VALUES (?, 'tax', ?, ?, ?, ?, ?, ?, 1, 0, NULL)");
    $stmt->execute([null, 'Lịch sử import HĐĐT', 'bi-clock-history', '/hoa-don-dien-tu/import-history', 'einvoice', 'read', 169]);
};

<?php
// Dọn dẹp menu dashboard bị trùng do migration 119 thiếu 'dashboard' trong danh sách deactivate
// Migration 119 đã seed lại mục dashboard nhưng chưa tắt mục cũ từ migration 112
// Kết quả: 2 mục dashboard active → render ra 2 link trùng nhau
return function (PDO $pdo) {
    // Giữ lại mục dashboard có id cao nhất (mới nhất), tắt các mục cũ
    $pdo->exec("
        UPDATE menu_items SET is_active = 0
        WHERE section = 'dashboard' AND is_active = 1
        AND id != (SELECT max_id FROM (SELECT MAX(id) AS max_id FROM menu_items WHERE section = 'dashboard') AS t)
    ");
};

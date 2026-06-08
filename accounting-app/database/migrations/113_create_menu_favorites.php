<?php
// NGHIỆP VỤ: Bảng yêu thích menu — cho phép user ghim menu thường dùng
// RỦI RO: User thích quá nhiều → mất tác dụng. Giới hạn 20 items.
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_favorites (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL COMMENT 'FK to users',
        menu_item_id INT UNSIGNED NOT NULL COMMENT 'FK to menu_items',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_menu (user_id, menu_item_id),
        INDEX idx_user (user_id),
        CONSTRAINT fk_mf_menu FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};

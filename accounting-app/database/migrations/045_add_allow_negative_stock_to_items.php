<?php
// Thêm cột allow_negative_stock vào items — cho phép tồn kho âm
return function (PDO $pdo) {
    $r = $pdo->query("SHOW COLUMNS FROM items LIKE 'allow_negative_stock'");
    if (!$r->fetch()) {
        $pdo->exec("ALTER TABLE items
            ADD COLUMN allow_negative_stock TINYINT(1) DEFAULT 0 AFTER valuation_method_id");
    }
};
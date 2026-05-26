<?php
// Thêm cột valuation_method vào items — gắn phương pháp tính giá cho từng mặt hàng
return function (PDO $pdo) {
    $r = $pdo->query("SHOW COLUMNS FROM items LIKE 'valuation_method_id'");
    if (!$r->fetch()) {
        $pdo->exec('ALTER TABLE items
            ADD COLUMN valuation_method_id VARCHAR(50) DEFAULT NULL AFTER min_stock,
            ADD INDEX idx_valuation (valuation_method_id)');
    }
};

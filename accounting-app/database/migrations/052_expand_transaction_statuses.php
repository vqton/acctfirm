<?php
// Mở rộng trạng thái chứng từ — thêm submitted/approved/rejected
return function (PDO $pdo) {
    $pdo->exec("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending','submitted','approved','rejected','posted','reversed') NOT NULL DEFAULT 'pending'");
};

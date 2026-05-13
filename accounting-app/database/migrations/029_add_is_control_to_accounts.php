<?php
return function (PDO $pdo) {
    $res = $pdo->query("SHOW COLUMNS FROM accounts LIKE 'is_control'");
    if (!$res->fetch()) {
        $pdo->exec("ALTER TABLE accounts ADD COLUMN is_control TINYINT(1) DEFAULT 0 AFTER status");
    }
};

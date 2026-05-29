<?php
return function (PDO $pdo) {
    $pdo->exec("ALTER TABLE ccdc MODIFY COLUMN allocation_type ENUM('once','installment','period','direct') NOT NULL DEFAULT 'period'");
};

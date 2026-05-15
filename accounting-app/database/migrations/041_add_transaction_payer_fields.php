<?php
return function (PDO $pdo) {
    $pdo->exec('ALTER TABLE transactions
        ADD COLUMN transaction_date DATE DEFAULT NULL AFTER `date`,
        ADD COLUMN payer_name VARCHAR(200) DEFAULT NULL AFTER `reference`,
        ADD COLUMN payer_type ENUM("customer","supplier","employee","other") DEFAULT NULL AFTER `payer_name`,
        ADD COLUMN payer_id VARCHAR(50) DEFAULT NULL AFTER `payer_type`,
        ADD INDEX idx_payer (payer_type, payer_id)');
};

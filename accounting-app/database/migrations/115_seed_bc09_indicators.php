<?php
// Seed BC09 indicators (Mẫu B09-DN — Thuyết minh BCTC) theo TT 99/2025/TT-BTC.
// Sử dụng seed_bc09_indicators.php function để đảm bảo idempotent (INSERT IGNORE).
return function (PDO $pdo) {
    require_once __DIR__ . '/../seed_bc09_indicators.php';
    $result = seedBc09Indicators($pdo);
};

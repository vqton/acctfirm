<?php
//
// NGHIỆP VỤ: RBAC scope theo created_by + entity_id
//
// Bối cảnh: Trước đây, mọi user thấy tất cả transactions (sau khi pass RBAC module check).
// R-3: Kế toán viên chỉ thấy data do mình tạo (created_by = current user).
//      KTT + Admin thấy tất cả.
//
// Configurable: bật/tắt qua business_config rbac.scope_by_creator
// Backward compatible: mặc định false (giữ hành vi cũ) — admin có thể bật cho SME
//
return function (PDO $pdo) {
    $r = $pdo->query("SHOW COLUMNS FROM business_config LIKE 'config_key'");
    if (!$r->fetch()) return;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM business_config WHERE config_key = ?");
    $stmt->execute(['rbac.scope_by_creator']);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO business_config (config_key, config_value, config_type, description)
            VALUES ('rbac.scope_by_creator', 'false', 'boolean',
                    'Kế toán viên chỉ thấy data mình tạo (SME pattern)')");
    }

    $stmt->execute(['rbac.entity_filter_enabled']);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO business_config (config_key, config_value, config_type, description)
            VALUES ('rbac.entity_filter_enabled', 'true', 'boolean',
                    'Tự động filter query theo entity_id hiện tại (multi-tenant)')");
    }
};

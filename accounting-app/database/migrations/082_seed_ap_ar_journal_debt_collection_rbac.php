<?php
return function (PDO $pdo) {
    $modules = ['ap', 'ar', 'journal', 'debt_collection'];

    // Admin: full permissions
    $adminPerm = $pdo->prepare(
        'INSERT IGNORE INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_post, can_print)
         VALUES (?, ?, 1, 1, 1, 1, 1, 1)'
    );
    foreach ($modules as $m) $adminPerm->execute(['admin', $m]);

    // Ke toan truong: full permissions
    $ktPerm = $pdo->prepare(
        'INSERT IGNORE INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_post, can_print)
         VALUES (?, ?, 1, 1, 1, 1, 1, 1)'
    );
    foreach ($modules as $m) $ktPerm->execute(['ke_toan_truong', $m]);

    // kt_tien: view ap, ar, debt_collection
    $viewPerm = $pdo->prepare(
        'INSERT IGNORE INTO role_permissions (role_id, module, can_view) VALUES (?, ?, 1)'
    );
    foreach (['ap', 'ar', 'debt_collection'] as $m) $viewPerm->execute(['kt_tien', $m]);

    // kt_mua: access ap (mua hang), master_data ap, ar view
    $ktMuaPerm = $pdo->prepare(
        'INSERT IGNORE INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_post, can_print)
         VALUES (?, ?, 1, 1, 1, 1, 1, 1)'
    );
    $ktMuaPerm->execute(['kt_mua', 'ap']);
    $viewPerm->execute(['kt_mua', 'ar']);
    $viewPerm->execute(['kt_mua', 'debt_collection']);

    // kt_ban: access ar (ban hang), view ap
    $ktBanPerm = $pdo->prepare(
        'INSERT IGNORE INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_post, can_print)
         VALUES (?, ?, 1, 1, 1, 1, 1, 1)'
    );
    $ktBanPerm->execute(['kt_ban', 'ar']);
    $viewPerm->execute(['kt_ban', 'ap']);
    $viewPerm->execute(['kt_ban', 'debt_collection']);

    // kt_kho: view ap, ar, debt_collection
    foreach (['ap', 'ar', 'debt_collection'] as $m) $viewPerm->execute(['kt_kho', $m]);

    // kt_thue: view ap, ar (can xem cong no de ke khai thue)
    foreach (['ap', 'ar'] as $m) $viewPerm->execute(['kt_thue', $m]);

    // lanh_dao + kiem_toan: view all
    foreach (['lanh_dao', 'kiem_toan'] as $roleId) {
        foreach ($modules as $m) $viewPerm->execute([$roleId, $m]);
    }
};

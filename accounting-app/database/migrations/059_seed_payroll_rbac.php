<?php
// NGHIEP VU: Phan quyen module Tien luong cho cac vai tro
//
// Module 'payroll' duoc them vao role_permissions cho:
//   - admin: toan quyen
//   - ke_toan_truong: toan quyen
//   - kt_tien: xem, tao, sua (vi luong lien quan chi tra)
//   - lanh_dao: xem (bao cao)
//   - kiem_toan: xem (kiem tra)
//
// Cac action: view, create, edit, delete, post, print
return function (PDO $pdo) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM role_permissions WHERE module = ?');
    $stmt->execute(['payroll']);
    if ((int)$stmt->fetchColumn() > 0) { echo "[SKIP] payroll RBAC already seeded\n"; return; }

    $roles = [
        ['admin', 1, 1, 1, 1, 1, 1],
        ['ke_toan_truong', 1, 1, 1, 1, 1, 1],
        ['kt_tien', 1, 1, 1, 1, 0, 0],
        ['kt_thue', 1, 0, 0, 0, 0, 0],
        ['lanh_dao', 1, 0, 0, 0, 0, 0],
        ['kiem_toan', 1, 0, 0, 0, 0, 0],
    ];

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_post, can_print)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($roles as $r) $insert->execute([$r[0], 'payroll', $r[1], $r[2], $r[3], $r[4], $r[5], $r[6]]);
    echo "[OK] payroll RBAC seeded\n";
};

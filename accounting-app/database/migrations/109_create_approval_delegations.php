<?php
//
// R-17: Approval Delegation / Proxy
//
// Nghiệp vụ: Khi KTT đi vắng (công tác, nghỉ phép), người được ủy quyền
// có thể duyệt thay. Phải:
//   - Có thời hạn rõ ràng (start_date, end_date)
//   - Có lý do (reason) cho audit
//   - Audit trail vẫn ghi NGƯỜI DUYỆT THỰC TẾ (delegate) và NGƯỜI ỦY QUYỀN (delegator)
//
// Thiết kế:
//   - approval_delegations: delegator (người ủy quyền) → delegate (người được ủy quyền)
//   - Mỗi delegation gắn với 1 role (vd: delegator là chief_accountant ủy cho accountant)
//   - Effective khi: is_active=1 AND NOW() BETWEEN start_date AND end_date
//
// Tích hợp:
//   - ApprovalController.approve: nếu user không có role phù hợp → check delegation
//   - Nếu có delegation hợp lệ → cho phép approve, audit ghi cả 2 user
//   - Notify cả delegator lẫn delegate
//
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS approval_delegations (
        id VARCHAR(20) PRIMARY KEY,
        delegator_id VARCHAR(100) NOT NULL COMMENT 'User ủy quyền',
        delegate_id VARCHAR(100) NOT NULL COMMENT 'User được ủy quyền',
        role VARCHAR(50) NOT NULL COMMENT 'Role được ủy quyền (vd: chief_accountant)',
        start_date DATETIME NOT NULL,
        end_date DATETIME NOT NULL,
        reason VARCHAR(500) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        revoked_at DATETIME NULL,
        revoked_by VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_delegator (delegator_id, is_active, start_date, end_date),
        INDEX idx_delegate (delegate_id, is_active, start_date, end_date),
        INDEX idx_role (role, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT 'Ủy quyền phê duyệt'");
};

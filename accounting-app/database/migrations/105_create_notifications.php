<?php
//
// NGHIỆP VỤ: Hệ thống thông báo trong ứng dụng (R-12 In-App Notifications)
//
// Mục đích: Kế toán/kiểm toán viên nhận thông báo real-time khi có sự kiện:
//   - Có bút toán mới cần duyệt (KTT thấy ngay khi kế toán viên submit)
//   - Bút toán được duyệt/từ chối (người tạo biết kết quả)
//   - Kỳ kế toán sắp đến deadline (cảnh báo sớm 7 ngày)
//   - Kỳ kế toán đã đóng
//   - Import dữ liệu hoàn tất / thất bại
//   - Lỗi posting rule / period lock
//
// Đặc tính:
//   - Bất biến (không sửa message sau khi tạo — chỉ mark read)
//   - Mỗi user có hàng đợi riêng (user_id)
//   - Có thể có link tới resource (vd /journal/edit/{id})
//   - Có thể broadcast cho nhiều user (target_user_id IS NULL = broadcast)
//
// Rủi ro:
//   - Quá nhiều thông báo → user bỏ qua → dùng severity (info/warn/critical)
//   - Notification spam (loop) → debounce bằng unique (type + resource_id + user_id)
//   - Xóa notification → mất audit → KHÔNG cho xóa, chỉ mark read
//
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id VARCHAR(20) PRIMARY KEY,
        user_id VARCHAR(50) NULL COMMENT 'NULL = broadcast to all users',
        type VARCHAR(50) NOT NULL COMMENT 'journal.pending_approval, period.deadline_soon, import.completed, ...',
        severity VARCHAR(20) NOT NULL DEFAULT 'info' COMMENT 'info/warn/critical',
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        resource_type VARCHAR(50) NULL,
        resource_id VARCHAR(50) NULL,
        link VARCHAR(255) NULL COMMENT 'URL tới resource (vd /journal/edit/{id})',
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        read_at TIMESTAMP NULL,
        created_by VARCHAR(100) NULL COMMENT 'user/actor gây ra thông báo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_unread (user_id, is_read, created_at),
        INDEX idx_type_resource (type, resource_type, resource_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};

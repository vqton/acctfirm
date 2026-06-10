<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\NotificationService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Thông báo (Notifications)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý thông báo nội bộ cho người dùng
 *   - Cảnh báo: bút toán chờ duyệt, công nợ quá hạn, kỳ sắp đóng
 *   - Theo dõi trạng thái đã đọc/chưa đọc
 *
 * API endpoints:
 *   GET  /api/notifications — Danh sách thông báo
 *   POST /api/notifications/{id}/read — Đánh dấu đã đọc
 *   GET  /api/notifications/unread-count — Số thông báo chưa đọc
 *
 * Rủi ro:
 *   - Bỏ sót thông báo quan trọng (kỳ sắp đóng, duyệt gấp)
 *
 * Tích hợp:
 *   - NotificationService đọc từ bảng notifications
 *   - Các service module tạo notification khi có sự kiện
 */
class NotificationController
{
    private NotificationService $notifications;

    public function __construct(NotificationService $notifications) { $this->notifications = $notifications; }

    /**
     * Danh sách thông báo
     *
     * @return void
     */
    public function list(): void
    {
        $userId = Auth::getCurrentUserId();
        $limit = (int)($_GET['limit'] ?? 50);
        JsonResponse::ok($this->notifications->getNotifications($userId, $limit));
    }

    /**
     * Đánh dấu thông báo đã đọc
     *
     * @param string $id ID thông báo
     * @return void
     */
    public function markRead(string $id): void
    {
        $userId = Auth::getCurrentUserId();
        $this->notifications->markAsRead($id, $userId);
        JsonResponse::ok(['message' => 'Đã đánh dấu đã đọc']);
    }

    /**
     * Số lượng thông báo chưa đọc
     *
     * @return void
     */
    public function unreadCount(): void
    {
        $userId = Auth::getCurrentUserId();
        JsonResponse::ok(['count' => $this->notifications->getUnreadCount($userId)]);
    }
}

<?php
//
// NOTIFICATION CONTROLLER — R-12 In-App Notifications
//
// Endpoints:
//   GET  /api/notifications           — list (query: ?unread=1, ?limit=50)
//   GET  /api/notifications/unread    — count unread
//   POST /api/notifications/{id}/read — mark 1 đã đọc
//   POST /api/notifications/read-all  — mark tất cả đã đọc
//
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\NotificationService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class NotificationController
{
    private NotificationService $svc;

    public function __construct(NotificationService $svc)
    {
        $this->svc = $svc;
    }

    public function list(): void
    {
        Auth::requirePermission('report', 'read');
        $userId = Auth::getCurrentUserId() ?? 'system';
        $unreadOnly = ($_GET['unread'] ?? '') === '1';
        $limit = (int)($_GET['limit'] ?? 50);
        if ($limit < 1 || $limit > 200) $limit = 50;

        $items = $this->svc->listForUser($userId, $limit, $unreadOnly);
        JsonResponse::ok([
            'items' => $items,
            'unread_count' => $this->svc->countUnread($userId),
        ]);
    }

    public function unreadCount(): void
    {
        Auth::requirePermission('report', 'read');
        $userId = Auth::getCurrentUserId() ?? 'system';
        JsonResponse::ok(['unread_count' => $this->svc->countUnread($userId)]);
    }

    public function markRead(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('report', 'read');
        $userId = Auth::getCurrentUserId() ?? 'system';
        $ok = $this->svc->markRead($id, $userId);
        JsonResponse::ok(['marked' => $ok]);
    }

    public function markAllRead(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('report', 'read');
        $userId = Auth::getCurrentUserId() ?? 'system';
        $count = $this->svc->markAllRead($userId);
        JsonResponse::ok(['marked_count' => $count]);
    }
}

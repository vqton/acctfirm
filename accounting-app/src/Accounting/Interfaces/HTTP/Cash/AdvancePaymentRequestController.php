<?php
namespace Accounting\Interfaces\HTTP\Cash;

use Accounting\Domain\Service\AdvancePaymentRequestService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Giấy đề nghị tạm ứng — Mẫu số 03-TT (TT 99)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý đề nghị tạm ứng: draft → submitted → approved → paid → cancelled
 *   - Tích hợp PettyCashService khi lập phiếu chi
 *
 * API endpoints:
 *   POST /api/advance-payment/draft — Tạo đề nghị
 *   POST /api/advance-payment/{id}/submit — Gửi duyệt
 *   POST /api/advance-payment/{id}/approve — Duyệt
 *   POST /api/advance-payment/{id}/reject — Từ chối
 *   POST /api/advance-payment/{id}/cancel — Hủy
 *   POST /api/advance-payment/{id}/paid — Đã chi
 *   POST /api/advance-payment/{id}/settle — Hoàn ứng
 *   GET  /api/advance-payment/{id} — Chi tiết
 *   GET  /api/advance-payment/list — Danh sách
 *
 * Tích hợp:
 *   - PettyCashController::disburseFromRequest()
 *   - PettyCashService
 */
class AdvancePaymentRequestController
{
    private AdvancePaymentRequestService $service;

    public function __construct(AdvancePaymentRequestService $service)
    {
        $this->service = $service;
    }

    /**
     * Tạo đề nghị tạm ứng mới (draft)
     *
     * @return void
     */
    public function createDraft(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('cash', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['requester_name'], $data['amount'])) {
            JsonResponse::error('Vui lòng nhập họ tên người đề nghị và số tiền tạm ứng', 400);
            return;
        }
        $data['created_by'] = $_SESSION['user_id'] ?? 'system';
        try {
            $result = $this->service->createDraft($data);
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Gửi duyệt đề nghị
     *
     * @param string $id ID đề nghị
     * @return void
     */
    public function submit(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('cash', 'create');
        try {
            $result = $this->service->submitRequest($id, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Duyệt đề nghị
     *
     * @param string $id ID đề nghị
     * @return void
     */
    public function approve(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('cash', 'post');
        try {
            $result = $this->service->approveRequest($id, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Từ chối đề nghị
     *
     * @param string $id ID đề nghị
     * @return void
     */
    public function reject(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('cash', 'post');
        $data = json_decode(file_get_contents('php://input'), true);
        $reason = $data['reason'] ?? null;
        try {
            $result = $this->service->rejectRequest($id, $_SESSION['user_id'] ?? 'system', $reason);
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Hủy đề nghị (chỉ draft)
     *
     * @param string $id ID đề nghị
     * @return void
     */
    public function cancel(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('cash', 'create');
        try {
            $result = $this->service->cancelDraft($id, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Đánh dấu đã chi
     *
     * @param string $id ID đề nghị
     * @return void
     */
    public function markPaid(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('cash', 'post');
        try {
            $result = $this->service->markAsPaid($id, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Chi tiết đề nghị
     *
     * @param string $id ID đề nghị
     * @return void
     */
    public function getDetail(string $id): void
    {
        Auth::requirePermission('cash', 'read');
        try {
            $result = $this->service->getRequest($id);
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Danh sách đề nghị
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('cash', 'read');
        $status = $_GET['status'] ?? null;
        $limit = (int)($_GET['limit'] ?? 50);
        JsonResponse::ok($this->service->listRequests($status, $limit));
    }

    /**
     * Hoàn ứng tạm ứng — nhân viên nộp lại tiền + chi phí
     *
     * @param string $id ID đề nghị
     * @return void
     */
    public function settle(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('cash', 'post');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['cash_returned'])) {
            JsonResponse::error('Vui lòng nhập số tiền hoàn ứng', 400);
            return;
        }
        try {
            $result = $this->service->settle(
                $id,
                (float)$data['cash_returned'],
                $data['expense_lines'] ?? [],
                $_SESSION['user_id'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }
    }

    /**
     * View HTML
     *
     * @return void
     */
    public function viewIndex(): void
    {
        require __DIR__ . '/../../../../public/views/advance_payment_request.php';
    }
}

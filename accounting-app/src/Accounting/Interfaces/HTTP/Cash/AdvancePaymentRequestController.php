<?php
namespace Accounting\Interfaces\HTTP\Cash;

use Accounting\Domain\Service\AdvancePaymentRequestService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Giấy đề nghị tạm ứng — Mẫu số 03-TT theo Thông tư 99
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý giấy đề nghị tạm ứng của nhân viên
 *   - Lifecycle: draft → submitted → approved → paid → cancelled
 *   - Tích hợp với PettyCashService/PettyCashController khi lập phiếu chi
 *
 * API endpoints:
 *   POST /api/advance-payment/draft       — Tạo đề nghị mới (draft)
 *   POST /api/advance-payment/{id}/submit — Gửi duyệt
 *   POST /api/advance-payment/{id}/approve — Duyệt đề nghị
 *   POST /api/advance-payment/{id}/reject  — Từ chối
 *   POST /api/advance-payment/{id}/cancel  — Hủy (chỉ draft)
 *   POST /api/advance-payment/{id}/paid    — Đánh dấu đã chi
 *   GET  /api/advance-payment/{id}         — Chi tiết
 *   GET  /api/advance-payment/list         — Danh sách
 *
 * Rủi ro:
 *   - Chi vượt quá số tiền đã duyệt → mất kiểm soát
 *   - Đề nghị không được duyệt nhưng vẫn chi → sai quy trình
 *   - Mất audit trail nếu không log đầy đủ
 */
class AdvancePaymentRequestController
{
    private AdvancePaymentRequestService $service;

    public function __construct(AdvancePaymentRequestService $service)
    {
        $this->service = $service;
    }

    // TẠO ĐỀ NGHỊ MỚI (draft)
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

    // GỬI DUYỆT
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

    // DUYỆT
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

    // TỪ CHỐI
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

    // HỦY (chỉ draft)
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

    // ĐÁNH DẤU ĐÃ CHI
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

    // CHI TIẾT
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

    // DANH SÁCH
    public function list(): void
    {
        Auth::requirePermission('cash', 'read');
        $status = $_GET['status'] ?? null;
        $limit = (int)($_GET['limit'] ?? 50);
        JsonResponse::ok($this->service->listRequests($status, $limit));
    }

    // HOAN UNG TAM UNG
    // NGHIEP VU: Nhan vien hoan lai tien tam ung chua dung + chi phi
    // Input: { cash_returned, expense_lines: [{account_code, amount}], created_by? }
    // Output: { transaction_id, cash_returned, expense_amount, status }
    // Hach toan: No 111 + No TK chi phi / Co 141
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

    // VIEW
    public function viewIndex(): void
    {
        require __DIR__ . '/../../../../public/views/advance_payment_request.php';
    }
}

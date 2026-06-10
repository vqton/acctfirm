<?php
namespace Accounting\Interfaces\HTTP\Cash;

use Accounting\Domain\Service\PettyCashService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Tạm ứng (Petty Cash / Công quỹ)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý quỹ tạm ứng (imprest fund) — TK 1113
 *   - Thiết lập quỹ mới với hạn mức
 *   - Chi tạm ứng cho nhân viên
 *   - Hoàn ứng / thanh toán tạm ứng
 *   - Bổ sung quỹ
 *
 * API endpoints:
 *   GET  /api/petty-cash/funds — Danh sách quỹ
 *   POST /api/petty-cash/funds — Thiết lập quỹ mới
 *   POST /api/petty-cash/disburse — Chi tạm ứng
 *   POST /api/petty-cash/disburse-from-request — Chi từ đề nghị 03-TT
 *   POST /api/petty-cash/top-up — Bổ sung quỹ
 *   POST /api/petty-cash/funds/{id}/close — Đóng quỹ
 *   GET  /api/petty-cash/transactions/{id} — Lịch sử
 *
 * Tích hợp:
 *   - PettyCashService → CashService → JournalService
 *   - AdvancePaymentRequestController
 */
class PettyCashController
{
    private PettyCashService $pettyCash;

    public function __construct(PettyCashService $pettyCash)
    {
        $this->pettyCash = $pettyCash;
    }

    /**
     * Danh sách quỹ tạm ứng
     *
     * @return void
     */
    public function funds(): void
    {
        JsonResponse::ok($this->pettyCash->getPettyCashFunds());
    }

    /**
     * Thiết lập quỹ tạm ứng mới
     *
     * @return void
     */
    public function createFund(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_name'], $data['imprest_amount'])) {
            JsonResponse::error('Vui lòng nhập tên quỹ và hạn mức tạm ứng', 400);
            return;
        }
        try {
            $result = $this->pettyCash->establishPettyCash(
                $data['fund_name'], (float)$data['imprest_amount'],
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Chi tạm ứng từ quỹ — Nợ 141 / Có 1113
     *
     * @return void
     */
    public function disburse(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_id'], $data['amount'])) {
            JsonResponse::error('Vui lòng nhập mã quỹ và số tiền', 400);
            return;
        }
        try {
            $result = $this->pettyCash->disbursePettyCash(
                $data['fund_id'], (float)$data['amount'],
                $data['description'] ?? 'Petty cash disbursement',
                $data['reference'] ?? uniqid('pc_'),
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Chi tiền từ đề nghị tạm ứng đã duyệt (Mẫu 03-TT)
     *
     * @return void
     */
    public function disburseFromRequest(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_id'], $data['request_id'], $data['request_number'], $data['amount'])) {
            JsonResponse::error('Vui lòng nhập mã quỹ, mã đề nghị, số chứng từ và số tiền', 400);
            return;
        }
        try {
            $result = $this->pettyCash->disburseFromRequest(
                $data['fund_id'], $data['request_id'], $data['request_number'],
                (float)$data['amount'],
                $data['description'] ?? 'Chi tạm ứng từ đề nghị ' . $data['request_number'],
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Bổ sung quỹ tạm ứng + ghi nhận chi phí thực tế
     *
     * @return void
     */
    public function replenish(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_id'], $data['expense_account'], $data['total_amount'])) {
            JsonResponse::error('Vui lòng nhập mã quỹ, tài khoản chi phí và tổng số tiền', 400);
            return;
        }
        try {
            $result = $this->pettyCash->replenishPettyCash(
                $data['fund_id'], $data['expense_account'], (float)$data['total_amount'],
                $data['description'] ?? 'Petty cash replenishment',
                $data['reference'] ?? uniqid('pc_'),
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Đóng quỹ tạm ứng — thu hồi số dư về quỹ chính
     *
     * @return void
     */
    public function closeFund(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_id'])) {
            JsonResponse::error('Vui lòng nhập mã quỹ', 400);
            return;
        }
        try {
            $result = $this->pettyCash->closePettyCash(
                $data['fund_id'], (float)($data['return_amount'] ?? 0),
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Lịch sử giao dịch quỹ
     *
     * @param string $fundId ID quỹ
     * @return void
     */
    public function transactions(string $fundId): void
    {
        JsonResponse::ok($this->pettyCash->getPettyCashTransactions($fundId));
    }
}

<?php
namespace Accounting\Interfaces\HTTP\Cash;

use Accounting\Domain\Service\PettyCashService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Tạm ứng (Petty Cash / Công quỹ)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý quỹ tạm ứng (imprest fund) — TK 1113 (hoặc theo dõi riêng)
 *   - Thiết lập quỹ mới với hạn mức (imprest amount)
 *   - Chi tạm ứng cho nhân viên (disburse)
 *   - Hoàn ứng / thanh toán tạm ứng (reimburse)
 *   - Nộp lại quỹ khi kết thúc (top-up / replenish)
 *
 * API endpoints:
 *   GET    /api/petty-cash/funds       — Danh sách quỹ tạm ứng
 *   POST   /api/petty-cash/funds       — Thiết lập quỹ mới
 *   POST   /api/petty-cash/disburse    — Chi tạm ứng
 *   POST   /api/petty-cash/reimburse   — Hoàn ứng
 *   POST   /api/petty-cash/top-up      — Bổ sung quỹ
 *   GET    /api/petty-cash/transactions — Lịch sử giao dịch quỹ
 *
 * Rủi ro:
 *   - R007: Chi tạm ứng nhưng không ghi nhận bút toán → mất cân đối
 *   - Hoàn ứng sai số tiền → chênh lệch quỹ
 *   - Quỹ tạm ứng không được đối chiếu định kỳ → thất thoát
 *
 * Tích hợp:
 *   - PettyCashService gọi CashService → JournalService cho mọi giao dịch
 *   - Theo dõi chi tiết theo nhân viên (employee_id)
 *   - Số dư quỹ là một phần của BC01 (khoản mục tiền mặt)
 */
class PettyCashController
{
    private PettyCashService $pettyCash;

    public function __construct(PettyCashService $pettyCash)
    {
        $this->pettyCash = $pettyCash;
    }

    public function funds(): void
    {
        JsonResponse::ok($this->pettyCash->getPettyCashFunds());
    }

    // NGHIỆP VỤ: Thiết lập quỹ tạm ứng mới (imprest fund) — cấp tiền cho quỹ
    // Input: { fund_name, imprest_amount, created_by? }
    // Output: { fund_id, status } — 201 Created
    // Service: PettyCashService.establishPettyCash() → CashService → JournalService
    // Hạch toán: Nợ 1113 (quỹ tạm ứng) / Có 1111 (tiền mặt chính)
    // Permission: CSRF check
    // Rủi ro: imprest_amount là hạn mức tối đa. Quỹ tạm ứng cần kiểm kê định kỳ
    public function createFund(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_name'], $data['imprest_amount'])) {
            JsonResponse::error('fund_name, imprest_amount required', 400);
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

    // NGHIỆP VỤ: Chi tạm ứng từ quỹ cho nhân viên — Nợ 141 / Có 1113
    // Input: { fund_id, amount, description?, reference?, created_by? }
    // Output: { transaction_id, reference, fund_balance_after } — 201 Created
    // Service: PettyCashService.disbursePettyCash() → CashService → JournalService
    // Permission: CSRF check
    // Hạch toán: Dr 141 (tạm ứng) / Cr 1113 (quỹ tạm ứng)
    // Rủi ro: Chi vượt quá hạn mức quỹ. Cần theo dõi nhân viên để thu hồi
    // Audit trail: Mọi khoản tạm ứng phải có chứng từ đầy đủ
    public function disburse(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_id'], $data['amount'])) {
            JsonResponse::error('fund_id, amount required', 400);
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

    // NGHIỆP VỤ: Bổ sung quỹ tạm ứng + ghi nhận chi phí thực tế
    // Input: { fund_id, expense_account, total_amount, description?, reference?, created_by? }
    // Output: { transaction_id, status }
    // Service: PettyCashService.replenishPettyCash() — kết hợp hoàn ứng và cấp bù
    // Hạch toán: Dr expense_account (642, 641, 627) / Cr 1111 (tiền mặt chính)
    // Rủi ro: expense_account phải đúng bản chất chi phí. Tổng chi phí kê khai phải = số tiền cấp bù
    // Quy trình: Nhân viên nộp chứng từ → kế toán kiểm tra → cấp bù quỹ
    public function replenish(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_id'], $data['expense_account'], $data['total_amount'])) {
            JsonResponse::error('fund_id, expense_account, total_amount required', 400);
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

    // NGHIỆP VỤ: Đóng quỹ tạm ứng — thu hồi số dư còn lại về quỹ chính
    // Input: { fund_id, return_amount?, created_by? }
    // Output: { transaction_id, returned_amount, status }
    // Service: PettyCashService.closePettyCash() — chuyển số dư còn lại về 1111
    // Hạch toán: Dr 1111 (số dư còn lại) / Cr 1113 (đóng quỹ)
    // Rủi ro: return_amount phải khớp với số dư quỹ thực tế. Nếu chênh lệch → ghi nhận chi phí/bồi thường
    // Quy trình: Kiểm kê quỹ → đối chiếu → thu hồi → đóng quỹ
    public function closeFund(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fund_id'])) {
            JsonResponse::error('fund_id required', 400);
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

    public function transactions(string $fundId): void
    {
        JsonResponse::ok($this->pettyCash->getPettyCashTransactions($fundId));
    }
}

<?php
namespace Accounting\Interfaces\HTTP\Cash;

use Accounting\Domain\Service\BankReconciliationService;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Đối chiếu Ngân hàng
 *
 * Mục đích nghiệp vụ:
 *   - Đối chiếu số dư sổ kế toán (TK 112) với sao kê ngân hàng
 *   - Tạo phiên đối chiếu (session) theo từng kỳ
 *   - Phát hiện chênh lệch: giao dịch chưa ghi nhận, phí ngân hàng, lãi
 *   - Tạo bút toán điều chỉnh tự động từ kết quả đối chiếu
 *
 * API endpoints:
 *   GET    /api/bank-recon/sessions       — Danh sách phiên đối chiếu
 *   POST   /api/bank-recon/sessions       — Tạo phiên mới
 *   GET    /api/bank-recon/sessions/{id}  — Chi tiết phiên
 *   POST   /api/bank-recon/sessions/{id}/match    — Khớp giao dịch
 *   POST   /api/bank-recon/sessions/{id}/adjust   — Tạo bút toán điều chỉnh
 *   POST   /api/bank-recon/sessions/{id}/close    — Đóng phiên
 *
 * Rủi ro:
 *   - R007: Chênh lệch không được xử lý → sai số dư ngân hàng
 *   - Điều chỉnh sai → ảnh hưởng BC01 (khoản mục tiền)
 *   - Trùng lặp giao dịch khi đối chiếu thủ công
 *
 * Tích hợp:
 *   - BankReconciliationService gọi JournalService cho bút toán điều chỉnh
 *   - CashController cập nhật số dư sau đối chiếu
 *   - Cần sao kê ngân hàng (statement) nhập từ bên ngoài
 */
class BankReconciliationController
{
    private BankReconciliationService $recon;
    private AccountRepositoryInterface $accountRepo;

    public function __construct(BankReconciliationService $recon, AccountRepositoryInterface $accountRepo)
    {
        $this->recon = $recon;
        $this->accountRepo = $accountRepo;
    }

    public function sessions(): void
    {
        JsonResponse::ok($this->recon->getSessions());
    }

    // NGHIỆP VỤ: Tạo phiên đối chiếu ngân hàng mới — so sánh sổ sách (112) với sao kê
    // Input: { bank_account_code, statement_date, statement_balance, created_by? }
    // Output: { session_id, book_balance, statement_balance, difference } — 201 Created
    // Service: BankReconciliationService.startSession() — lấy số dư sổ từ AccountRepository
    // Permission: CSRF check
    // Rủi ro: R007 — Chênh lệch giữa sổ và sao kê cần được xử lý trước khi đóng kỳ
    // Quy trình: Khởi tạo → nhập sao kê → match items → adjusting entries → complete
    public function startSession(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['bank_account_code'], $data['statement_date'], $data['statement_balance'])) {
            JsonResponse::error('bank_account_code, statement_date, statement_balance required', 400);
            return;
        }
        try {
            $result = $this->recon->startSession(
                $data['bank_account_code'], $data['statement_date'],
                (float)$data['statement_balance'],
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function getSession(int $id): void
    {
        try {
            JsonResponse::ok($this->recon->getSession($id));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    public function items(int $id): void
    {
        JsonResponse::ok($this->recon->getSessionItems($id));
    }

    public function unmatched(int $id): void
    {
        JsonResponse::ok($this->recon->getUnmatchedItems($id));
    }

    // NGHIỆP VỤ: Thêm dòng sao kê ngân hàng vào phiên đối chiếu
    // Input: { amount, type (debit|credit), description?, reference?, date? }
    // Output: { id } — 201 Created
    // Service: BankReconciliationService.addStatementEntry()
    // Rủi ro: Dữ liệu sao kê phải chính xác (nhập tay hoặc import file)
    // Sai sót trong sao kê → đối chiếu sai → chênh lệch kéo dài
    public function addStatementEntry(int $sessionId): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['type'])) {
            JsonResponse::error('amount, type required', 400);
            return;
        }
        try {
            $id = $this->recon->addStatementEntry(
                $sessionId, (float)$data['amount'],
                $data['description'] ?? '', $data['reference'] ?? '',
                $data['date'] ?? date('Y-m-d'), $data['type']
            );
            JsonResponse::ok(['id' => $id], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // NGHIỆP VỤ: Tự động khớp giao dịch sao kê với giao dịch sổ sách
    // Input: { statement_item_id, book_item_id }
    // Output: { matched: true }
    // Service: BankReconciliationService.autoMatch() — dùng reference, amount, date để match
    // Rủi ro: Auto match có thể sai nếu reference không chuẩn. Cần manual match verify sau
    public function autoMatch(int $sessionId): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['statement_item_id'], $data['book_item_id'])) {
            JsonResponse::error('statement_item_id, book_item_id required', 400);
            return;
        }
        try {
            $this->recon->manualMatch($sessionId, (int)$data['statement_item_id'], (int)$data['book_item_id']);
            JsonResponse::ok(['matched' => true]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // NGHIỆP VỤ: Khớp giao dịch thủ công — kế toán tự chọn cặp sao kê ↔ sổ sách
    // Input: { statement_item_id, book_item_id }
    // Output: { matched: true }
    // Service: BankReconciliationService.manualMatch()
    // Rủi ro: Khớp sai cặp giao dịch → chênh lệch không được phát hiện
    // Audit trail: Mọi manual match đều được ghi lại để kiểm toán
    public function manualMatch(int $sessionId): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['statement_item_id'], $data['book_item_id'])) {
            JsonResponse::error('statement_item_id, book_item_id required', 400);
            return;
        }
        try {
            $this->recon->manualMatch($sessionId, (int)$data['statement_item_id'], (int)$data['book_item_id']);
            JsonResponse::ok(['matched' => true]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // NGHIỆP VỤ: Tạo bút toán điều chỉnh từ chênh lệch đối chiếu ngân hàng
    // Input: { debit_account, credit_account, amount, description?, created_by? }
    // Output: { transaction_id, reference, status }
    // Service: BankReconciliationService.addAdjustingEntry() → JournalService.postEntry
    // Permission: CSRF check
    // Hạch toán: Điều chỉnh phí NH (6425/112), lãi NH (112/515), giao dịch thiếu sót
    // Rủi ro: R007 — Bút toán điều chỉnh sai → chênh lệch mới. Cần kiểm tra Dr = Cr
    // Ràng buộc: Chỉ tạo adjusting entry khi phiên đang mở, chưa complete
    public function addAdjustingEntry(int $sessionId): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['debit_account'], $data['credit_account'], $data['amount'])) {
            JsonResponse::error('debit_account, credit_account, amount required', 400);
            return;
        }
        try {
            $result = $this->recon->addAdjustingEntry(
                $sessionId, $data['debit_account'], $data['credit_account'],
                (float)$data['amount'], $data['description'] ?? 'Adjustment',
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // NGHIỆP VỤ: Đóng phiên đối chiếu ngân hàng — xác nhận số dư đã khớp
    // Input: none (sessionId from URL)
    // Output: { session_id, final_book_balance, final_statement_balance, status: 'completed' }
    // Service: BankReconciliationService.complete() — kiểm tra chênh lệch = 0 mới cho close
    // Permission: CSRF check
    // Rủi ro: R007 — Đóng phiên khi còn chênh lệch → sai số dư. Sau khi complete, không sửa được
    // Audit trail: Ghi lại số dư cuối và ngày giờ hoàn tất đối chiếu
    public function complete(int $sessionId): void
    {
        Auth::checkCsrf();
        try {
            JsonResponse::ok($this->recon->complete($sessionId));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function bankAccounts(): void
    {
        $all = $this->accountRepo->findAll();
        $bankAccounts = array_filter($all, fn($a) => str_starts_with($a->getCode(), '112'));
        JsonResponse::ok(array_map(fn($a) => [
            'code' => $a->getCode(), 'name' => $a->getName(),
            'balance' => $a->getBalance(),
        ], $bankAccounts));
    }
}

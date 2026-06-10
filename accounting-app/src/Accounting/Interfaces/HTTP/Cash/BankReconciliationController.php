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
 *   - Tạo phiên đối chiếu theo từng kỳ
 *   - Phát hiện chênh lệch, tạo bút toán điều chỉnh
 *
 * API endpoints:
 *   GET  /api/bank-recon/sessions — Danh sách phiên
 *   POST /api/bank-recon/sessions — Tạo phiên mới
 *   GET  /api/bank-recon/sessions/{id} — Chi tiết
 *   GET  /api/bank-recon/sessions/{id}/items — Items
 *   GET  /api/bank-recon/sessions/{id}/unmatched — Chưa khớp
 *   POST /api/bank-recon/sessions/{id}/statement-entry — Thêm sao kê
 *   POST /api/bank-recon/sessions/{id}/auto-match — Auto match
 *   POST /api/bank-recon/sessions/{id}/manual-match — Manual match
 *   POST /api/bank-recon/sessions/{id}/adjusting-entry — Tạo điều chỉnh
 *   POST /api/bank-recon/sessions/{id}/complete — Đóng phiên
 *   POST /api/bank-recon/sessions/{id}/import-csv — Import CSV
 *   GET  /api/bank-recon/bank-accounts — DS TK ngân hàng
 *
 * Tích hợp:
 *   - BankReconciliationService → JournalService
 *   - CashController
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

    /**
     * Danh sách phiên đối chiếu
     *
     * @return void
     */
    public function sessions(): void
    {
        JsonResponse::ok($this->recon->getSessions());
    }

    /**
     * Tạo phiên đối chiếu ngân hàng mới
     *
     * @return void
     */
    public function startSession(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['bank_account_code'], $data['statement_date'], $data['statement_balance'])) {
            JsonResponse::error('Vui lòng nhập mã tài khoản ngân hàng, ngày sao kê và số dư sao kê', 400);
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

    /**
     * Chi tiết phiên đối chiếu
     *
     * @param int $id ID phiên
     * @return void
     */
    public function getSession(int $id): void
    {
        try {
            JsonResponse::ok($this->recon->getSession($id));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Danh sách items trong phiên
     *
     * @param int $id ID phiên
     * @return void
     */
    public function items(int $id): void
    {
        JsonResponse::ok($this->recon->getSessionItems($id));
    }

    /**
     * Danh sách giao dịch chưa khớp
     *
     * @param int $id ID phiên
     * @return void
     */
    public function unmatched(int $id): void
    {
        JsonResponse::ok($this->recon->getUnmatchedItems($id));
    }

    /**
     * Thêm dòng sao kê ngân hàng vào phiên
     *
     * @param int $sessionId ID phiên
     * @return void
     */
    public function addStatementEntry(int $sessionId): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['type'])) {
            JsonResponse::error('Vui lòng nhập số tiền và loại giao dịch', 400);
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

    /**
     * Tự động khớp giao dịch sao kê với sổ sách
     *
     * @param int $sessionId ID phiên
     * @return void
     */
    public function autoMatch(int $sessionId): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['statement_item_id'], $data['book_item_id'])) {
            JsonResponse::error('Vui lòng nhập mã mục sao kê và mã mục sổ sách', 400);
            return;
        }
        try {
            $this->recon->manualMatch($sessionId, (int)$data['statement_item_id'], (int)$data['book_item_id']);
            JsonResponse::ok(['matched' => true]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Khớp giao dịch thủ công
     *
     * @param int $sessionId ID phiên
     * @return void
     */
    public function manualMatch(int $sessionId): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['statement_item_id'], $data['book_item_id'])) {
            JsonResponse::error('Vui lòng nhập mã mục sao kê và mã mục sổ sách', 400);
            return;
        }
        try {
            $this->recon->manualMatch($sessionId, (int)$data['statement_item_id'], (int)$data['book_item_id']);
            JsonResponse::ok(['matched' => true]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Tạo bút toán điều chỉnh từ chênh lệch đối chiếu
     *
     * @param int $sessionId ID phiên
     * @return void
     */
    public function addAdjustingEntry(int $sessionId): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['debit_account'], $data['credit_account'], $data['amount'])) {
            JsonResponse::error('Vui lòng nhập tài khoản Nợ, tài khoản Có và số tiền', 400);
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

    /**
     * Đóng phiên đối chiếu — xác nhận số dư đã khớp
     *
     * @param int $sessionId ID phiên
     * @return void
     */
    public function complete(int $sessionId): void
    {
        Auth::checkCsrf();
        try {
            JsonResponse::ok($this->recon->complete($sessionId));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Import sao kê ngân hàng từ file CSV
     *
     * @param int $sessionId ID phiên
     * @return void
     */
    public function importCsv(int $sessionId): void
    {
        Auth::checkCsrf();
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            JsonResponse::error('Vui lòng chọn file CSV', 400);
            return;
        }
        $content = file_get_contents($_FILES['file']['tmp_name']);
        if ($content === false || trim($content) === '') {
            JsonResponse::error('File rỗng hoặc không đọc được', 400);
            return;
        }
        try {
            $result = $this->recon->importStatementCsv(
                $sessionId, $content,
                $_SESSION['user_id'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Danh sách tài khoản ngân hàng (112)
     *
     * @return void
     */
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

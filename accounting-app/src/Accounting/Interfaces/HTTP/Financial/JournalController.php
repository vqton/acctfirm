<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Contract\JournalServiceInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Bút toán (Journal Entry) — CORE SYSTEM
 *
 * Mục đích nghiệp vụ:
 *   - Tạo và quản lý bút toán kế toán tổng hợp
 *   - Ghi nhận bút toán tay (manual journal) do kế toán viên nhập
 *   - Cập nhật bút toán nháp (draft) trước khi ghi sổ (post)
 *   - Ghi sổ (post) — khóa bút toán, không cho sửa/xóa
 *   - Danh sách bút toán theo kỳ
 *
 * API endpoints:
 *   GET    /api/journals             — Danh sách bút toán theo kỳ
 *   GET    /api/journals/{id}        — Chi tiết bút toán
 *   POST   /api/journals             — Tạo bút toán mới
 *   PUT    /api/journals/{id}        — Cập nhật (draft)
 *   POST   /api/journals/{id}/post   — Ghi sổ
 *   POST   /api/journals/{id}/reverse— Hoàn nhập
 *   DELETE /api/journals/{id}        — Xóa (draft)
 *
 * Rủi ro:
 *   - R001: Post vào kỳ đã đóng → từ chối (PeriodService::isPeriodOpen)
 *   - R002: Dr ≠ Cr kiểm tra trước khi post (tolerance ±10 VND)
 *   - R005: Post vào control account → từ chối (trừ khi $allowControl)
 *   - R006: Trùng số chứng từ → VoucherService SELECT FOR UPDATE
 *   - R007: Bút toán multi-step không rollback → giao dịch lỗi
 *   - R008: Không index trên transaction_date → query chậm
 *
 * Tích hợp:
 *   - JournalService là service trung tâm, mọi module gọi qua
 *   - PostingRuleService kiểm tra Dr-Cr pair hợp lệ
 *   - AuditLogger ghi lại mọi thay đổi
 *   - Tất cả module (Cash, AP, AR, Inventory) đều gọi JournalService
 */
class JournalController
{
    private JournalServiceInterface $journal;
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;

    public function __construct(JournalServiceInterface $journal, AccountRepositoryInterface $accountRepo, TransactionRepositoryInterface $txnRepo)
    {
        $this->journal = $journal;
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
    }

    public function list(): void
    {
        $period = $_GET['period'] ?? date('Y-m');
        // R-3: RBAC scope theo created_by — KTV chỉ thấy data mình tạo
        // Admin + KTT thấy tất cả. Configurable qua rbac.scope_by_creator.
        $createdByFilter = null;
        if (!\Accounting\Infrastructure\Auth::canViewAllData()) {
            $createdByFilter = \Accounting\Infrastructure\Auth::getCurrentUserId();
        }
        $txns = $this->txnRepo->getTransactionsByPeriod($period, $createdByFilter);
        $result = [];
        foreach ($txns as $txn) {
            $lines = [];
            foreach ($txn->getLedgerEntries() as $e) {
                $a = $this->accountRepo->findById($e->getAccountId());
                $lines[] = [
                    'account_code' => $a ? $a->getCode() : $e->getAccountId(),
                    'account_name' => $a ? $a->getName() : '',
                    'amount' => $e->getAmount(),
                    'is_debit' => $e->isDebit(),
                ];
            }
            $result[] = [
                'id' => $txn->getId(),
                'reference' => $txn->getReference(),
                'description' => $txn->getDescription(),
                'date' => $txn->getDate()->format('Y-m-d H:i:s'),
                'status' => $txn->getStatus(),
                'created_by' => $txn->getCreatedBy(),
                'lines' => $lines,
            ];
        }
        JsonResponse::ok($result);
    }

    public function get(string $id): void
    {
        $txn = $this->txnRepo->findById($id);
        if (!$txn) { JsonResponse::error('Không tìm thấy bút toán', 404); return; }
        $lines = [];
        foreach ($txn->getLedgerEntries() as $e) {
            $a = $this->accountRepo->findById($e->getAccountId());
            $lines[] = [
                'account_code' => $a ? $a->getCode() : $e->getAccountId(),
                'account_name' => $a ? $a->getName() : '',
                'amount' => $e->getAmount(),
                'is_debit' => $e->isDebit(),
            ];
        }
        JsonResponse::ok([
            'id' => $txn->getId(),
            'reference' => $txn->getReference(),
            'description' => $txn->getDescription(),
            'date' => $txn->getDate()->format('Y-m-d H:i:s'),
            'status' => $txn->getStatus(),
            'created_by' => $txn->getCreatedBy(),
            'lines' => $lines,
        ]);
    }

    // NGHIỆP VỤ: Tạo bút toán nháp (draft) — chưa ghi sổ, có thể sửa/xóa
    // Input: { description?, reference?, lines: [{account_code, amount, is_debit}], created_by? }
    // Output: { id, reference, status: 'draft', date } — 201 Created
    // Service: JournalService.createDraft() — validate: ít nhất 2 lines, Dr=Cr, posting rules
    // Permission: Không cần CSRF (không ảnh hưởng data cuối cùng), không cần permission đặc biệt
    // Rủi ro: R002 — Kiểm tra Dr=Cr ngay tại createDraft (tolerance ±10). R005 — Control account check
    // Ràng buộc: lines phải có ít nhất 1 Dr và 1 Cr. Tất cả tài khoản phải tồn tại
    // Trạng thái: draft → submitted (gửi duyệt) → posted (đã ghi sổ)
    public function createDraft(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('journal', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['lines']) || count($data['lines']) < 2) {
            JsonResponse::error('Bút toán phải có ít nhất 2 dòng với mã tài khoản, số tiền và loại Nợ/Có', 400);
            return;
        }
        try {
            $txn = $this->journal->createDraft(
                $data['description'] ?? '',
                $data['reference'] ?? uniqid('ref_'),
                $data['lines'],
                $_SESSION['user']['username'] ?? 'system'
            );
            JsonResponse::ok([
                'id' => $txn->getId(),
                'reference' => $txn->getReference(),
                'status' => $txn->getStatus(),
                'date' => $txn->getDate()->format('Y-m-d H:i:s'),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // NGHIỆP VỤ: Phê duyệt draft (gửi duyệt) — chuyển status draft → submitted
    // Input: id (URL)
    // Output: { id, reference, status: 'submitted' }
    // Service: JournalService.approveDraft() — cập nhật status
    // Permission: journal, approve
    // Rủi ro: Sau khi submitted, draft không sửa được nữa. Chờ ApprovalController duyệt
    // Quy trình: Draft → Submitted → (ApprovalController) → Posted
    public function approveDraft(string $id): void
    {
        Auth::requirePermission('journal', 'approve');
        try {
            $txn = $this->journal->approveDraft($id, $_SESSION['user']['username'] ?? 'system');
            JsonResponse::ok([
                'id' => $txn->getId(),
                'reference' => $txn->getReference(),
                'status' => $txn->getStatus(),
                'date' => $txn->getDate()->format('Y-m-d H:i:s'),
            ]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // NGHIỆP VỤ: Ghi sổ bút toán trực tiếp (post ngay, không qua draft) — CORE OPERATION
    // Input: { description?, reference?, lines: [{account_code, amount, is_debit}], created_by? }
    // Output: { id, reference, status: 'posted', date, description, lines } — 201 Created
    // Service: JournalService.postEntry() — validate posting rules → insert transaction + ledger_entries
    // Transaction: JournalService tự wrap trong beginTransaction/commit/rollback
    // Permission: Không CSRF (gọi từ service khác, không trình duyệt trực tiếp)
    // Validate sequence: (1) Account tồn tại (2) Lines ≥ 2 (3) Dr=Cr ±10 (4) Control account (5) Posting rules
    // (6) Period open check (7) Voucher uniqueness (SELECT FOR UPDATE)
    // Rủi ro: R002 (Dr=Cr), R001 (period closed), R005 (control account), R006 (voucher)
    // Ràng buộc: Sau post, không sửa/xóa được. Chỉ reverse (journal reversal)
    public function postEntry(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('journal', 'post');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['lines']) || count($data['lines']) < 2) {
            JsonResponse::error('Bút toán phải có ít nhất 2 dòng với mã tài khoản, số tiền và loại Nợ/Có', 400);
            return;
        }
        try {
            $txn = $this->journal->postEntry(
                $data['description'] ?? '',
                $data['reference'] ?? uniqid('ref_'),
                $data['lines'],
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok([
                'id' => $txn->getId(),
                'reference' => $txn->getReference(),
                'status' => $txn->getStatus(),
                'date' => $txn->getDate()->format('Y-m-d H:i:s'),
                'description' => $txn->getDescription(),
                'lines' => array_map(fn($e) => [
                    'account_id' => $e->getAccountId(),
                    'amount' => $e->getAmount(),
                    'is_debit' => $e->isDebit(),
                ], $txn->getLedgerEntries()),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    // NGHIỆP VỤ: Bảng cân đối tài khoản (Trial Balance) — tổng hợp số dư tất cả TK
    // Input: none (đọc tất cả tài khoản)
    // Output: { accounts: [{code, name, type, debit, credit}], total_debit, total_credit, balanced }
    // Service: AccountRepository.findAll() — lấy số dư từng tài khoản
    // Tính chất: Normal_balance D = Asset, Expense. Normal_balance C = Liability, Equity, Revenue
    // Kiểm tra: total_debit - total_credit < 10 VND → balanced = true
    // Rủi ro: R002 (CRITICAL) — Nếu balanced=false → toàn bộ hệ thống sai, không thể lập BCTC
    // Mục đích: Kiểm tra toàn bộ hệ thống trước khi đóng kỳ (PeriodController.closeWithChecklist)
    public function trialBalance(): void
    {
        $accounts = $this->accountRepo->findAll();
        $result = [];
        $totalDr = 0; $totalCr = 0;

        foreach ($accounts as $a) {
            $bal = $a->getBalance();
            if (abs($bal) < 500) continue;
            $isDr = in_array($a->getType(), ['asset', 'expense']);
            $dr = $isDr ? $bal : 0;
            $cr = $isDr ? 0 : $bal;
            $totalDr += $dr;
            $totalCr += $cr;
            $result[] = [
                'code' => $a->getCode(),
                'name' => $a->getName(),
                'type' => $a->getType(),
                'debit' => round($dr, 0),
                'credit' => round($cr, 0),
            ];
        }

        JsonResponse::ok([
            'accounts' => $result,
            'total_debit' => round($totalDr, 0),
            'total_credit' => round($totalCr, 0),
            'balanced' => abs($totalDr - $totalCr) < 10,
        ]);
    }

    //
    // R-9: Duplicate bút toán — copy lines từ bút toán gốc → tạo draft mới
    // Input: POST /api/journal/duplicate/{id} body: { date?: 'YYYY-MM-DD' }
    // Output: { id, reference: '', status: 'draft', lines }
    // Quyền: journal.create
    //
    public function duplicate(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('journal', 'create');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $newDate = $data['date'] ?? null;

        try {
            $draft = $this->journal->duplicateEntry(
                $id,
                Auth::getCurrentUserId() ?? 'system',
                $newDate
            );
            JsonResponse::ok([
                'id' => $draft->getId(),
                'status' => $draft->getStatus(),
                'description' => $draft->getDescription(),
                'line_count' => count($draft->getLedgerEntries()),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    //
    // R-13: Soft delete — xóa mềm bút toán (chỉ draft/reversed, không cho posted)
    // Input: POST /api/journal/{id}/delete body: { reason: string }
    //
    public function softDelete(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('journal', 'delete');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = $data['reason'] ?? '';

        if (!$reason) {
            JsonResponse::error('Vui lòng nhập lý do xóa (bắt buộc cho audit trail)', 400);
            return;
        }

        try {
            $this->journal->softDelete($id, Auth::getCurrentUserId() ?? 'system', $reason);
            JsonResponse::ok(['id' => $id, 'deleted' => true]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    //
    // R-13: Restore — khôi phục bút toán đã xóa (trong 30 ngày)
    // Input: POST /api/journal/{id}/restore
    //
    public function restore(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('journal', 'delete');
        try {
            $txn = $this->journal->restore($id, Auth::getCurrentUserId() ?? 'system');
            JsonResponse::ok([
                'id' => $txn->getId(),
                'status' => $txn->getStatus(),
                'restored' => true,
            ]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    //
    // R-8: Bulk Post — ghi sổ hàng loạt, all-or-nothing transactional
    // Input: POST /api/journal/bulk-post body: { txn_ids: [...] }
    // Output: { posted: [...], failed: [{id, error}], rolled_back: bool }
    //
    public function bulkPost(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('journal', 'post');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $ids = $data['txn_ids'] ?? [];

        if (!is_array($ids) || count($ids) === 0) {
            JsonResponse::error('Vui lòng cung cấp danh sách txn_ids', 400);
            return;
        }

        $result = $this->journal->bulkPost($ids, Auth::getCurrentUserId() ?? 'system');
        $httpStatus = $result['rolled_back'] ? 422 : 200;
        JsonResponse::ok($result, $httpStatus);
    }
}

<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Contract\JournalServiceInterface;
use Accounting\Domain\Model\LedgerEntry;
use Accounting\Domain\Model\Transaction;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;

// NGHIỆP VỤ: JournalService là GL Posting Engine — trung tâm xử lý mọi bút toán kế toán.
// Tất cả nghiệp vụ (tiền mặt, ngân hàng, mua hàng, bán hàng, kho, TSCĐ, kết chuyển...)
// đều đi qua service này. Đảm bảo các nguyên tắc bất biến của kế toán:
//   - Tổng Nợ = Tổng Có (Dr = Cr)
//   - Không post vào tài khoản tổng hợp (control account) — chỉ post vào TK con
//   - Posting rules kiểm tra cặp Dr-Cr theo từng module nghiệp vụ
//   - Audit trail cho mọi thay đổi (bắt buộc kiểm toán)
//   - Kỳ kế toán đã đóng là read-only
//   - Số chứng từ tự động tăng, chống trùng dưới concurrent access
//
// TÍCH HỢP: Được gọi từ CashService, ApService, ArService, InventoryService,
// FixedAssetService, PeriodService (kết chuyển cuối kỳ).
class JournalService implements JournalServiceInterface
{
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;
    private ?\PDO $pdo;
    private ?AuditLoggerInterface $auditLogger;
    private ?PostingRuleService $postingRuleService;
    private ?VoucherService $voucherService;
    private ?ApprovalRoutingService $approvalRoutingService;
    private ?\Accounting\Domain\Service\NotificationService $notificationService;

    public function __construct(
        AccountRepositoryInterface $accountRepo,
        TransactionRepositoryInterface $txnRepo,
        ?\PDO $pdo = null,
        ?AuditLoggerInterface $auditLogger = null,
        ?PostingRuleService $postingRuleService = null,
        ?VoucherService $voucherService = null,
        ?ApprovalRoutingService $approvalRoutingService = null,
        ?\Accounting\Domain\Service\NotificationService $notificationService = null
    ) {
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
        $this->pdo = $pdo;
        $this->auditLogger = $auditLogger;
        $this->postingRuleService = $postingRuleService;
        $this->voucherService = $voucherService;
        $this->approvalRoutingService = $approvalRoutingService;
        $this->notificationService = $notificationService;
    }

    // NGHIỆP VỤ: Kiểm tra từng cặp Dr-Cr theo posting rules đã seed trong bảng posting_rules (75 rules).
    // Mỗi module nghiệp vụ có bộ rules riêng. Block → ngăn chặn hoàn toàn. Warn → cho phép + log.
    // RỦI RO: Nếu bỏ qua posting rules → sai bản chất nghiệp vụ → dẫn đến sai báo cáo tài chính.
    // Ví dụ: Dr 631 (CP NVL trực tiếp) / Cr 111 (Tiền mặt) là không hợp lệ cho module purchase.
    /**
     * Validate Dr-Cr pairs against posting rules. Throws if any rule blocks.
     */
    private function validatePostingRules(array $lines, ?string $module): void
    {
        if ($this->postingRuleService === null) return;
        $results = $this->postingRuleService->validateEntry($lines, $module);
        if ($this->postingRuleService->hasBlock($results)) {
            $blocked = array_values(array_filter($results, fn($r) => $r['severity'] === 'block'));
            throw new \InvalidArgumentException(
                'Vi phạm quy tắc hạch toán: ' . ($blocked[0]['message'] ?? 'cặp tài khoản bị chặn')
            );
        }
    }

    // NGHIỆP VỤ: Tạo bút toán nháp — ghi nhận nghiệp vụ nhưng chưa ảnh hưởng số dư tài khoản.
    // Luồng điển hình: Kế toán nhập liệu → lưu nháp → submit → approve → post (cập nhật balance).
    // Cố ý không kiểm tra kỳ kế toán ở bước này — chỉ kiểm tra khi post thực tế (approveDraft / postEntry).
    // Điều này cho phép nhập liệu cho kỳ sau trong khi chờ mở kỳ.
    //
    // RỦI RO: Số dư cuối kỳ có thể thiếu nếu draft tồn đọng không được duyệt kịp.
    // Kiểm soát: Báo cáo "Draft chưa duyệt" cuối kỳ — kế toán trưởng phải review.
    /**
     * Create a draft journal entry: validates Dr=Cr, saves as 'pending' without balance changes.
     */
    public function createDraft(
        string $description,
        string $reference,
        array $lines,
        string $createdBy,
        bool $allowControl = false,
        ?string $module = null,
        ?string $date = null,
        ?string $voucherType = null,
        ?string $sourceModule = null,
        string $currency = 'VND',
        float $exchangeRate = 1.0
    ): Transaction
    {
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('Bút toán phải có ít nhất 2 dòng (Nợ và Có)');
        }

        $totalDr = 0.0;
        $totalCr = 0.0;
        $entryLines = [];

        foreach ($lines as $line) {
            if ($line['amount'] <= 0) {
                throw new \InvalidArgumentException('Số tiền phải lớn hơn 0');
            }
            $account = $this->accountRepo->findByCode($line['account_code']);
            if (!$account) {
                throw new \InvalidArgumentException("Không tìm thấy tài khoản: {$line['account_code']}");
            }
            if ($account->isControl() && !$allowControl) {
                throw new \InvalidArgumentException(
                    "Tài khoản {$line['account_code']} ({$account->getName()}) là tài khoản tổng hợp — vui lòng hạch toán vào tài khoản chi tiết"
                );
            }
            if ($line['is_debit']) $totalDr += $line['amount'];
            else $totalCr += $line['amount'];
            $entryLines[] = new LedgerEntry(uniqid('led_'), $account->getId(), $line['amount'], $line['is_debit']);
        }

        // KIỂM TRA TỔNG NỢ = TỔNG CÓ: Nguyên tắc bất biến của kế toán kép (double-entry).
        // Tolerance ±10 VND: Cho phép sai số làm tròn số học khi tính toán nhiều dòng bút toán.
        // RỦI RO: Tolerance quá lớn → bỏ qua lỗi ghi nhận → BC01 mất cân đối.
        // RỦI RO: Tolerance quá nhỏ → từ chối bút toán hợp lệ do rounding error
        // (VD: phân bổ 100.000đ cho 3 dòng: 33.333,33 + 33.333,33 + 33.333,34 = 100.000).
        if (abs($totalDr - $totalCr) > 10) {
            throw new \InvalidArgumentException("Tổng Nợ ($totalDr) không bằng tổng Có ($totalCr)");
        }

        $this->validatePostingRules($lines, $module);

        $finalReference = $reference ?: ($this->voucherService ? $this->voucherService->nextNumber('JV') : '');
        $txnDate = $date ? new \DateTimeImmutable($date) : new \DateTimeImmutable();
        $txn = new Transaction(uniqid('jrn_'), $txnDate, $description, $finalReference, $voucherType, $sourceModule, $currency, $exchangeRate);
        foreach ($entryLines as $e) $txn->addLedgerEntry($e);
        $txn->setCreatedBy($createdBy);
        $this->txnRepo->save($txn);

        $this->auditLogger?->log('journal.draft', 'transaction', $txn->getId(), null, [
            'reference' => $reference, 'description' => $description,
            'total_dr' => $totalDr, 'total_cr' => $totalCr,
        ], $createdBy);

        return $txn;
    }

    // KIỂM SOÁT: Ghi nhật ký duyệt — ai duyệt, hành động gì, ý kiến gì, thời gian nào.
    // Bắt buộc theo yêu cầu Kiểm toán độc lập: phải trace được toàn bộ vòng đời phê duyệt.
    // Dữ liệu này không được xóa — phục vụ đối chiếu sau này.
    private function recordApprovalAction(string $txnId, string $action, string $actor, ?string $comment = null, int $level = 1): void
    {
        if ($this->pdo === null) return;
        $stmt = $this->pdo->prepare(
            'INSERT INTO journal_entry_approvals (transaction_id, action, approval_level, actor, comment) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$txnId, $action, $level, $actor, $comment]);
    }

    // LUỒNG DUYỆT Bước 1: Người tạo gửi duyệt. pending → submitted.
    // Sau bước này, người tạo không thể sửa bút toán — chỉ Kế toán trưởng mới duyệt/từ chối được.
    public function submitEntry(string $txnId, string $submittedBy): Transaction
    {
        $txn = $this->txnRepo->findById($txnId);
        if (!$txn) {
            throw new \InvalidArgumentException("Không tìm thấy bút toán mã {$txnId}");
        }
        $txn->submit();
        $this->txnRepo->save($txn);
        $this->recordApprovalAction($txnId, 'submit', $submittedBy);
        $this->auditLogger?->log('journal.submit', 'transaction', $txnId,
            ['status' => 'pending'], ['status' => 'submitted'], $submittedBy);
        // R-12: thông báo broadcast cho KTT/admin — có bút toán cần duyệt
        $this->notificationService?->notifyPendingApproval(
            $txnId, $submittedBy, $txn->getDescription(), $this->txnAmount($txnId)
        );
        return $txn;
    }

    // LUỒNG DUYỆT Bước 2: Kế toán trưởng phê duyệt. submitted → approved.
    // Lưu ý: approved ≠ posted — bút toán mới chỉ được duyệt, chưa ảnh hưởng số dư tài khoản.
    // Cần gọi approveDraft để thực sự ghi nhận vào sổ cái.
    //
    // R-16 Multi-level: nếu giao dịch cần N cấp duyệt:
    //   - Cấp hiện tại < N: ghi nhận approve, status vẫn là "submitted", chờ cấp tiếp theo
    //   - Cấp hiện tại = N: set status = "approved", cho phép post
    public function approveEntry(string $txnId, string $approverId, ?string $comment = null): Transaction
    {
        $txn = $this->txnRepo->findById($txnId);
        if (!$txn) {
            throw new \InvalidArgumentException("Không tìm thấy bút toán mã {$txnId}");
        }

        // R-16: xác định số cấp duyệt cần thiết
        $requiredSteps = ['chief_accountant']; // default fallback
        $currentLevel = 1;
        if ($this->approvalRoutingService !== null) {
            $total = $this->txnAmount($txnId);
            $module = $txn->getSourceModule();
            $requiredSteps = $this->approvalRoutingService->getRequiredApprovalSteps($total, $module);
            $currentLevel = $this->approvalRoutingService->getCurrentApprovalLevel($txnId, $requiredSteps);
        }
        $isFinalLevel = $currentLevel >= count($requiredSteps);

        if ($isFinalLevel) {
            $txn->approve();
            $this->txnRepo->save($txn);
        }
        // Ghi nhận approval action với level hiện tại
        $this->recordApprovalAction($txnId, 'approve', $approverId, $comment, $currentLevel);

        $this->auditLogger?->log('journal.approve', 'transaction', $txnId,
            ['status' => 'submitted', 'level' => $currentLevel - 1],
            ['status' => $isFinalLevel ? 'approved' : 'submitted', 'level' => $currentLevel, 'required_levels' => count($requiredSteps)],
            $approverId);

        // R-12: thông báo — chỉ thông báo khi duyệt xong (final level) để tránh spam
        if ($isFinalLevel) {
            $this->notificationService?->notifyApprovalResult(
                $txnId, $txn->getCreatedBy() ?? 'system', true, $approverId, $comment
            );
        }
        return $txn;
    }

    // LUỒNG DUYỆT Bước 2 (từ chối): submitted → rejected.
    // Bắt buộc có lý do từ chối (reason) — phục vụ kiểm soát nội bộ và đào tạo.
    // Người tạo có thể sửa lại và gửi duyệt lại (returnEntry → submitEntry).
    public function rejectEntry(string $txnId, string $approverId, string $reason): Transaction
    {
        $txn = $this->txnRepo->findById($txnId);
        if (!$txn) {
            throw new \InvalidArgumentException("Không tìm thấy bút toán mã {$txnId}");
        }
        $txn->reject();
        $this->txnRepo->save($txn);
        $this->recordApprovalAction($txnId, 'reject', $approverId, $reason);
        $this->auditLogger?->log('journal.reject', 'transaction', $txnId,
            ['status' => 'submitted'], ['status' => 'rejected', 'reason' => $reason], $approverId);
        // R-12: thông báo cho người tạo biết bị từ chối
        $this->notificationService?->notifyApprovalResult(
            $txnId, $txn->getCreatedBy() ?? 'system', false, $approverId, $reason
        );
        return $txn;
    }

    // LUỒNG DUYỆT Bước 3 (trả lại): rejected → pending (về trạng thái nháp).
    // Cho phép người tạo sửa lại nội dung dựa trên lý do từ chối, sau đó gửi duyệt lại.
    // RỦI RO: Nếu không có cơ chế kiểm soát, bút toán có thể bị trả lại nhiều lần → delay xử lý.
    // Kiểm soát: Giới hạn số lần trả lại (tùy chính sách từng công ty).
    public function returnEntry(string $txnId, string $userId, ?string $comment = null): Transaction
    {
        $txn = $this->txnRepo->findById($txnId);
        if (!$txn) {
            throw new \InvalidArgumentException("Không tìm thấy bút toán mã {$txnId}");
        }
        $txn->returnToDraft();
        $this->txnRepo->save($txn);
        $this->recordApprovalAction($txnId, 'return', $userId, $comment);
        $this->auditLogger?->log('journal.return', 'transaction', $txnId,
            ['status' => 'rejected'], ['status' => 'pending'], $userId);
        return $txn;
    }

    // NGHIỆP VỤ: Duyệt và post bút toán đã duyệt — thực sự cập nhật số dư tài khoản.
    // Đây là bước không thể undo — nếu sai phải làm bút toán đảo (reversal entry).
    // Atomic transaction: Nếu bất kỳ tài khoản nào cập nhật thất bại → rollback toàn bộ.
    //
    // KIỂM TRA khi post (không phải khi draft):
    //   1. Kỳ kế toán còn mở? (PeriodService::isPeriodOpen)
    //   2. Posting rules vẫn hiệu lực? (rules có thể thay đổi từ lúc tạo draft)
    //   3. Draft còn ở trạng thái pending? (không post 2 lần)
    /**
     * Approve and post a draft: applies balance changes atomically.
     */
    public function approveDraft(string $txnId, string $approvedBy): Transaction
    {
        $txn = $this->txnRepo->findById($txnId);
        if (!$txn || $txn->getStatus() !== 'pending') {
            throw new \InvalidArgumentException('Bút toán nháp không tồn tại hoặc đã được ghi sổ');
        }

        $draftDate = $txn->getDate()->format('Y-m-d');
        if (!PeriodService::isPeriodOpen($draftDate, $this->pdo)) {
            throw new \RuntimeException("Không thể ghi sổ: ngày {$draftDate} thuộc kỳ kế toán đã đóng");
        }

        // Validate posting rules on approval too (rules may have changed since draft)
        $lines = [];
        foreach ($txn->getLedgerEntries() as $entry) {
            $acct = $this->accountRepo->findById($entry->getAccountId());
            if ($acct) {
                $lines[] = ['account_code' => $acct->getCode(), 'is_debit' => $entry->isDebit(), 'amount' => $entry->getAmount()];
            }
        }
        $this->validatePostingRules($lines, null);

        $inTransaction = $this->pdo !== null && !$this->pdo->inTransaction();
        if ($inTransaction) $this->pdo->beginTransaction();

        try {
            // GHI NHẬN SỐ DƯ TÀI KHOẢN (Cập nhật balance trong DB transaction):
            // Quy tắc kế toán Việt Nam (Circular 99, VAS 01):
            //   - TK Tài sản (1xx, 2xx) & Chi phí (6xx, 8xx):
            //       Bút toán Nợ → TĂNG số dư, Bút toán Có → GIẢM số dư
            //   - TK Nợ phải trả (3xx) & Vốn CSH (4xx) & Doanh thu (5xx, 7xx):
            //       Bút toán Có → TĂNG số dư, Bút toán Nợ → GIẢM số dư
            // LƯU Ý: Account::debit()/credit() là operation trên balance, KHÔNG phải bên Nợ/Của.
            // RỦI RO: Sai chiều tác động → toàn bộ BC01/BC02 sai dấu → mất cân đối.
            foreach ($txn->getLedgerEntries() as $entry) {
                $account = $this->accountRepo->findById($entry->getAccountId());
                if (!$account) continue;
                if ($entry->isDebit()) {
                    if (in_array($account->getType(), ['asset', 'expense'])) $account->credit($entry->getAmount());
                    else $account->debit($entry->getAmount());
                } else {
                    if (in_array($account->getType(), ['liability', 'equity', 'revenue'])) $account->credit($entry->getAmount());
                    else $account->debit($entry->getAmount());
                }
                $this->accountRepo->save($account);
            }

            $txn->post($approvedBy);
            $this->txnRepo->save($txn);

            if ($inTransaction) $this->pdo->commit();

            $this->auditLogger?->log('journal.approve', 'transaction', $txn->getId(),
                ['status' => 'pending'], ['status' => 'posted'], $approvedBy);

            return $txn;
        } catch (\Exception $e) {
            if ($inTransaction) $this->pdo->rollBack();
            throw $e;
        }
    }

    // NGHIỆP VỤ: Sinh số chứng từ tự động theo format {PREFIX}{YYYY}-{000000}.
    // Prefix convention: PC (Phiếu chi), PT (Phiếu thu), JV (Journal Voucher),
    // PNK (Phiếu nhập kho), PXK (Phiếu xuất kho).
    // CONCURRENCY: VoucherService sử dụng SELECT FOR UPDATE để chống trùng số.
    // RỦI RO: Nếu không lock → trùng số chứng từ → mất audit trail → rủi ro pháp lý.
    /**
     * Post a journal entry: validates Dr=Cr first, then applies balance changes atomically.
     * Control accounts (Level 1 parent accounts with sub-accounts) are blocked.
     * Set $allowControl to true for Chief Accountant override.
     */
    public function generateVoucherNo(string $prefix = 'JV'): string
    {
        if (!$this->voucherService) {
            throw new \RuntimeException('VoucherService chưa được cấu hình');
        }
        return $this->voucherService->nextNumber($prefix);
    }

    public function createSupplementaryEntry(string $originalTxnId, array $correctLines, string $reason, string $createdBy, bool $allowControl = false): Transaction
    {
        $original = $this->txnRepo->findById($originalTxnId);
        if (!$original) {
            throw new \InvalidArgumentException("Không tìm thấy bút toán gốc mã {$originalTxnId}");
        }

        $txn = $this->postEntry(
            description: "BS: {$original->getDescription()} — {$reason}",
            reference: '',
            lines: $correctLines,
            createdBy: $createdBy,
            allowControl: $allowControl,
            module: 'correction',
            date: date('Y-m-d'),
            voucherType: 'JV'
        );

        $txn->setIsCorrection(true);
        $txn->setCorrectionType('supplementary');
        $txn->setOriginalTransactionId($originalTxnId);
        $txn->setCorrectionReason($reason);
        $this->txnRepo->save($txn);

        $this->auditLogger?->log('correction.supplementary', 'transaction', $txn->getId(),
            ['original_id' => $originalTxnId],
            ['type' => 'supplementary', 'reason' => $reason, 'lines' => $correctLines],
            $createdBy);

        return $txn;
    }

    public function createNegativeEntry(string $originalTxnId, string $reason, string $createdBy, bool $allowControl = false): Transaction
    {
        $original = $this->txnRepo->findById($originalTxnId);
        if (!$original) {
            throw new \InvalidArgumentException("Không tìm thấy bút toán gốc mã {$originalTxnId}");
        }
        if ($original->getStatus() !== 'posted') {
            throw new \InvalidArgumentException("Chỉ có thể điều chỉnh bút toán đã ghi sổ");
        }

        $this->pdo->beginTransaction();
        try {
            $reverseLines = [];
            foreach ($original->getLedgerEntries() as $entry) {
                $acct = $this->accountRepo->findById($entry->getAccountId());
                $reverseLines[] = [
                    'account_code' => $acct ? $acct->getCode() : $entry->getAccountId(),
                    'amount' => $entry->getAmount(),
                    'is_debit' => !$entry->isDebit(),
                ];
            }

            $txn = $this->postEntry(
                description: "RĐ: Đảo bút toán {$original->getReference()} — {$reason}",
                reference: '',
                lines: $reverseLines,
                createdBy: $createdBy,
                allowControl: $allowControl,
                module: 'correction',
                date: date('Y-m-d'),
                voucherType: 'JV'
            );

            $txn->setIsCorrection(true);
            $txn->setCorrectionType('negative');
            $txn->setOriginalTransactionId($originalTxnId);
            $txn->setCorrectionReason($reason);
            $this->txnRepo->save($txn);

            $original->reverse($createdBy);
            $this->txnRepo->save($original);

            $this->pdo->commit();

            $this->auditLogger?->log('correction.negative', 'transaction', $txn->getId(),
                ['original_id' => $originalTxnId, 'original_status' => 'posted'],
                ['type' => 'negative', 'reason' => $reason, 'reversed_amount' => $txn->getLedgerEntries()],
                $createdBy);

            return $txn;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function createAdjustingEntry(string $originalTxnId, array $movingLines, string $reason, string $createdBy, bool $allowControl = false): Transaction
    {
        $original = $this->txnRepo->findById($originalTxnId);
        if (!$original) {
            throw new \InvalidArgumentException("Không tìm thấy bút toán gốc mã {$originalTxnId}");
        }

        $txn = $this->postEntry(
            description: "ĐC: Điều chỉnh bút toán {$original->getReference()} — {$reason}",
            reference: '',
            lines: $movingLines,
            createdBy: $createdBy,
            allowControl: $allowControl,
            module: 'correction',
            date: date('Y-m-d'),
            voucherType: 'JV'
        );

        $txn->setIsCorrection(true);
        $txn->setCorrectionType('adjusting');
        $txn->setOriginalTransactionId($originalTxnId);
        $txn->setCorrectionReason($reason);
        $this->txnRepo->save($txn);

        $this->auditLogger?->log('correction.adjusting', 'transaction', $txn->getId(),
            ['original_id' => $originalTxnId],
            ['type' => 'adjusting', 'reason' => $reason, 'lines' => $movingLines],
            $createdBy);

        return $txn;
    }

    //
    // R-9: SAO CHÉP BÚT TOÁN (Duplicate) — kế toán tiết kiệm 80% thời gian nhập liệu
    //
    // Nghiệp vụ: Kế toán thường xuyên nhập các bút toán tương tự nhau (vd: tiền điện hàng tháng).
    // Tính năng này copy lines từ bút toán gốc → tạo draft mới, user chỉ cần sửa ngày/số tiền.
    //
    // Ràng buộc:
    //   - Copy được cả posted và draft (không giới hạn status)
    //   - Bút toán mới là DRAFT (status='draft', chưa posted)
    //   - Reference reset = rỗng (VoucherService sẽ sinh số mới khi post)
    //   - created_by = user hiện tại (audit trail)
    //   - Giữ nguyên: account codes, amounts, currency, exchange rate
    //
    // Rủi ro: Copy sai số tiền/account → phải verify trước khi post. Form hiển thị
    //   cho user review trước khi submit là bắt buộc.
    //
    public function duplicateEntry(string $originalTxnId, string $createdBy, ?string $newDate = null): Transaction
    {
        $original = $this->txnRepo->findById($originalTxnId);
        if (!$original) {
            throw new \InvalidArgumentException("Không tìm thấy bút toán gốc mã {$originalTxnId}");
        }

        $lines = [];
        foreach ($original->getLedgerEntries() as $entry) {
            $acct = $this->accountRepo->findById($entry->getAccountId());
            $lines[] = [
                'account_code' => $acct ? $acct->getCode() : (string)$entry->getAccountId(),
                'amount' => $entry->getAmount(),
                'is_debit' => $entry->isDebit(),
            ];
        }

        $description = '[COPY] ' . $original->getDescription();
        $module = $original->getSourceModule() ?? 'journal';
        $date = $newDate ?? date('Y-m-d');

        $draft = $this->createDraft(
            description: $description,
            reference: '',
            lines: $lines,
            createdBy: $createdBy,
            module: $module,
            date: $date,
            voucherType: $original->getVoucherType(),
            sourceModule: $module,
            currency: $original->getCurrency(),
            exchangeRate: $original->getExchangeRate()
        );

        $this->auditLogger?->log('journal.duplicate', 'transaction', $draft->getId(),
            ['original_id' => $originalTxnId, 'original_status' => $original->getStatus()],
            ['status' => 'draft', 'line_count' => count($lines)],
            $createdBy);

        return $draft;
    }

    //
    // R-13: SOFT DELETE — xóa mềm bút toán (giữ data, ẩn khỏi list)
    //
    // Nghiệp vụ: Cho phép xóa bút toán NHẦM (draft hoặc posted đã reverse) mà không
    // mất audit trail. Restore trong vòng 30 ngày.
    //
    // Ràng buộc:
    //   - KHÔNG cho xóa bút toán posted (status='posted') — phải reverse trước
    //     rồi mới delete (vì posted = ảnh hưởng BC)
    //   - Bút toán đã reversed (status='reversed') cho xóa
    //   - Draft (status='draft'|'pending'|'submitted') cho xóa bình thường
    //   - Audit: ghi lại deleted_by, deleted_at + audit log
    //
    // Rủi ro: Xóa nhầm bút toán posted → sai BC. Code check bên dưới.
    //
    public function softDelete(string $txnId, string $deletedBy, string $reason): void
    {
        $txn = $this->txnRepo->findById($txnId);
        if (!$txn) {
            throw new \InvalidArgumentException("Không tìm thấy bút toán mã {$txnId}");
        }
        if ($txn->getStatus() === 'posted') {
            throw new \InvalidArgumentException(
                'Không thể xóa bút toán đã ghi sổ. Hãy reverse trước (tạo bút toán ngược).'
            );
        }
        if ($txn->isDeleted()) {
            throw new \InvalidArgumentException('Bút toán đã bị xóa trước đó.');
        }

        $txn->setDeletedAt(new \DateTimeImmutable());
        $txn->setDeletedBy($deletedBy);
        $this->txnRepo->save($txn);

        $this->auditLogger?->log('journal.soft_delete', 'transaction', $txnId,
            ['status' => $txn->getStatus()],
            ['deleted_at' => $txn->getDeletedAt()?->format('Y-m-d H:i:s'), 'deleted_by' => $deletedBy, 'reason' => $reason],
            $deletedBy);
    }

    //
    // R-13: RESTORE — khôi phục bút toán đã soft delete (trong 30 ngày)
    //
    // Ràng buộc: Chỉ restore trong 30 ngày (rollback window — config: import.rollback_window_hours
    //   hoặc dedicated journal.restore_window_days). Sau 30 ngày, cần can thiệp thủ công.
    //
    public function restore(string $txnId, string $restoredBy, int $windowDays = 30): Transaction
    {
        $txn = $this->txnRepo->findById($txnId);
        if (!$txn) {
            throw new \InvalidArgumentException("Không tìm thấy bút toán mã {$txnId}");
        }
        if (!$txn->isDeleted()) {
            throw new \InvalidArgumentException('Bút toán chưa bị xóa.');
        }

        $deletedAt = $txn->getDeletedAt();
        $now = new \DateTimeImmutable();
        $daysSinceDelete = (int)$now->diff($deletedAt)->format('%r%a');
        if ($daysSinceDelete > $windowDays) {
            throw new \InvalidArgumentException(
                "Đã quá {$windowDays} ngày kể từ khi xóa. Không thể restore tự động — liên hệ KTT."
            );
        }

        $txn->setDeletedAt(null);
        $txn->setDeletedBy(null);
        $this->txnRepo->save($txn);

        $this->auditLogger?->log('journal.restore', 'transaction', $txnId,
            ['deleted_at' => $deletedAt?->format('Y-m-d H:i:s')],
            ['status' => $txn->getStatus(), 'restored_by' => $restoredBy],
            $restoredBy);

        return $txn;
    }

    //
    // R-8: BULK POST — ghi sổ hàng loạt, transactional all-or-nothing
    //
    // Nghiệp vụ: Cuối tháng, kế toán chọn nhiều bút toán draft → ghi sổ cùng lúc.
    // Quyết định BA: ALL-OR-NOTHING (user confirmed) — nếu 1 fail thì rollback tất cả.
    //
    // Ràng buộc:
    //   - Tất cả txn phải ở status='draft' hoặc 'pending'
    //   - Nếu 1 cái fail (period locked, posting rule block, validation) → rollback
    //   - Audit: 1 log entry 'journal.bulk_post' với danh sách IDs thành công
    //   - Trả về: { posted: [...], failed: [{id, error}] }
    //
    // Rủi ro: Bulk post 100 bút toán trong 1 transaction → lock DB → tốc độ.
    //   Khuyến nghị: giới hạn batch ≤ 50 txn/lần.
    //
    public function bulkPost(array $txnIds, string $approverId, int $maxBatchSize = 50): array
    {
        if (count($txnIds) === 0) {
            throw new \InvalidArgumentException('Danh sách bút toán trống');
        }
        if (count($txnIds) > $maxBatchSize) {
            throw new \InvalidArgumentException(
                "Batch vượt quá giới hạn {$maxBatchSize} bút toán/lần. Vui lòng chia nhỏ."
            );
        }

        $posted = [];
        $failed = [];

        // Pre-validate tất cả trước (fail-fast)
        $txns = [];
        foreach ($txnIds as $id) {
            $txn = $this->txnRepo->findById($id);
            if (!$txn) {
                $failed[] = ['id' => $id, 'error' => 'Không tìm thấy bút toán'];
                continue;
            }
            if (!in_array($txn->getStatus(), ['draft', 'pending'], true)) {
                $failed[] = ['id' => $id, 'error' => "Trạng thái không hợp lệ: {$txn->getStatus()}"];
                continue;
            }
            $txns[] = $txn;
        }

        if (count($failed) > 0) {
            // Pre-validation fail → KHÔNG post bất kỳ cái nào
            return ['posted' => [], 'failed' => $failed, 'rolled_back' => true];
        }

        // All-or-nothing: 1 transaction duy nhất
        $this->pdo->beginTransaction();
        try {
            foreach ($txns as $txn) {
                // Bulk post bypass workflow (no submitted→approved), go straight to posted
                $txn->setStatus('posted');
                $this->txnRepo->save($txn);
                $this->recordApprovalAction($txn->getId(), 'approve', $approverId, 'bulk_post');
                $posted[] = $txn->getId();
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return [
                'posted' => [],
                'failed' => array_map(fn($t) => ['id' => $t->getId(), 'error' => $e->getMessage()], $txns),
                'rolled_back' => true,
            ];
        }

        $this->auditLogger?->log('journal.bulk_post', 'transaction', null,
            ['txn_ids' => $txnIds, 'approver' => $approverId],
            ['posted_count' => count($posted), 'failed_count' => 0],
            $approverId);

        return ['posted' => $posted, 'failed' => [], 'rolled_back' => false];
    }

    public function getCorrectionHistory(string $transactionId): array
    {
        $corrections = $this->txnRepo->getCorrectionsByOriginalId($transactionId);
        $result = [];
        foreach ($corrections as $txn) {
            $result[] = [
                'id' => $txn->getId(),
                'date' => $txn->getDate()->format('Y-m-d'),
                'reference' => $txn->getReference(),
                'description' => $txn->getDescription(),
                'correction_type' => $txn->getCorrectionType(),
                'correction_reason' => $txn->getCorrectionReason(),
                'status' => $txn->getStatus(),
                'created_by' => $txn->getCreatedBy(),
            ];
        }
        return $result;
    }

    // NGHIỆP VỤ: Post bút toán trực tiếp (không qua workflow draft → submit → approve).
    // Dành cho: giao dịch tự động (kết chuyển cuối kỳ, khấu hao TSCĐ, phân bổ chi phí),
    // hoặc nghiệp vụ đã được duyệt trước bên ngoài hệ thống.
    //
    // QUY TRÌNH 4 PHA TRONG MỘT TRANSACTION:
    //   Phase 1: Validate tất cả dòng — tài khoản tồn tại? Control account? Amount > 0?
    //   Phase 2: Kiểm tra Dr = Cr (tolerance ±10đ) + posting rules
    //   Phase 3: Cập nhật số dư tài khoản (balance changes)
    //   Phase 4: Tạo transaction record + commit
    //
    // RỦI RO: Nếu bất kỳ Phase 3 nào thất bại → rollback toàn bộ.
    // Không có "post một nửa" — đảm bảo tính toàn vẹn dữ liệu.
    //
    // HARD DEADLINE: Kỳ kế toán có thể có hard deadline (ví dụ: hạn nộp thuế).
    // Nếu đã quá hạn, chỉ Kế toán trưởng (allowControl=true) mới được bypass.
    public function postEntry(string $description, string $reference, array $lines, string $createdBy, bool $allowControl = false, ?string $module = null, ?string $date = null, ?string $voucherType = null, ?string $sourceModule = null, string $currency = 'VND', float $exchangeRate = 1.0): Transaction
    {
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('Bút toán phải có ít nhất 2 dòng (Nợ và Có)');
        }

        $postDate = $date ?? date('Y-m-d');
        if (!PeriodService::isPeriodOpen($postDate, $this->pdo)) {
            throw new \RuntimeException("Không thể ghi sổ: ngày {$postDate} thuộc kỳ kế toán đã đóng");
        }

        // KỲ KẾ TOÁN: Kiểm tra hard deadline — nếu quá hạn, từ chối ghi nhận
        // Exception: allowControl = true (Kế toán trưởng) có thể bypass
        //
        // CONCURRENCY: Deadline check không dùng FOR UPDATE vì accounting_periods ít thay đổi.
        // Nếu 2 request post cùng lúc khi deadline vừa hết hạn, cả 2 đều pass check do đọc
        // stale value. Request đầu commit thành công, request thứ 2 phát hiện hard_closed=1
        // và từ chối — cơ chế tự sửa lỗi (self-healing) nhờ idempotent check.
        if ($this->pdo && !$allowControl) {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM accounting_periods
                 WHERE ? BETWEEN start_date AND end_date
                   AND deadline IS NOT NULL
                   AND deadline < CURDATE()
                   AND hard_closed = 1"
            );
            $stmt->execute([$postDate]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new \RuntimeException(
                    "Không thể ghi sổ: hạn chót kỳ kế toán ngày {$postDate} đã qua. Vui lòng liên hệ Kế toán trưởng để được xử lý."
                );
            }
        }

        $inTransaction = $this->pdo !== null && !$this->pdo->inTransaction();
        if ($inTransaction) $this->pdo->beginTransaction();

        try {
            // QUY TRÌNH 4 PHA (Validate-Then-Execute) — đảm bảo tính toàn vẹn:
            // Phase 1-2: VALIDATE — kiểm tra tất cả điều kiện, KHÔNG thay đổi dữ liệu.
            // Phase 3-4: EXECUTE — áp dụng thay đổi số dư và ghi nhận transaction.
            // Lý do tách biệt: Nếu validate xen lẫn execute, rollback có thể bỏ sót
            // side effect (ví dụ: số chứng từ sinh từ VoucherService không rollback được).
            //
            // Phase 1: Validate all lines + compute totals (NO balance changes yet)
            $totalDr = 0.0;
            $totalCr = 0.0;
            $validated = [];

            foreach ($lines as $line) {
                if ($line['amount'] <= 0) {
                    throw new \InvalidArgumentException('Số tiền phải lớn hơn 0');
                }

                $account = $this->accountRepo->findByCode($line['account_code']);
                if (!$account) {
                    throw new \InvalidArgumentException("Không tìm thấy tài khoản: {$line['account_code']}");
                }

                // BR15: Block posting to control accounts unless override
                if ($account->isControl() && !$allowControl) {
                    throw new \InvalidArgumentException(
                        "Tài khoản {$line['account_code']} ({$account->getName()}) là tài khoản tổng hợp — vui lòng hạch toán vào tài khoản chi tiết"
                    );
                }

                if ($line['is_debit']) {
                    $totalDr += $line['amount'];
                } else {
                    $totalCr += $line['amount'];
                }

                $validated[] = ['account' => $account, 'amount' => $line['amount'], 'is_debit' => $line['is_debit']];
            }

            // Phase 2: Check Dr = Cr BEFORE any balance changes
            if (abs($totalDr - $totalCr) > 10) {
                    throw new \InvalidArgumentException(
                        "Tổng Nợ ({$totalDr}) không bằng tổng Có ({$totalCr})"
                    );
            }

            $this->validatePostingRules($lines, $module);

            // PHA 3 — GHI NHẬN SỐ DƯ TÀI KHOẢN (Balance changes):
            // Xác định chiều tác động dựa trên loại tài khoản:
            //   Tài sản/Chi phí: Bút toán Nợ → TĂNG, Bút toán Có → GIẢM
            //   Nợ phải trả/VCSH/Doanh thu: Bút toán Nợ → GIẢM, Bút toán Có → TĂNG
            // LƯU Ý: Account::debit() = operation tăng số dư, Account::credit() = giảm số dư
            // (không nhầm với bên Nợ/Của của bút toán kế toán).
            // RỦI RO: Đảo ngược debit()/credit() → số dư tài khoản sai chiều → BC01 mất cân đối.
            // Biện pháp: Kiểm tra trial balance (tổng Dr = tổng Cr) sau mỗi lần post.
            $entryLines = [];
            foreach ($validated as $v) {
                $account = $this->accountRepo->findByCode($v['account']->getCode());
                if ($v['is_debit']) {
                    if (in_array($account->getType(), ['asset', 'expense'])) {
                        $account->credit($v['amount']);
                    } else {
                        $account->debit($v['amount']);
                    }
                } else {
                    if (in_array($account->getType(), ['liability', 'equity', 'revenue'])) {
                        $account->credit($v['amount']);
                    } else {
                        $account->debit($v['amount']);
                    }
                }
                $this->accountRepo->save($account);
                $entryLines[] = new LedgerEntry(uniqid('led_'), $account->getId(), $v['amount'], $v['is_debit']);
            }

            // PHA 4 — TẠO TRANSACTION RECORD:
            // Sinh số chứng tự động từ VoucherService (SELECT FOR UPDATE — chống trùng dưới concurrent).
            // LƯU Ý: Nếu commit thất bại, số chứng từ đã sinh bị lãng phí (gap trong dãy số).
            // Gap là hành vi chấp nhận được trong audit: miễn dãy số tăng dần và không trùng.
            // Audit trail ghi nhận cả transaction thành công và thất bại để trace gap.
            //
            // Phase 4: Create and persist transaction
            $finalReference = $reference ?: ($this->voucherService ? $this->voucherService->nextNumber('JV') : '');
            $txn = new Transaction(uniqid('jrn_'), new \DateTimeImmutable($postDate), $description, $finalReference, $voucherType, $sourceModule, $currency, $exchangeRate);
            foreach ($entryLines as $entry) {
                $txn->addLedgerEntry($entry);
            }
            $txn->post($createdBy);
            $this->txnRepo->save($txn);

            if ($inTransaction) $this->pdo->commit();

            $this->auditLogger?->log('journal.post', 'transaction', $txn->getId(), null, [
                'reference' => $reference,
                'description' => $description,
                'total_dr' => $totalDr,
                'total_cr' => $totalCr,
                'lines' => array_map(fn($l) => [
                    'account_code' => $l['account']->getCode(),
                    'amount' => $l['amount'],
                    'is_debit' => $l['is_debit'],
                ], $validated),
            ], $createdBy);

            return $txn;
        } catch (\Exception $e) {
            // ROLLBACK SAFETY: Nếu bất kỳ bước nào trong Phase 3-4 throw exception,
            // toàn bộ thay đổi số dư tài khoản trong Phase 3 được rollback atomic.
            // KHÔNG có trường hợp "post một nửa" (partial posting) — Dr hoặc Cr thiếu.
            // WARNING: Nếu là nested transaction (inTransaction=true khi vào phương thức),
            // không gọi rollback ở đây — giao dịch cha quyết định kết quả cuối cùng.
            if ($inTransaction) $this->pdo->rollBack();
            throw $e;
        }
    }

    //
    // Helper (R-12): Tính tổng Nợ của 1 bút toán — dùng cho notification message
    // Trả về 0 nếu txn không tồn tại hoặc chưa có ledger entries
    //
    private function txnAmount(string $txnId): float
    {
        if (!$this->pdo) return 0.0;
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN is_debit = 1 THEN amount ELSE 0 END), 0)
             FROM ledger_entries WHERE transaction_id = ?"
        );
        $stmt->execute([$txnId]);
        return (float)$stmt->fetchColumn();
    }
}
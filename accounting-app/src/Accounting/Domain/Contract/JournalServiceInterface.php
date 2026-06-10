<?php
namespace Accounting\Domain\Contract;

use Accounting\Domain\Model\Transaction;

/**
 * Giao diện dịch vụ bút toán kế toán (Journal Entry Service).
 *
 * Nghiệp vụ: Đây là service trung tâm của toàn bộ hệ thống kế toán.
 * Mọi nghiệp vụ phát sinh đều được ghi nhận dưới dạng bút toán kép
 * thông qua service này, đảm bảo nguyên tắc tổng Dr = tổng Cr.
 *
 * Vòng đời bút toán:
 *   Draft → Submitted → Approved → Posted
 *                      → Rejected  (từ chối)
 *                      → Returned  (trả lại để sửa)
 *
 * Các loại bút toán đặc biệt:
 *   - Bút toán bổ sung (supplementary): Điều chỉnh tăng/giảm số tiền
 *   - Bút toán đảo ngược (negative): Đảo ngược bút toán đã post
 *   - Bút toán điều chỉnh (adjusting): Chuyển số dư giữa các tài khoản
 *
 * Ràng buộc:
 *   - Mọi bút toán phải có tổng Nợ = tổng Có (±10 VND tolerance)
 *   - Không post vào tài khoản tổng hợp (control account) trừ khi allowControl = true
 *   - Kỳ kế toán đã đóng = read-only
 *   - Account code phải tồn tại trong hệ thống
 */
interface JournalServiceInterface
{
    /**
     * Tạo bút toán nháp (draft).
     *
     * Bút toán ở trạng thái draft có thể sửa hoặc xóa.
     * Chưa ảnh hưởng đến số dư tài khoản.
     *
     * @param string      $description  Diễn giải bút toán
     * @param string      $reference    Số chứng từ tham chiếu
     * @param array       $lines        Mảng các dòng bút toán [account_code, amount, is_debit]
     * @param string      $createdBy    Người tạo bút toán
     * @param bool        $allowControl Cho phép post vào tài khoản tổng hợp (mặc định false)
     * @param string|null $module       Module nghiệp vụ (cash, inventory, ap, ar, ...)
     * @param string|null $date         Ngày hạch toán (Y-m-d, null = ngày hiện tại)
     * @param string|null $voucherType  Loại chứng từ (PC, PT, JV, ...)
     * @param string|null $sourceModule Module nguồn tạo bút toán
     * @param string      $currency     Mã tiền tệ (mặc định VND)
     * @param float       $exchangeRate Tỷ giá quy đổi (mặc định 1.0)
     * @return Transaction              Đối tượng Transaction (trạng thái draft)
     * @throws \InvalidArgumentException Nếu Dr ≠ Cr, tài khoản không hợp lệ, hoặc kỳ đã đóng
     */
    public function createDraft(
        string $description, string $reference, array $lines, string $createdBy,
        bool $allowControl = false, ?string $module = null, ?string $date = null,
        ?string $voucherType = null, ?string $sourceModule = null,
        string $currency = 'VND', float $exchangeRate = 1.0
    ): Transaction;

    /**
     * Gửi bút toán nháp lên cấp duyệt (Draft → Submitted).
     *
     * @param string $txnId        Mã giao dịch bút toán
     * @param string $submittedBy  Người gửi duyệt
     * @return Transaction         Đối tượng Transaction (trạng thái submitted)
     * @throws \InvalidArgumentException Nếu bút toán không ở trạng thái draft
     */
    public function submitEntry(string $txnId, string $submittedBy): Transaction;

    /**
     * Phê duyệt bút toán (Submitted → Approved).
     *
     * @param string      $txnId      Mã giao dịch bút toán
     * @param string      $approverId Mã người phê duyệt
     * @param string|null $comment    Nhận xét phê duyệt
     * @return Transaction            Đối tượng Transaction (trạng thái approved)
     * @throws \InvalidArgumentException Nếu bút toán không ở trạng thái submitted
     */
    public function approveEntry(string $txnId, string $approverId, ?string $comment = null): Transaction;

    /**
     * Từ chối bút toán (Submitted → Rejected).
     *
     * @param string $txnId      Mã giao dịch bút toán
     * @param string $approverId Mã người từ chối
     * @param string $reason     Lý do từ chối
     * @return Transaction       Đối tượng Transaction (trạng thái rejected)
     * @throws \InvalidArgumentException Nếu bút toán không ở trạng thái submitted
     */
    public function rejectEntry(string $txnId, string $approverId, string $reason): Transaction;

    /**
     * Trả lại bút toán để sửa (Submitted → Draft).
     *
     * @param string      $txnId   Mã giao dịch bút toán
     * @param string      $userId  Mã người trả lại
     * @param string|null $comment Nhận xét lý do trả lại
     * @return Transaction         Đối tượng Transaction (trạng thái draft)
     * @throws \InvalidArgumentException Nếu bút toán không ở trạng thái submitted
     */
    public function returnEntry(string $txnId, string $userId, ?string $comment = null): Transaction;

    /**
     * Phê duyệt bút toán nháp (Draft → Posted) — duyệt nhanh bỏ qua bước submit.
     *
     * @param string $txnId      Mã giao dịch bút toán
     * @param string $approvedBy Mã người phê duyệt
     * @return Transaction       Đối tượng Transaction (trạng thái posted)
     * @throws \InvalidArgumentException Nếu bút toán không ở trạng thái draft
     */
    public function approveDraft(string $txnId, string $approvedBy): Transaction;

    /**
     * Sinh số chứng từ tự động theo prefix và năm hiện tại.
     *
     * Format: {PREFIX}{YYYY}-{000000}
     * Sử dụng SELECT ... FOR UPDATE để đảm bảo uniqueness dưới concurrent access.
     *
     * @param string $prefix Tiền tố chứng từ (PC, PT, JV, PNK, PXK, ...)
     * @return string        Số chứng từ đã sinh (VD: 'JV2025-000089')
     */
    public function generateVoucherNo(string $prefix = 'JV'): string;

    /**
     * Post bút toán trực tiếp (không qua draft → submit → approve).
     *
     * Dùng cho các nghiệp vụ tự động (bút toán kết chuyển, khấu hao, ...)
     * hoặc khi người dùng có quyền post trực tiếp.
     *
     * @param string      $description  Diễn giải bút toán
     * @param string      $reference    Số chứng từ tham chiếu
     * @param array       $lines        Mảng các dòng bút toán [account_code, amount, is_debit]
     * @param string      $createdBy    Người tạo bút toán
     * @param bool        $allowControl Cho phép post vào tài khoản tổng hợp (mặc định false)
     * @param string|null $module       Module nghiệp vụ
     * @param string|null $date         Ngày hạch toán (Y-m-d)
     * @param string|null $voucherType  Loại chứng từ
     * @param string|null $sourceModule Module nguồn
     * @param string      $currency     Mã tiền tệ (mặc định VND)
     * @param float       $exchangeRate Tỷ giá quy đổi (mặc định 1.0)
     * @return Transaction              Đối tượng Transaction (trạng thái posted)
     * @throws \InvalidArgumentException Nếu Dr ≠ Cr, tài khoản không hợp lệ, hoặc kỳ đã đóng
     */
    public function postEntry(
        string $description, string $reference, array $lines, string $createdBy,
        bool $allowControl = false, ?string $module = null, ?string $date = null,
        ?string $voucherType = null, ?string $sourceModule = null,
        string $currency = 'VND', float $exchangeRate = 1.0
    ): Transaction;

    /**
     * Tạo bút toán bổ sung cho bút toán đã post.
     *
     * Sử dụng khi cần điều chỉnh tăng/giảm số tiền của một bút toán đã post
     * mà không làm thay đổi bút toán gốc.
     *
     * @param string $originalTxnId Mã bút toán gốc
     * @param array  $correctLines  Mảng các dòng điều chỉnh
     * @param string $reason        Lý do bổ sung
     * @param string $createdBy     Người tạo bút toán
     * @param bool   $allowControl  Cho phép post vào tài khoản tổng hợp
     * @return Transaction          Đối tượng Transaction bổ sung (trạng thái posted)
     * @throws \InvalidArgumentException Nếu bút toán gốc không tồn tại hoặc chưa post
     */
    public function createSupplementaryEntry(
        string $originalTxnId, array $correctLines, string $reason,
        string $createdBy, bool $allowControl = false
    ): Transaction;

    /**
     * Tạo bút toán đảo ngược (negative entry) cho bút toán đã post.
     *
     * Nghiệp vụ: Khi phát hiện bút toán sai, thay vì xóa, hệ thống tạo
     * bút toán đảo ngược với các dòng Nợ/Có đảo chiều.
     * Bút toán gốc được đánh dấu reversedBy để audit trail.
     *
     * @param string $originalTxnId Mã bút toán gốc cần đảo ngược
     * @param string $reason        Lý do đảo ngược
     * @param string $createdBy     Người tạo bút toán
     * @param bool   $allowControl  Cho phép post vào tài khoản tổng hợp
     * @return Transaction          Đối tượng Transaction đảo ngược (trạng thái posted)
     * @throws \InvalidArgumentException Nếu bút toán gốc không tồn tại hoặc đã đảo ngược
     */
    public function createNegativeEntry(
        string $originalTxnId, string $reason, string $createdBy,
        bool $allowControl = false
    ): Transaction;

    /**
     * Tạo bút toán điều chỉnh chuyển số dư giữa các tài khoản.
     *
     * Sử dụng khi cần chuyển một phần số dư từ tài khoản này sang tài khoản khác
     * (ví dụ: kết chuyển chi phí, phân bổ doanh thu).
     *
     * @param string $originalTxnId Mã bút toán gốc
     * @param array  $movingLines   Mảng các dòng chuyển (from_account, to_account, amount)
     * @param string $reason        Lý do điều chỉnh
     * @param string $createdBy     Người tạo bút toán
     * @param bool   $allowControl  Cho phép post vào tài khoản tổng hợp
     * @return Transaction          Đối tượng Transaction điều chỉnh (trạng thái posted)
     * @throws \InvalidArgumentException Nếu bút toán gốc không tồn tại hoặc chưa post
     */
    public function createAdjustingEntry(
        string $originalTxnId, array $movingLines, string $reason,
        string $createdBy, bool $allowControl = false
    ): Transaction;

    /**
     * Lấy lịch sử điều chỉnh của một bút toán.
     *
     * Trả về danh sách các bút toán bổ sung, đảo ngược và điều chỉnh
     * liên quan đến bút toán gốc (dùng cho audit trail).
     *
     * @param string $transactionId Mã giao dịch bút toán gốc
     * @return array                Mảng các bút toán điều chỉnh (supplementary, negative, adjusting)
     */
    public function getCorrectionHistory(string $transactionId): array;
}

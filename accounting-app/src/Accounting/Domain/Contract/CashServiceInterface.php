<?php
namespace Accounting\Domain\Contract;

/**
 * Giao diện dịch vụ tiền mặt & tiền gửi ngân hàng.
 *
 * Nghiệp vụ: Xử lý các nghiệp vụ thu/chi tiền mặt (TK 111) và
 * tiền gửi ngân hàng (TK 112). Mọi giao dịch đều ghi nhận bút toán kép
 * thông qua JournalService để đảm bảo tổng Dr = tổng Cr.
 *
 * Hỗ trợ:
 *   - Thu tiền mặt, chi tiền mặt
 *   - Gửi tiền vào ngân hàng, rút tiền từ ngân hàng
 *   - Thu/chi qua ngân hàng, lãi ngân hàng, phí ngân hàng
 *   - Tiền đang chuyển (chưa xác nhận)
 *   - Ngoại tệ (FC) — tỷ giá, đánh giá lại cuối kỳ
 *   - Tách thuế GTGT (VAT) cho hóa đơn đầu vào/đầu ra
 */
interface CashServiceInterface
{
    /**
     * Ghi nhận phiếu thu tiền mặt (Nợ 111 / Có tài khoản đối ứng).
     *
     * @param float  $amount            Số tiền thu (VND)
     * @param string $creditAccountCode Mã tài khoản Có (đối ứng)
     * @param string $description       Diễn giải nghiệp vụ
     * @param string $reference         Số chứng từ tham chiếu
     * @param string $createdBy         Người tạo phiếu thu
     * @param float  $vatAmount         Số tiền thuế GTGT (mặc định 0)
     * @param float  $vatRate           Thuế suất GTGT (mặc định 0)
     * @return array                    Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function recordReceipt(
        float $amount, string $creditAccountCode, string $description,
        string $reference, string $createdBy,
        float $vatAmount = 0, float $vatRate = 0
    ): array;

    /**
     * Ghi nhận phiếu chi tiền mặt (Nợ tài khoản / Có 111).
     *
     * @param float  $amount           Số tiền chi (VND)
     * @param string $debitAccountCode Mã tài khoản Nợ (đối ứng)
     * @param string $description      Diễn giải nghiệp vụ
     * @param string $reference        Số chứng từ tham chiếu
     * @param string $createdBy        Người tạo phiếu chi
     * @param float  $vatAmount        Số tiền thuế GTGT (mặc định 0)
     * @param float  $vatRate          Thuế suất GTGT (mặc định 0)
     * @return array                   Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function recordPayment(
        float $amount, string $debitAccountCode, string $description,
        string $reference, string $createdBy,
        float $vatAmount = 0, float $vatRate = 0
    ): array;

    /**
     * Ghi nhận nghiệp vụ nộp tiền vào ngân hàng (Nợ 112 / Có 111).
     *
     * @param float  $amount      Số tiền nộp (VND)
     * @param string $description Diễn giải nghiệp vụ
     * @param string $reference   Số chứng từ tham chiếu
     * @param string $createdBy   Người tạo chứng từ
     * @return array              Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function recordBankDeposit(
        float $amount, string $description, string $reference, string $createdBy
    ): array;

    /**
     * Ghi nhận nghiệp vụ rút tiền từ ngân hàng (Nợ 111 / Có 112).
     *
     * @param float  $amount      Số tiền rút (VND)
     * @param string $description Diễn giải nghiệp vụ
     * @param string $reference   Số chứng từ tham chiếu
     * @param string $createdBy   Người tạo chứng từ
     * @return array              Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function recordBankWithdrawal(
        float $amount, string $description, string $reference, string $createdBy
    ): array;

    /**
     * Ghi nhận khoản thu qua ngân hàng (Nợ 112 / Có tài khoản đối ứng).
     *
     * @param float  $amount            Số tiền thu (VND)
     * @param string $creditAccountCode Mã tài khoản Có (đối ứng)
     * @param string $description       Diễn giải nghiệp vụ
     * @param string $reference         Số chứng từ tham chiếu
     * @param string $createdBy         Người tạo chứng từ
     * @param float  $vatAmount         Số tiền thuế GTGT (mặc định 0)
     * @param float  $vatRate           Thuế suất GTGT (mặc định 0)
     * @return array                    Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function recordBankReceipt(
        float $amount, string $creditAccountCode, string $description,
        string $reference, string $createdBy,
        float $vatAmount = 0, float $vatRate = 0
    ): array;

    /**
     * Ghi nhận khoản chi qua ngân hàng (Nợ tài khoản / Có 112).
     *
     * @param float  $amount           Số tiền chi (VND)
     * @param string $debitAccountCode Mã tài khoản Nợ (đối ứng)
     * @param string $description      Diễn giải nghiệp vụ
     * @param string $reference        Số chứng từ tham chiếu
     * @param string $createdBy        Người tạo chứng từ
     * @param float  $vatAmount        Số tiền thuế GTGT (mặc định 0)
     * @param float  $vatRate          Thuế suất GTGT (mặc định 0)
     * @return array                   Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function recordBankPayment(
        float $amount, string $debitAccountCode, string $description,
        string $reference, string $createdBy,
        float $vatAmount = 0, float $vatRate = 0
    ): array;

    /**
     * Ghi nhận lãi tiền gửi ngân hàng (Nợ 112 / Có 515).
     *
     * @param float  $amount      Số tiền lãi (VND)
     * @param string $description Diễn giải nghiệp vụ
     * @param string $reference   Số chứng từ tham chiếu
     * @param string $createdBy   Người tạo chứng từ
     * @return array              Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function recordBankInterest(
        float $amount, string $description, string $reference, string $createdBy
    ): array;

    /**
     * Ghi nhận phí dịch vụ ngân hàng (Nợ 642 / Có 112), có thể kèm VAT.
     *
     * @param float  $amount      Số tiền phí (VND)
     * @param string $description Diễn giải nghiệp vụ
     * @param string $reference   Số chứng từ tham chiếu
     * @param string $createdBy   Người tạo chứng từ
     * @param float  $vatAmount   Số tiền thuế GTGT (mặc định 0)
     * @param float  $vatRate     Thuế suất GTGT (mặc định 0)
     * @return array              Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function recordBankCharge(
        float $amount, string $description, string $reference, string $createdBy,
        float $vatAmount = 0, float $vatRate = 0
    ): array;

    /**
     * Ghi nhận tiền đang chuyển (Nợ 113 / Có 111).
     *
     * @param float  $amount      Số tiền đang chuyển (VND)
     * @param string $description Diễn giải nghiệp vụ
     * @param string $reference   Số chứng từ tham chiếu
     * @param string $createdBy   Người tạo chứng từ
     * @return array              Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function recordTransit(
        float $amount, string $description, string $reference, string $createdBy
    ): array;

    /**
     * Xác nhận tiền đang chuyển đã đến tài khoản ngân hàng (Nợ 112 / Có 113).
     *
     * @param string $transitId Mã giao dịch tiền đang chuyển
     * @param string $createdBy Người xác nhận
     * @return array            Thông tin bút toán đã ghi nhận
     */
    public function confirmTransit(string $transitId, string $createdBy): array;

    /**
     * Đảo ngược nghiệp vụ tiền đang chuyển (Nợ 111 / Có 113).
     *
     * @param string $transitId Mã giao dịch tiền đang chuyển
     * @param string $createdBy Người đảo ngược
     * @return array            Thông tin bút toán đã ghi nhận
     */
    public function reverseTransit(string $transitId, string $createdBy): array;

    /**
     * Lấy sổ quỹ tiền mặt/sổ phụ ngân hàng trong khoảng thời gian.
     *
     * @param string|null $fromDate Ngày bắt đầu (Y-m-d, null = đầu kỳ)
     * @param string|null $toDate   Ngày kết thúc (Y-m-d, null = cuối kỳ)
     * @return array                Dữ liệu sổ quỹ/sổ phụ
     */
    public function getCashBook(?string $fromDate = null, ?string $toDate = null): array;

    /**
     * Ghi nhận thu tiền mặt bằng ngoại tệ (Nợ 1112 / Có tài khoản đối ứng).
     *
     * @param float  $fcAmount        Số tiền ngoại tệ
     * @param string $creditAccountCode Mã tài khoản Có (đối ứng)
     * @param string $currencyCode    Mã tiền tệ (USD, EUR, ...)
     * @param float  $exchangeRate    Tỷ giá quy đổi tại thời điểm ghi nhận
     * @param string $description     Diễn giải nghiệp vụ
     * @param string $reference       Số chứng từ tham chiếu
     * @param string $createdBy       Người tạo chứng từ
     * @return array                  Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function recordReceiptFC(
        float $fcAmount, string $creditAccountCode, string $currencyCode,
        float $exchangeRate, string $description, string $reference, string $createdBy
    ): array;

    /**
     * Ghi nhận chi tiền mặt bằng ngoại tệ (Nợ tài khoản / Có 1112).
     *
     * @param float  $fcAmount       Số tiền ngoại tệ
     * @param string $debitAccountCode Mã tài khoản Nợ (đối ứng)
     * @param string $currencyCode   Mã tiền tệ (USD, EUR, ...)
     * @param float  $exchangeRate   Tỷ giá quy đổi tại thời điểm ghi nhận
     * @param string $description    Diễn giải nghiệp vụ
     * @param string $reference      Số chứng từ tham chiếu
     * @param string $createdBy      Người tạo chứng từ
     * @return array                 Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function recordPaymentFC(
        float $fcAmount, string $debitAccountCode, string $currencyCode,
        float $exchangeRate, string $description, string $reference, string $createdBy
    ): array;

    /**
     * Lấy số dư tài khoản ngoại tệ.
     *
     * @return array Danh sách số dư ngoại tệ theo từng loại tiền
     */
    public function getFCBalances(): array;

    /**
     * Đánh giá lại số dư ngoại tệ theo tỷ giá cuối kỳ.
     *
     * Nghiệp vụ: Cuối kỳ kế toán, doanh nghiệp phải đánh giá lại các
     * khoản mục tiền tệ có gốc ngoại tệ theo tỷ giá cuối kỳ.
     * Chênh lệch tỷ giá được hạch toán vào TK 413 (chênh lệch tỷ giá).
     *
     * @param string $accountCode Mã tài khoản (1112, 1122, ...)
     * @param string $currencyCode Mã tiền tệ
     * @param float  $closingRate Tỷ giá cuối kỳ
     * @param string $asOfDate    Ngày đánh giá lại (Y-m-d)
     * @param string $createdBy   Người thực hiện
     * @return array              Kết quả đánh giá lại (chênh lệch tăng/giảm)
     */
    public function revalueFC(
        string $accountCode, string $currencyCode, float $closingRate,
        string $asOfDate, string $createdBy
    ): array;
}

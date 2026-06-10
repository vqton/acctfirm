<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\CashServiceInterface;
use Accounting\Domain\Contract\JournalServiceInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;

/**
 * Quản lý thu chi tiền mặt và tiền gửi ngân hàng.
 *
 * NGHIỆP VỤ:
 *   - Thu tiền mặt (recordReceipt): Nợ 111 / Có TK đối ứng
 *   - Chi tiền mặt (recordPayment): Nợ TK đối ứng / Có 111
 *   - Thu tiền gửi NH (recordBankReceipt): Nợ 112 / Có TK đối ứng
 *   - Chi tiền gửi NH (recordBankPayment): Nợ TK đối ứng / Có 112
 *   - Phí ngân hàng (recordBankCharge): Nợ 642 / Có 112
 *   - Lãi tiền gửi (recordBankInterest): Nợ 112 / Có 515
 *
 * Tất cả giao dịch đều đi qua JournalService để đảm bảo Dr=Cr và posting rules.
 * Hỗ trợ tách VAT: vatAmount>0 → tự động tạo dòng VAT (133/3331).
 * RỦI RO: Quy trình thu chi phải có chứng từ gốc đầy đủ (phiếu thu/chi, UNC, séc).
 *
 * @package Accounting\Domain\Service
 */
class CashService implements CashServiceInterface
{
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;
    private ?\PDO $pdo;
    private JournalServiceInterface $journal;

    public function __construct(
        AccountRepositoryInterface $accountRepo,
        TransactionRepositoryInterface $txnRepo,
        JournalServiceInterface $journal,
        ?\PDO $pdo = null
    ) {
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
        $this->journal = $journal;
        $this->pdo = $pdo;
    }

    /**
     * THU TIỀN MẶT — Nợ 111 / Có TK đối ứng.
     *
     * NGHIỆP VỤ: Ghi nhận khoản thu tiền mặt tại quỹ.
     * - Nợ 111 (Tiền mặt Việt Nam): tăng tiền mặt tại quỹ
     * - Có TK đối ứng (theo creditAccountCode): giảm công nợ phải thu (131), thu hồi
     *   tạm ứng (141), ghi nhận doanh thu (511), vay nợ (341), nhận vốn góp (411),...
     *
     * Ảnh hưởng BC01: chỉ tiêu "Tiền mặt" (Mã số 111) tăng.
     * RỦI RO: Nếu creditAccountCode là 511 hoặc 515, bắt buộc đã xuất hóa đơn GTGT
     * trước khi ghi nhận (Điều 8 Thông tư 219/2013/TT-BTC).
     * Audit trail: Số chứng từ bắt buộc có tiền tố PT (Phiếu thu), kèm chứng từ gốc.
     *
     * @param float $amount Số tiền thu (>0)
     * @param string $creditAccountCode TK Có (đối ứng)
     * @param string $description Nội dung thu
     * @param string $reference Số chứng từ (PTYYYY-NNNNNN)
     * @param string $createdBy ID người tạo
     * @param float $vatAmount Số tiền VAT (0 nếu không có)
     * @param float $vatRate Thuế suất VAT (%)
     * @return array{transaction_id:string, reference:string, total_amount:float, lines:array}
     * @throws \InvalidArgumentException Nếu amount<=0 hoặc sai tài khoản
     */
    public function recordReceipt(float $amount, string $creditAccountCode, string $description, string $reference, string $createdBy, float $vatAmount = 0, float $vatRate = 0): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Số tiền phải lớn hơn 0.');

        $creditAccount = $this->accountRepo->findByCode($creditAccountCode);
        if (!$creditAccount) throw new \InvalidArgumentException("Không tìm thấy tài khoản: {$creditAccountCode}");

        // NGHIỆP VỤ THU TIỀN CÓ VAT:
        // Nếu vatAmount > 0 → tách thành doanh thu chưa thuế và VAT đầu ra:
        //   Nợ 111 (tổng tiền)
        //   Có creditAccount (tiền chưa thuế)
        //   Có 33311 (VAT đầu ra phải nộp)
        // Nếu vatAmount = 0 → ghi nhận thẳng (thu hồi công nợ, vốn góp, ...)
        $lines = ($vatAmount > 0)
            ? [
                ['account_code' => '111', 'amount' => $amount, 'is_debit' => true],
                ['account_code' => $creditAccountCode, 'amount' => $amount - $vatAmount, 'is_debit' => false],
                ['account_code' => '33311', 'amount' => $vatAmount, 'is_debit' => false],
            ]
            : [
                ['account_code' => '111', 'amount' => $amount, 'is_debit' => true],
                ['account_code' => $creditAccountCode, 'amount' => $amount, 'is_debit' => false],
            ];

        $txn = $this->journal->postEntry("Cash receipt: {$description}", $reference, $lines, $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'vat_amount' => $vatAmount, 'type' => 'receipt'];
    }

    /**
     * CHI TIỀN MẶT — Nợ TK đối ứng / Có 111.
     *
     * NGHIỆP VỤ: Ghi nhận khoản chi tiền mặt từ quỹ.
     * - Nợ TK đối ứng: chi phí quản lý (642), mua hàng (156/152), tạm ứng (141),
     *   đầu tư TSCĐ (211/241), thanh toán nợ (331),...
     * - Có 111 (Tiền mặt Việt Nam): giảm tiền mặt tại quỹ
     *
     * Kiểm tra số dư: Bắt buộc đảm bảo quỹ tiền mặt đủ chi trước khi hạch toán
     * (theo nguyên tắc thận trọng kế toán — Điều 5 Thông tư 99).
     * RỦI RO: Khoản chi tiền mặt > 20 triệu VND phải thực hiện qua chuyển khoản
     * (theo quy định giao dịch thanh toán của NHNN).
     * Audit trail: Số chứng từ bắt buộc có tiền tố PC (Phiếu chi), kèm chứng từ gốc.
     *
     * @param float $amount Số tiền chi (>0)
     * @param string $debitAccountCode TK Nợ (đối ứng)
     * @param string $description Nội dung chi
     * @param string $reference Số phiếu chi (PCYYYY-NNNNNN)
     * @param string $createdBy ID người tạo
     * @param float $vatAmount Số tiền VAT (0 nếu không có)
     * @param float $vatRate Thuế suất VAT (%)
     * @return array
     * @throws \InvalidArgumentException Nếu amount<=0, sai TK, hoặc số dư không đủ
     */
    public function recordPayment(float $amount, string $debitAccountCode, string $description, string $reference, string $createdBy, float $vatAmount = 0, float $vatRate = 0): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Số tiền phải lớn hơn 0.');

        $debitAccount = $this->accountRepo->findByCode($debitAccountCode);
        if (!$debitAccount) throw new \InvalidArgumentException("Không tìm thấy tài khoản: {$debitAccountCode}");

        // KIỂM TRA SỐ DƯ TIỀN MẶT: Duy trì nguyên tắc thận trọng (prudence concept) —
        // không được ghi nhận chi tiêu vượt quá số dư hiện có (trừ khi có thỏa thuận thấu chi).
        // RỦI RO CONCURRENCY: Đây là read-before-write không atomic. Giữa lúc đọc balance
        // và journal::postEntry, một request khác có thể đã tiêu số tiền này.
        // Biện pháp: JournalService::postEntry chạy trong DB transaction — nếu số dư âm
        // do race condition, DB constraint (CHECK balance >= 0) sẽ từ chối và rollback.
        // Tuy nhiên, constraint này phụ thuộc vào implementation của AccountRepository.
        $cash = $this->accountRepo->findByCode('111');
        if ($cash && $cash->getBalance() < $amount) {
            throw new \InvalidArgumentException("Số dư tiền mặt không đủ: hiện có {$cash->getBalance()}, cần {$amount}");
        }

        // NGHIỆP VỤ CHI TIỀN CÓ VAT:
        // Nếu vatAmount > 0 → tách thành chi phí chưa thuế và VAT đầu vào:
        //   Nợ debitAccount (tiền chưa thuế)
        //   Nợ 1331 (VAT đầu vào được khấu trừ)
        //   Có 111 (tổng tiền)
        // Nếu vatAmount = 0 → ghi nhận thẳng (trả nợ NCC, tạm ứng, ...)
        $lines = ($vatAmount > 0)
            ? [
                ['account_code' => $debitAccountCode, 'amount' => $amount - $vatAmount, 'is_debit' => true],
                ['account_code' => '1331', 'amount' => $vatAmount, 'is_debit' => true],
                ['account_code' => '111', 'amount' => $amount, 'is_debit' => false],
            ]
            : [
                ['account_code' => $debitAccountCode, 'amount' => $amount, 'is_debit' => true],
                ['account_code' => '111', 'amount' => $amount, 'is_debit' => false],
            ];

        $txn = $this->journal->postEntry("Cash payment: {$description}", $reference, $lines, $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'vat_amount' => $vatAmount, 'type' => 'payment'];
    }

    /**
     * Nộp tiền mặt vào ngân hàng — Nợ 112 / Có 111.
     *
     * NGHIỆP VỤ: Chuyển đổi hình thức nắm giữ tiền, không làm thay đổi tổng tài sản.
     * - Nợ 112 (Tiền gửi ngân hàng): tăng số dư tài khoản ngân hàng
     * - Có 111 (Tiền mặt): giảm tiền mặt tại quỹ
     *
     * Ảnh hưởng BC01: "Tiền mặt" giảm, "Tiền gửi NH" tăng cùng số tiền.
     * RỦI RO: Cần đối chiếu với sao kê ngân hàng (bank statement) để phát hiện chênh lệch.
     *
     * @param float $amount Số tiền nộp
     * @param string $description Nội dung
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu số dư tiền mặt không đủ
     */
    public function recordBankDeposit(float $amount, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Số tiền phải lớn hơn 0.');

        $cash = $this->accountRepo->findByCode('111');
        if ($cash && $cash->getBalance() < $amount) {
            throw new \InvalidArgumentException("Số dư tiền mặt không đủ: hiện có {$cash->getBalance()}, cần {$amount}");
        }

        
        $txn = $this->journal->postEntry("Bank deposit: {$description}", $reference, [
            ['account_code' => '112', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '111', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'type' => 'bank_deposit'];
    }

    /**
     * Rút tiền ngân hàng về quỹ — Nợ 111 / Có 112.
     *
     * NGHIỆP VỤ: Chuyển đổi hình thức nắm giữ tiền, không làm thay đổi tổng tài sản.
     * - Nợ 111 (Tiền mặt): tăng tiền mặt tại quỹ
     * - Có 112 (Tiền gửi ngân hàng): giảm số dư tài khoản ngân hàng
     *
     * RỦI RO: Cần kiểm tra hạn mức rút tiền và chữ ký ủy quyền theo quy định ngân hàng.
     *
     * @param float $amount Số tiền rút
     * @param string $description Nội dung
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     */
    public function recordBankWithdrawal(float $amount, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Số tiền phải lớn hơn 0.');

        $bank = $this->accountRepo->findByCode('112');
        if ($bank && $bank->getBalance() < $amount) {
            throw new \InvalidArgumentException("Số dư ngân hàng không đủ: hiện có {$bank->getBalance()}, cần {$amount}");
        }

        
        $txn = $this->journal->postEntry("Bank withdrawal: {$description}", $reference, [
            ['account_code' => '111', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '112', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'type' => 'bank_withdrawal'];
    }

    /**
     * THU TIỀN QUA NGÂN HÀNG — Nợ 112 / Có TK đối ứng.
     *
     * NGHIỆP VỤ: Khách hàng trả tiền qua chuyển khoản.
     * - Nợ 112 (Tiền gửi ngân hàng): tăng số dư tài khoản ngân hàng
     * - Có TK đối ứng: giảm công nợ phải thu (131) hoặc ghi nhận doanh thu (511)
     *
     * Ảnh hưởng BC01: "Tiền gửi NH" tăng.
     * Audit trail: Bắt buộc đối chiếu với sao kê ngân hàng, lưu giấy báo Có.
     *
     * @param float $amount Số tiền thu
     * @param string $creditAccountCode TK Có (đối ứng)
     * @param string $description Nội dung
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @param float $vatAmount Số tiền VAT (0 nếu không có)
     * @param float $vatRate Thuế suất VAT (%)
     * @return array
     */
    public function recordBankReceipt(float $amount, string $creditAccountCode, string $description, string $reference, string $createdBy, float $vatAmount = 0, float $vatRate = 0): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Số tiền phải lớn hơn 0.');

        $creditAccount = $this->accountRepo->findByCode($creditAccountCode);
        if (!$creditAccount) throw new \InvalidArgumentException("Không tìm thấy tài khoản: {$creditAccountCode}");

        // NGHIỆP VỤ THU TIỀN QUA NH CÓ VAT:
        // Nếu vatAmount > 0 → tách: Nợ 112 (tổng) / Có creditAccount (net) + Có 33311 (VAT)
        $lines = ($vatAmount > 0)
            ? [
                ['account_code' => '112', 'amount' => $amount, 'is_debit' => true],
                ['account_code' => $creditAccountCode, 'amount' => $amount - $vatAmount, 'is_debit' => false],
                ['account_code' => '33311', 'amount' => $vatAmount, 'is_debit' => false],
            ]
            : [
                ['account_code' => '112', 'amount' => $amount, 'is_debit' => true],
                ['account_code' => $creditAccountCode, 'amount' => $amount, 'is_debit' => false],
            ];

        $txn = $this->journal->postEntry("Bank receipt: {$description}", $reference, $lines, $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'vat_amount' => $vatAmount, 'type' => 'bank_receipt'];
    }

    /**
     * CHI TIỀN QUA NGÂN HÀNG — Nợ TK đối ứng / Có 112.
     *
     * NGHIỆP VỤ: Thanh toán nhà cung cấp (331), chi phí (642), mua TSCĐ (211),...
     * - Nợ TK đối ứng: giảm công nợ hoặc ghi nhận chi phí
     * - Có 112 (Tiền gửi ngân hàng): giảm số dư tài khoản ngân hàng
     *
     * Ảnh hưởng BC01: "Tiền gửi NH" giảm.
     * RỦI RO: Kiểm tra thụ hưởng và tài khoản đích để tránh chuyển nhầm.
     * Audit trail: Bắt buộc lưu ủy nhiệm chi / lệnh chuyển tiền.
     *
     * @param float $amount Số tiền chi
     * @param string $debitAccountCode TK Nợ (đối ứng)
     * @param string $description Nội dung
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @param float $vatAmount Số tiền VAT (0 nếu không có)
     * @param float $vatRate Thuế suất VAT (%)
     * @return array
     */
    public function recordBankPayment(float $amount, string $debitAccountCode, string $description, string $reference, string $createdBy, float $vatAmount = 0, float $vatRate = 0): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Số tiền phải lớn hơn 0.');

        $debitAccount = $this->accountRepo->findByCode($debitAccountCode);
        if (!$debitAccount) throw new \InvalidArgumentException("Không tìm thấy tài khoản: {$debitAccountCode}");

        $bank = $this->accountRepo->findByCode('112');
        if ($bank && $bank->getBalance() < $amount) {
            throw new \InvalidArgumentException("Số dư ngân hàng không đủ: hiện có {$bank->getBalance()}, cần {$amount}");
        }

        // NGHIỆP VỤ CHI TIỀN QUA NH CÓ VAT:
        // Nếu vatAmount > 0 → tách: Nợ debitAccount (net) + Nợ 1331 (VAT) / Có 112 (tổng)
        $lines = ($vatAmount > 0)
            ? [
                ['account_code' => $debitAccountCode, 'amount' => $amount - $vatAmount, 'is_debit' => true],
                ['account_code' => '1331', 'amount' => $vatAmount, 'is_debit' => true],
                ['account_code' => '112', 'amount' => $amount, 'is_debit' => false],
            ]
            : [
                ['account_code' => $debitAccountCode, 'amount' => $amount, 'is_debit' => true],
                ['account_code' => '112', 'amount' => $amount, 'is_debit' => false],
            ];

        $txn = $this->journal->postEntry("Bank payment: {$description}", $reference, $lines, $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'vat_amount' => $vatAmount, 'type' => 'bank_payment'];
    }

    /**
     * Hạch toán lãi ngân hàng — Nợ 112 / Có 515.
     *
     * NGHIỆP VỤ: Ngân hàng trả lãi tiền gửi, lãi nhập gốc.
     * - Nợ 112 (Tiền gửi ngân hàng): tăng số dư
     * - Có 515 (Doanh thu hoạt động tài chính): ghi nhận doanh thu lãi
     *
     * Ảnh hưởng BC02: chỉ tiêu "Doanh thu HĐTC" (Mã số 21) tăng.
     * RỦI RO: Lãi ngân hàng thường có chứng từ là sao kê, không có hóa đơn GTGT.
     * Không phải kê khai thuế GTGT đầu ra cho khoản lãi này.
     *
     * @param float $amount Số tiền lãi
     * @param string $description Nội dung
     * @param string $reference Số chứng từ (sao kê NH)
     * @param string $createdBy ID người tạo
     * @return array
     */
    public function recordBankInterest(float $amount, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Số tiền phải lớn hơn 0.');

        
        $txn = $this->journal->postEntry("Bank interest: {$description}", $reference, [
            ['account_code' => '112', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '515', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'type' => 'bank_interest'];
    }

    /**
     * Hạch toán phí ngân hàng — Nợ 642 / Có 112.
     *
     * NGHIỆP VỤ: Ngân hàng thu phí dịch vụ (duy trì tài khoản, chuyển tiền,...).
     * - Nợ 642 (Chi phí quản lý doanh nghiệp): chi tiết "Phí ngân hàng"
     * - Có 112 (Tiền gửi ngân hàng): ngân hàng tự động trừ phí
     *
     * Ảnh hưởng BC02: "Chi phí QLDN" (Mã số 25) tăng → LNTT giảm → Thuế TNDN giảm.
     * RỦI RO: Phí ngân hàng thường không có hóa đơn GTGT, nếu có hóa đơn thì phải
     * tách VAT đầu vào: Nợ 1331 / Có 112 (phần VAT).
     *
     * @param float $amount Số tiền phí
     * @param string $description Nội dung
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @param float $vatAmount Số tiền VAT (0 nếu không có)
     * @param float $vatRate Thuế suất VAT (%)
     * @return array
     */
    public function recordBankCharge(float $amount, string $description, string $reference, string $createdBy, float $vatAmount = 0, float $vatRate = 0): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Số tiền phải lớn hơn 0.');

        $bank = $this->accountRepo->findByCode('112');
        if ($bank && $bank->getBalance() < $amount) {
            throw new \InvalidArgumentException("Số dư ngân hàng không đủ: hiện có {$bank->getBalance()}, cần {$amount}");
        }

        // NGHIỆP VỤ PHÍ NGÂN HÀNG CÓ VAT:
        // Nếu vatAmount > 0 → tách: Nợ 642 (net) + Nợ 1331 (VAT) / Có 112 (tổng)
        $lines = ($vatAmount > 0)
            ? [
                ['account_code' => '642', 'amount' => $amount - $vatAmount, 'is_debit' => true],
                ['account_code' => '1331', 'amount' => $vatAmount, 'is_debit' => true],
                ['account_code' => '112', 'amount' => $amount, 'is_debit' => false],
            ]
            : [
                ['account_code' => '642', 'amount' => $amount, 'is_debit' => true],
                ['account_code' => '112', 'amount' => $amount, 'is_debit' => false],
            ];

        $txn = $this->journal->postEntry("Bank charge: {$description}", $reference, $lines, $createdBy);

        return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'vat_amount' => $vatAmount, 'type' => 'bank_charge'];
    }

    /**
     * Tiền đang chuyển — Nợ 113 / Có 111.
     *
     * NGHIỆP VỤ: Tiền đã rời quỹ nhưng chưa vào tài khoản ngân hàng.
     * - Nợ 113 (Tiền đang chuyển): tăng số dư tiền đang chuyển
     * - Có 111 (Tiền mặt): giảm tiền mặt tại quỹ
     *
     * Bản chất: Tài khoản trung gian để phản ánh khoản tiền đang trong quá trình luân chuyển.
     * Sử dụng khi: nộp tiền mặt vào NH nhưng chưa có sao kê xác nhận,
     * hoặc chuyển tiền giữa các NH chưa về tài khoản đích.
     * Ảnh hưởng BC01: "Tiền mặt" giảm, "Tiền đang chuyển" (Mã số 112) tăng.
     * RỦI RO: Nếu tiền đang chuyển tồn đọng lâu (> 3 ngày), cần kiểm tra thực tế.
     *
     * @param float $amount Số tiền
     * @param string $description Nội dung
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     */
    public function recordTransit(float $amount, string $description, string $reference, string $createdBy): array
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Số tiền phải lớn hơn 0.');

        $cash = $this->accountRepo->findByCode('111');
        if ($cash && $cash->getBalance() < $amount) {
            throw new \InvalidArgumentException("Số dư tiền mặt không đủ: hiện có {$cash->getBalance()}, cần {$amount}");
        }

        
        $txn = $this->journal->postEntry("Cash in transit: {$description}", $reference, [
            ['account_code' => '113', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '111', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        // RỦI RO TOÀN VẸN: record cash_transit ĐỨNG NGOÀI DB transaction của journal::postEntry.
        // Nếu journal post thành công (balance cập nhật, transaction record ghi) nhưng
        // INSERT cash_transit thất bại → tiền đã giảm trên sổ cái nhưng không có dấu vết đi đường.
        // Ngược lại, nếu journal fail mà cash_transit ghi thành công → có transit record ảo.
        // Biện pháp hiện tại: Không có transaction bao bọc (do journal đã tự commit bên trong).
        // TODO: Cần wrap cả journal và cash_transit trong một DB transaction duy nhất.
        $transitId = uniqid('tr_');
        if ($this->pdo) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO cash_transit (id, amount, source_account, destination_account, description, reference, status, transit_date, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)'
            );
            $stmt->execute([$transitId, $amount, '111', '112', $description, $reference, 'in_transit', $createdBy]);
        }

        return ['transaction_id' => $txn->getId(), 'transit_id' => $transitId, 'amount' => $amount, 'type' => 'transit'];
    }

    // ── Confirm Transit (Dr 112 — Cr 113) ──
    //
    // NGHIỆP VỤ XÁC NHẬN TIỀN ĐÃ VỀ TÀI KHOẢN NGÂN HÀNG:
    // - Nợ 112 (Tiền gửi ngân hàng): tiền đã vào tài khoản
    // - Có 113 (Tiền đang chuyển): kết chuyển hết số dư tạm thời
    //
    // Thực hiện khi: nhận được sao kê ngân hàng xác nhận số tiền đã về.
    // Ảnh hưởng BC01: "Tiền đang chuyển" giảm, "Tiền gửi NH" tăng.
    // RỦI RO: Chỉ xác nhận khi có chứng từ sao kê NH, không xác nhận trước.

    public function confirmTransit(string $transitId, string $createdBy): array
    {
        if (!$this->pdo) {
            throw new \RuntimeException('PDO không khả dụng cho theo dõi tiền đang chuyển');
        }

        $stmt = $this->pdo->prepare('SELECT amount FROM cash_transit WHERE id = ? AND status = ?');
        $stmt->execute([$transitId, 'in_transit']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \InvalidArgumentException("Không tìm thấy bản ghi tiền đang chuyển hoặc đã được xử lý: {$transitId}");
        }

        $amount = (float)$row['amount'];
        
        $txn = $this->journal->postEntry("Transit confirmed: bank credited", "CNF-{$transitId}", [
            ['account_code' => '112', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '113', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        $this->pdo->prepare(
            'UPDATE cash_transit SET status=?, confirm_date=CURDATE() WHERE id=?'
        )->execute(['confirmed', $transitId]);

        return ['transaction_id' => $txn->getId(), 'transit_id' => $transitId, 'type' => 'transit_confirm'];
    }

    // ── Reverse Transit (Dr 111 — Cr 113) ──
    //
    // NGHIỆP VỤ HOÀN NHẬP TIỀN ĐANG CHUYỂN:
    // - Nợ 111 (Tiền mặt): tiền chưa chuyển được, trả lại quỹ
    // - Có 113 (Tiền đang chuyển): xóa khoản tạm thời
    //
    // Thực hiện khi: giao dịch chuyển tiền bị thất bại hoặc hủy bỏ,
    // tiền chưa thực sự được chuyển đến ngân hàng.
    // RỦI RO: Nếu đã confirmTransit thì không được reverse — dữ liệu đã đồng bộ với sao kê NH.

    public function reverseTransit(string $transitId, string $createdBy): array
    {

        if ($this->pdo) {
            $stmt = $this->pdo->prepare('SELECT amount FROM cash_transit WHERE id = ? AND status = ?');
            $stmt->execute([$transitId, 'in_transit']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $amount = (float)$row['amount'];
                $this->pdo->prepare(
                    'UPDATE cash_transit SET status=? WHERE id=?'
                )->execute(['reversed', $transitId]);

                $txn = $this->journal->postEntry("Transit reversed", "REV-{$transitId}", [
                    ['account_code' => '111', 'amount' => $amount, 'is_debit' => true],
                    ['account_code' => '113', 'amount' => $amount, 'is_debit' => false],
                ], $createdBy);

                return ['transaction_id' => $txn->getId(), 'transit_id' => $transitId, 'type' => 'transit_reverse'];
            }
            throw new \InvalidArgumentException("Không tìm thấy bản ghi tiền đang chuyển hoặc đã được xử lý: {$transitId}");
        }

        throw new \RuntimeException('PDO không khả dụng cho theo dõi tiền đang chuyển');
    }

    // ── Cash Book (computed view of TK 111 ledger entries with running balance) ──
    //
    // SỔ QUỸ TIỀN MẶT:
    // Xuất bảng kê chi tiết các phát sinh Nợ/Có TK 111 kèm số dư lũy kế.
    // Tương ứng Sổ quỹ tiền mặt (Mẫu S06a-TN) theo quy định tại Thông tư 99.
    //
    // Cột Thu = Nợ 111 (ghi nhận khi nhập quỹ)
    // Cột Chi = Có 111 (ghi nhận khi xuất quỹ)
    // Số dư cuối = Số dư đầu + Tổng Thu - Tổng Chi
    //
    // RỦI RO: Số dư cuối kỳ trên sổ quỹ phải khớp với kiểm kê quỹ thực tế.
    // Nếu chênh lệch → phải lập Biên bản kiểm kê quỹ và điều chỉnh (Nợ/Có 111 và 1381/3381).

    public function getCashBook(string $fromDate = null, string $toDate = null): array
    {
        if (!$this->pdo) {
            throw new \RuntimeException('PDO không khả dụng cho sổ quỹ tiền mặt');
        }

        $sql = "SELECT t.id, t.description, t.reference, t.created_at, le.amount, le.is_debit
                FROM ledger_entries le
                JOIN transactions t ON t.id = le.transaction_id
                JOIN accounts a ON a.id = le.account_id
                WHERE a.code = '111'";
        $params = [];
        if ($fromDate) {
            $sql .= " AND t.created_at >= ?";
            $params[] = $fromDate;
        }
        if ($toDate) {
            $sql .= " AND t.created_at <= ?";
            $params[] = $toDate . ' 23:59:59';
        }
        $sql .= " ORDER BY t.created_at ASC, t.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // TÍNH SỐ DƯ LŨY KẾ (running balance):
        // Cột Thu = Nợ 111 (is_debit=1) → cộng vào running
        // Cột Chi = Có 111 (is_debit=0) → trừ từ running
        // Công thức: Số dư cuối = Số dư đầu + Tổng Thu - Tổng Chi
        //
        // ĐỘ CHÍNH XÁC: running balance được tính bằng PHP float — có thể mất precision
        // với số lượng lớn giao dịch (10.000+ dòng). Sai số lũy kế có thể lên đến vài đồng.
        // Biện pháp: Đối chiếu định kỳ với số dư TK 111 trên sổ cái (GL balance).
        // RỦI RO: Nếu có transaction được post sau khi getCashBook được gọi, dữ liệu
        // trả về có thể không nhất quán (dirty read nếu transaction chưa commit).
        $running = 0.0;
        $entries = [];
        foreach ($rows as $r) {
            $amt = (float)$r['amount'];
            if ($r['is_debit']) {
                $running += $amt;
            } else {
                $running -= $amt;
            }
            $entries[] = [
                'date' => $r['created_at'],
                'reference' => $r['reference'],
                'description' => $r['description'],
                'receipt_amount' => $r['is_debit'] ? $amt : 0,
                'payment_amount' => $r['is_debit'] ? 0 : $amt,
                'balance' => round($running, 2),
            ];
        }

        return $entries;
    }

    /**
     * THU NGOẠI TỆ — Nợ 112 (ngoại tệ) / Có TK đối ứng (VND quy đổi).
     *
     * NGHIỆP VỤ: Nhập quỹ ngoại tệ hoặc về tài khoản ngoại tệ.
     * - Nợ 112 (TK NH ngoại tệ) / Nợ 1112 (Tiền mặt ngoại tệ): theo VND quy đổi
     * - Có TK đối ứng: theo VND quy đổi
     *
     * Nguyên tắc: Ghi nhận đồng thời nguyên tệ (fcAmount) và quy đổi ra VND
     * theo tỷ giá thực tế tại thời điểm ghi nhận (Điều 38 Thông tư 99).
     * RỦI RO: Tỷ giá quy đổi phải phù hợp với tỷ giá xuất/quy đổi của NH nơi giao dịch.
     * Sai tỷ giá → ảnh hưởng số dư ngoại tệ và chênh lệch tỷ giá cuối kỳ.
     * Audit trail: Lưu tỷ giá, nguyên tệ, VND để phục vụ đánh giá lại cuối kỳ.
     *
     * @param float $fcAmount Số tiền nguyên tệ
     * @param string $creditAccountCode TK Có (đối ứng)
     * @param string $currencyCode Mã tiền tệ (USD, EUR,...)
     * @param float $exchangeRate Tỷ giá quy đổi ra VND
     * @param string $description Nội dung
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     */
    public function recordReceiptFC(float $fcAmount, string $creditAccountCode, string $currencyCode, float $exchangeRate, string $description, string $reference, string $createdBy): array
    {
        if ($fcAmount <= 0) throw new \InvalidArgumentException('Số tiền phải lớn hơn 0.');
        if ($exchangeRate <= 0) throw new \InvalidArgumentException('Tỷ giá phải lớn hơn 0');

        $vndAmount = round($fcAmount * $exchangeRate);

        $creditAccount = $this->accountRepo->findByCode($creditAccountCode);
        if (!$creditAccount) throw new \InvalidArgumentException("Không tìm thấy tài khoản: {$creditAccountCode}");

        
        $txn = $this->journal->postEntry("FC receipt: {$description}", $reference, [
            ['account_code' => '112', 'amount' => $vndAmount, 'is_debit' => true],
            ['account_code' => $creditAccountCode, 'amount' => $vndAmount, 'is_debit' => false],
        ], $createdBy);

        $this->recordFCTransaction($txn->getId(), '112', $currencyCode, $fcAmount, $exchangeRate, $vndAmount, 'receipt', $description);

        return ['transaction_id' => $txn->getId(), 'fc_amount' => $fcAmount, 'vnd_amount' => $vndAmount, 'rate' => $exchangeRate, 'currency' => $currencyCode, 'type' => 'fc_receipt'];
    }

    /**
     * CHI NGOẠI TỆ — Nợ TK đối ứng (VND) / Có 112 (ngoại tệ VND quy đổi).
     *
     * NGHIỆP VỤ: Xuất quỹ ngoại tệ hoặc chuyển tiền ngoại tệ.
     * - Nợ TK đối ứng: theo VND quy đổi
     * - Có 112 (TK NH ngoại tệ) / Có 1112 (Tiền mặt ngoại tệ): theo VND quy đổi
     *
     * Nguyên tắc: Ghi nhận song song nguyên tệ và VND, tỷ giá tại thời điểm chi.
     * RỦI RO: Nếu chi ngoại tệ để thanh toán nhà cung cấp nước ngoài, cần kiểm tra
     * tỷ giá ghi trên hợp đồng và tỷ giá thực tế tại ngân hàng.
     * Ảnh hưởng: Phát sinh chênh lệch tỷ giá đã thực hiện (doanh thu 515/chi phí 635).
     *
     * @param float $fcAmount Số tiền nguyên tệ
     * @param string $debitAccountCode TK Nợ (đối ứng)
     * @param string $currencyCode Mã tiền tệ (USD, EUR,...)
     * @param float $exchangeRate Tỷ giá quy đổi ra VND
     * @param string $description Nội dung
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     */
    public function recordPaymentFC(float $fcAmount, string $debitAccountCode, string $currencyCode, float $exchangeRate, string $description, string $reference, string $createdBy): array
    {
        if ($fcAmount <= 0) throw new \InvalidArgumentException('Số tiền phải lớn hơn 0.');
        if ($exchangeRate <= 0) throw new \InvalidArgumentException('Tỷ giá phải lớn hơn 0');

        $vndAmount = round($fcAmount * $exchangeRate);

        $bank = $this->accountRepo->findByCode('112');
        if ($bank && $bank->getBalance() < $vndAmount) {
            throw new \InvalidArgumentException("Số dư ngân hàng không đủ: hiện có {$bank->getBalance()}, cần {$vndAmount}");
        }

        $debitAccount = $this->accountRepo->findByCode($debitAccountCode);
        if (!$debitAccount) throw new \InvalidArgumentException("Không tìm thấy tài khoản: {$debitAccountCode}");

        
        $txn = $this->journal->postEntry("FC payment: {$description}", $reference, [
            ['account_code' => $debitAccountCode, 'amount' => $vndAmount, 'is_debit' => true],
            ['account_code' => '112', 'amount' => $vndAmount, 'is_debit' => false],
        ], $createdBy);

        $this->recordFCTransaction($txn->getId(), '112', $currencyCode, -$fcAmount, $exchangeRate, -$vndAmount, 'payment', $description);

        return ['transaction_id' => $txn->getId(), 'fc_amount' => -$fcAmount, 'vnd_amount' => $vndAmount, 'rate' => $exchangeRate, 'currency' => $currencyCode, 'type' => 'fc_payment'];
    }

    //
    // SỔ PHỤ THEO DÕI NGOẠI TỆ:
    // Tổng hợp số dư nguyên tệ và VND theo từng tài khoản + loại ngoại tệ.
    // Tỷ giá bình quân gia quyền = Tổng VND / Tổng nguyên tệ.
    // Sử dụng cho: đánh giá lại ngoại tệ cuối kỳ, lập Bảng cân đối kế toán.
    // RỦI RO: Nếu có cả nhập và xuất ngoại tệ, tỷ giá bình quân gia quyền
    // có thể khác tỷ giá thị trường — cần điều chỉnh tại thời điểm đánh giá lại.

    public function getFCBalances(): array
    {
        if (!$this->pdo) return [];
        $rows = $this->pdo->query(
            "SELECT account_code, currency_code as currency, 
                    SUM(fc_amount) as fc_balance,
                    SUM(vnd_amount) as vnd_balance,
                    COUNT(*) as transaction_count
             FROM fc_transactions 
             GROUP BY account_code, currency_code
             ORDER BY currency_code"
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn($r) => [
            'account' => $r['account_code'],
            'currency' => $r['currency'],
            'fc_balance' => (float)$r['fc_balance'],
            'vnd_balance' => (float)$r['vnd_balance'],
            'avg_rate' => (float)$r['fc_balance'] != 0 
                ? round((float)$r['vnd_balance'] / (float)$r['fc_balance'], 2) 
                : 0,
            'transaction_count' => (int)$r['transaction_count'],
        ], $rows);
    }

    //
    // ĐÁNH GIÁ LẠI SỐ DƯ NGOẠI TỆ CUỐI KỲ (Điều 36 Thông tư 99):
    // So sánh số dư VND theo sổ kế toán với số dư VND quy đổi theo tỷ giá cuối kỳ.
    // Chênh lệch → hạch toán vào TK 413 (Chênh lệch tỷ giá hối đoái):
    //   - Lãi (gainLoss > 0): Nợ 112 — Có 413 (chưa thực hiện)
    //   - Lỗ  (gainLoss < 0): Nợ 413 — Có 112 (chưa thực hiện)
    //
    // Ảnh hưởng BC01: TK 413 là khoản mục vốn chủ sở hữu, điều chỉnh vào
    // chỉ tiêu "Chênh lệch tỷ giá hối đoái" (Mã số 417).
    // RỦI RO: Nếu đánh giá lại sai tỷ giá → ảnh hưởng LNST và thuế TNDN.
    // Chỉ đánh giá lại các khoản mục tiền tệ (111, 112), không đánh giá lại
    // hàng tồn kho hoặc TSCĐ.
    // IMPORTANT: Bút toán này là điều chỉnh chưa thực hiện, không ảnh hưởng
    // đến dòng tiền thực tế.

    public function revalueFC(string $accountCode, string $currencyCode, float $closingRate, string $asOfDate, string $createdBy): array
    {
        $balances = $this->getFCBalances();
        $entry = current(array_filter($balances, fn($b) => $b['account'] === $accountCode && $b['currency'] === $currencyCode));

        if (!$entry || abs($entry['fc_balance']) < 0.01) {
            return ['transaction_id' => null, 'gain_loss' => 0, 'message' => 'No FC balance to revalue'];
        }

        $bookRate = $entry['avg_rate'];
        $fcBalance = $entry['fc_balance'];
        $currentVnd = $entry['vnd_balance'];
        $revaluedVnd = round($fcBalance * $closingRate);
        $gainLoss = $revaluedVnd - $currentVnd;

        if (abs($gainLoss) < 1) {
            return ['transaction_id' => null, 'gain_loss' => 0, 'message' => 'No gain/loss'];
        }

        

        // PHÂN LOẠI CHÊNH LỆCH TỶ GIÁ:
        // Lãi (gainLoss > 0): Nợ 112 / Có 413 — ghi nhận lãi chưa thực hiện
        // Lỗ  (gainLoss < 0): Nợ 413 / Có 112 — ghi nhận lỗ chưa thực hiện
        //
        // LƯU Ý THUẾ: Chênh lệch tỷ giá chưa thực hiện (unrealized) KHÔNG được tính
        // vào thu nhập chịu thuế TNDN (Điều 4 Thông tư 78/2014/TT-BTC). Chỉ khi nào
        // khoản thu/chi ngoại tệ thực sự phát sinh (realized) mới ảnh hưởng BC02.
        // TK 413 là khoản mục VCSH trên BC01 (Mã số 417), không ảnh hưởng BC02.
        //
        // RỦI RO: Đánh giá lại với tỷ giá không phù hợp (không phải tỷ giá NHNN công bố
        // cuối kỳ) → chênh lệch sai → ảnh hưởng vốn chủ sở hữu (BC01).
        if ($gainLoss > 0) {
            // Unrealized gain: Dr 112 — Cr 413
            $txn = $this->journal->postEntry("FC revaluation: {$currencyCode} gain", "REV-{$currencyCode}-{$asOfDate}", [
                ['account_code' => $accountCode, 'amount' => $gainLoss, 'is_debit' => true],
                ['account_code' => '413', 'amount' => $gainLoss, 'is_debit' => false],
            ], $createdBy);
        } else {
            // Unrealized loss: Dr 413 — Cr 112
            $loss = abs($gainLoss);
            $txn = $this->journal->postEntry("FC revaluation: {$currencyCode} loss", "REV-{$currencyCode}-{$asOfDate}", [
                ['account_code' => '413', 'amount' => $loss, 'is_debit' => true],
                ['account_code' => $accountCode, 'amount' => $loss, 'is_debit' => false],
            ], $createdBy);
        }

        $this->recordFCTransaction($txn->getId(), $accountCode, $currencyCode, 0, $closingRate, $gainLoss, 'revaluation', "Period-end FX revaluation adj");

        return ['transaction_id' => $txn->getId(), 'gain_loss' => $gainLoss, 'book_rate' => $bookRate, 'closing_rate' => $closingRate, 'fc_balance' => $fcBalance];
    }

    /**
     * Ghi nhận chi tiết giao dịch ngoại tệ vào bảng fc_transactions.
     *
     * Lưu vết tất cả thông tin nguyên tệ, tỷ giá, VND quy đổi phục vụ:
     * - Theo dõi số dư ngoại tệ chi tiết theo từng loại tiền
     * - Đánh giá lại cuối kỳ (tính tỷ giá bình quân gia quyền)
     * - Kiểm toán và truy xuất nguồn gốc chênh lệch tỷ giá
     * RỦI RO: Nếu bỏ qua bước này, số dư ngoại tệ sẽ không chính xác và
     * việc đánh giá lại cuối kỳ sẽ sai.
     *
     * @param string $transactionId ID bút toán chính
     * @param string $accountCode Tài khoản ngoại tệ (1112, 112)
     * @param string $currencyCode Mã tiền tệ (USD, EUR,...)
     * @param float $fcAmount Số tiền nguyên tệ
     * @param float $exchangeRate Tỷ giá quy đổi
     * @param float $vndAmount Số tiền VND quy đổi
     * @param string $type Loại giao dịch (receipt, payment, revaluation)
     * @param string $description Nội dung
     * @return void
     */
    private function recordFCTransaction(string $transactionId, string $accountCode, string $currencyCode, float $fcAmount, float $exchangeRate, float $vndAmount, string $type, string $description): void
    {
        if (!$this->pdo) return;
        $this->pdo->prepare(
            'INSERT INTO fc_transactions (transaction_id, account_code, currency_code, fc_amount, exchange_rate, vnd_amount, type, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$transactionId, $accountCode, $currencyCode, $fcAmount, $exchangeRate, $vndAmount, $type, $description]);
    }
}

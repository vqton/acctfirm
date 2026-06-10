<?php
namespace Accounting\Domain\Model;

/**
 * Tài khoản ngân hàng — Sổ chi tiết TK 112 "Tiền gửi ngân hàng".
 *
 * Mỗi tài khoản ngân hàng là một tài khoản con của TK 112, phản ánh số dư
 * tiền gửi tại từng ngân hàng. Số dư khớp với sao kê ngân hàng sau khi
 * đối chiếu (Bank Reconciliation).
 *
 * NGHIỆP VỤ:
 * - $code: mã tài khoản ngân hàng theo hệ thống (VD: "1121-VCB")
 * - $accountNumber: số tài khoản thực tế tại ngân hàng
 * - $currency: loại tiền — mỗi tài khoản chỉ quản lý một loại tiền
 * - $openingBalance: số dư đầu kỳ khi thiết lập tài khoản
 *
 * LIÊN KẾT:
 * - CashService → ghi nhận thu/chi qua ngân hàng (qua JournalService)
 * - BankReconciliationService → đối chiếu sao kê cuối kỳ
 *
 * RỦI RO:
 * - Số dư trên hệ thống phải khớp với số dư sao kê ngân hàng sau đối chiếu
 * - Chênh lệch tỷ giá cho TK 112 ngoại tệ phải được hạch toán cuối kỳ
 * - Phí ngân hàng thường trừ trực tiếp vào tài khoản — cần ghi nhận kịp thời
 */
class BankAccount
{
    private string $id;
    private string $code;
    private string $bankName;
    private string $accountNumber;
    private string $accountHolder;
    private string $branch;
    private string $currency;
    private float $openingBalance;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    /**
     * Khởi tạo tài khoản ngân hàng.
     *
     * @param string $id Định danh tài khoản ngân hàng
     * @param string $code Mã tài khoản (VD: "1121-VCB")
     * @param string $bankName Tên ngân hàng
     * @param string $accountNumber Số tài khoản tại ngân hàng
     * @param string $accountHolder Chủ tài khoản
     * @param string $branch Chi nhánh ngân hàng
     * @param string $currency Loại tiền (VND, USD, EUR...)
     * @param float $openingBalance Số dư đầu kỳ khi thiết lập
     */
    public function __construct(
        string $id, string $code, string $bankName, string $accountNumber,
        string $accountHolder, string $branch = '', string $currency = 'VND',
        float $openingBalance = 0
    ) {
        $this->id = $id;
        $this->code = $code;
        $this->bankName = $bankName;
        $this->accountNumber = $accountNumber;
        $this->accountHolder = $accountHolder;
        $this->branch = $branch;
        $this->currency = $currency;
        $this->openingBalance = $openingBalance;
        $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @return string Định danh tài khoản ngân hàng */
    public function getId(): string { return $this->id; }

    /** @return string Mã tài khoản ngân hàng */
    public function getCode(): string { return $this->code; }

    /** @return string Tên ngân hàng */
    public function getBankName(): string { return $this->bankName; }

    /** @return string Số tài khoản tại ngân hàng */
    public function getAccountNumber(): string { return $this->accountNumber; }

    /** @return string Chủ tài khoản */
    public function getAccountHolder(): string { return $this->accountHolder; }

    /** @return string Chi nhánh ngân hàng */
    public function getBranch(): string { return $this->branch; }

    /** @return string Loại tiền tệ */
    public function getCurrency(): string { return $this->currency; }

    /** @return float Số dư đầu kỳ khi thiết lập */
    public function getOpeningBalance(): float { return $this->openingBalance; }

    /** @return bool Trạng thái hoạt động */
    public function isStatus(): bool { return $this->status; }

    /** @return \DateTimeImmutable Thời điểm tạo */
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @param string $code Mã tài khoản mới */
    public function setCode(string $code): void { $this->code = $code; }

    /** @param string $name Tên ngân hàng mới */
    public function setBankName(string $name): void { $this->bankName = $name; }

    /** @param string $num Số tài khoản mới */
    public function setAccountNumber(string $num): void { $this->accountNumber = $num; }

    /** @param string $holder Chủ tài khoản mới */
    public function setAccountHolder(string $holder): void { $this->accountHolder = $holder; }

    /** @param string $branch Chi nhánh mới */
    public function setBranch(string $branch): void { $this->branch = $branch; }

    /** @param string $currency Loại tiền mới */
    public function setCurrency(string $currency): void { $this->currency = $currency; }

    /** @param float $balance Số dư đầu kỳ mới */
    public function setOpeningBalance(float $balance): void { $this->openingBalance = $balance; }

    /** @param bool $status Trạng thái mới */
    public function setStatus(bool $status): void { $this->status = $status; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu tài khoản ngân hàng dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'bank_name' => $this->bankName,
            'account_number' => $this->accountNumber, 'account_holder' => $this->accountHolder,
            'branch' => $this->branch, 'currency' => $this->currency,
            'opening_balance' => $this->openingBalance, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

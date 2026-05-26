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

    public function getId(): string { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getBankName(): string { return $this->bankName; }
    public function getAccountNumber(): string { return $this->accountNumber; }
    public function getAccountHolder(): string { return $this->accountHolder; }
    public function getBranch(): string { return $this->branch; }
    public function getCurrency(): string { return $this->currency; }
    public function getOpeningBalance(): float { return $this->openingBalance; }
    public function isStatus(): bool { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setCode(string $code): void { $this->code = $code; }
    public function setBankName(string $name): void { $this->bankName = $name; }
    public function setAccountNumber(string $num): void { $this->accountNumber = $num; }
    public function setAccountHolder(string $holder): void { $this->accountHolder = $holder; }
    public function setBranch(string $branch): void { $this->branch = $branch; }
    public function setCurrency(string $currency): void { $this->currency = $currency; }
    public function setOpeningBalance(float $balance): void { $this->openingBalance = $balance; }
    public function setStatus(bool $status): void { $this->status = $status; }

    // Chuyển đổi model thành mảng để response API.
    // Mỗi BankAccount tương ứng một TK con của TK 112 (VD: 1121-VCB, 1121-CTG).
    // 'currency': mỗi tài khoản chỉ quản lý một loại tiền — ảnh hưởng đánh giá chênh lệch tỷ giá.
    // 'opening_balance': số dư đầu kỳ khi thiết lập, khớp với sao kê ngân hàng.
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

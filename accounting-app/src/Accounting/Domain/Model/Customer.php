<?php
namespace Accounting\Domain\Model;

/**
 * Khách hàng — Sổ chi tiết TK 131 "Phải thu của khách hàng".
 *
 * Mỗi khách hàng là một tài khoản con của TK 131, theo dõi công nợ phải thu.
 * Số dư bên Nợ (khách hàng nợ doanh nghiệp) hoặc bên Có (doanh nghiệp nợ
 * khách hàng do ứng trước).
 *
 * NGHIỆP VỤ:
 * - $balance: số dư công nợ tại thời điểm hiện tại (dư Nợ nếu > 0)
 * - $creditLimit: hạn mức tín dụng — vượt quá sẽ chặn bán hàng
 * - $taxCode: mã số thuế khách hàng — bắt buộc cho hóa đơn GTGT
 * - $paymentTerms: điều khoản thanh toán (VD: "Net30", "2/10 Net30")
 *
 * LIÊN KẾT:
 * - ArService → ghi nhận phải thu, thu tiền, đối trừ công nợ
 * - ArAging → phân tích tuổi nợ và trích lập dự phòng (TK 2293)
 *
 * RỦI RO:
 * - Không được xóa khách hàng đã phát sinh công nợ — chỉ deactivate status
 * - Đối trừ công nợ phải đúng nguyên tắc: cùng khách hàng, cùng loại tiền
 */
class Customer
{
    private string $id;
    private string $code;
    private string $name;
    private ?string $taxCode;
    private ?string $phone;
    private ?string $email;
    private ?string $address;
    private ?string $contactPerson;
    private ?string $paymentTerms;
    private float $creditLimit;
    private float $balance;
    private ?string $notes;
    private bool $status;
    private \DateTimeImmutable $createdAt;

    /**
     * Khởi tạo khách hàng.
     *
     * @param string $id Định danh khách hàng
     * @param string $code Mã khách hàng
     * @param string $name Tên khách hàng
     * @param string|null $taxCode Mã số thuế
     * @param string|null $phone Số điện thoại
     * @param string|null $email Email
     * @param string|null $address Địa chỉ
     * @param string|null $contactPerson Người liên hệ
     * @param string|null $paymentTerms Điều khoản thanh toán
     * @param float $creditLimit Hạn mức tín dụng
     * @param string|null $notes Ghi chú
     */
    public function __construct(
        string $id, string $code, string $name, ?string $taxCode = null,
        ?string $phone = null, ?string $email = null, ?string $address = null,
        ?string $contactPerson = null, ?string $paymentTerms = null,
        float $creditLimit = 0, ?string $notes = null
    ) {
        $this->id = $id; $this->code = $code; $this->name = $name;
        $this->taxCode = $taxCode; $this->phone = $phone; $this->email = $email;
        $this->address = $address; $this->contactPerson = $contactPerson;
        $this->paymentTerms = $paymentTerms; $this->creditLimit = $creditLimit;
        $this->balance = 0; $this->notes = $notes; $this->status = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** @return string Định danh khách hàng */
    public function getId(): string { return $this->id; }

    /** @return string Mã khách hàng */
    public function getCode(): string { return $this->code; }

    /** @return string Tên khách hàng */
    public function getName(): string { return $this->name; }

    /** @return string|null Mã số thuế */
    public function getTaxCode(): ?string { return $this->taxCode; }

    /** @return string|null Số điện thoại */
    public function getPhone(): ?string { return $this->phone; }

    /** @return string|null Email */
    public function getEmail(): ?string { return $this->email; }

    /** @return string|null Địa chỉ */
    public function getAddress(): ?string { return $this->address; }

    /** @return string|null Người liên hệ */
    public function getContactPerson(): ?string { return $this->contactPerson; }

    /** @return string|null Điều khoản thanh toán */
    public function getPaymentTerms(): ?string { return $this->paymentTerms; }

    /** @return float Hạn mức tín dụng */
    public function getCreditLimit(): float { return $this->creditLimit; }

    /** @return float Số dư công nợ phải thu */
    public function getBalance(): float { return $this->balance; }

    /** @return string|null Ghi chú */
    public function getNotes(): ?string { return $this->notes; }

    /** @return bool Trạng thái hoạt động */
    public function isStatus(): bool { return $this->status; }

    /** @return \DateTimeImmutable Thời điểm tạo */
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @param string $v Mã khách hàng mới */
    public function setCode(string $code): void { $this->code = $code; }

    /** @param string $v Tên khách hàng mới */
    public function setName(string $name): void { $this->name = $name; }

    /** @param string|null $v Mã số thuế mới */
    public function setTaxCode(?string $v): void { $this->taxCode = $v; }

    /** @param string|null $v Số điện thoại mới */
    public function setPhone(?string $v): void { $this->phone = $v; }

    /** @param string|null $v Email mới */
    public function setEmail(?string $v): void { $this->email = $v; }

    /** @param string|null $v Địa chỉ mới */
    public function setAddress(?string $v): void { $this->address = $v; }

    /** @param string|null $v Người liên hệ mới */
    public function setContactPerson(?string $v): void { $this->contactPerson = $v; }

    /** @param string|null $v Điều khoản thanh toán mới */
    public function setPaymentTerms(?string $v): void { $this->paymentTerms = $v; }

    /** @param float $v Hạn mức tín dụng mới */
    public function setCreditLimit(float $v): void { $this->creditLimit = $v; }

    /** @param float $v Số dư công nợ mới */
    public function setBalance(float $v): void { $this->balance = $v; }

    /** @param string|null $v Ghi chú mới */
    public function setNotes(?string $v): void { $this->notes = $v; }

    /** @param bool $v Trạng thái mới */
    public function setStatus(bool $v): void { $this->status = $v; }

    /**
     * Chuyển đổi model thành mảng để response API.
     *
     * @return array Dữ liệu khách hàng dạng mảng
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'tax_code' => $this->taxCode, 'phone' => $this->phone,
            'email' => $this->email, 'address' => $this->address,
            'contact_person' => $this->contactPerson,
            'payment_terms' => $this->paymentTerms,
            'credit_limit' => $this->creditLimit, 'balance' => $this->balance,
            'notes' => $this->notes, 'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
